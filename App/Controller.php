<?php

namespace MADEMO\App;

use SPTK\App;
use SPTK\Core\{Screen, Widget, Window};
use SPTK\Events\{EventContext, KeyNormalizer};

/** Coordinates the presentation, persistent editor widgets, slide actions, and live tile preview. */
final class Controller {

  public static Session $session;
  public static Window $window;
  public static string $screenId = 'editor';
  private static ?Widget $markdown = null;

  /** Bind XML screens and open the requested presentation or bundled example. */
  public static function initialize(EventContext $event): void {
    self::$window = App::eventLoop()->windows()[0];
    self::$markdown = self::screen('editor')->widget('markdown');
    self::$session = new Session();
    Settings::initialize();
    EditorPreview::initialize();
    self::widget('editor', 'themes')->setItems(SlideTheme::names());
    self::widget('editor', 'themes')->setValue(self::$session->theme);
    $openError = null;
    try {
      self::$session->open($GLOBALS['argv'][1] ?? APP_DIR . '/Assets/doc.md');
    } catch (\Throwable $error) {
      $openError = $error->getMessage();
      self::show('editor');
    }
    self::sync(true);
    if ($openError !== null) {
      self::status($openError);
    }
  }

  /** Resolve a screen by its declared XML ID. */
  public static function screen(string $id): Screen {
    return self::$window->screen($id) ?? throw new \RuntimeException('Missing screen: ' . $id);
  }

  /** Resolve a persistent application widget by its screen and XML IDs. */
  public static function widget(string $screen, string $id): Widget {
    return self::screen($screen)->widget($id)
      ?? ($screen === 'editor' && $id === 'markdown' ? self::$markdown : null)
      ?? throw new \RuntimeException('Missing widget: ' . $id);
  }

  /** Switch screens and remember which view the timer should observe. */
  public static function show(string $id): void {
    $presenting = self::$screenId === 'presentation';
    self::$window->setCurrentScreenId($id);
    self::$screenId = $id;
    if ($id !== 'presentation') {
      Presenter::close();
      if ($presenting) {
        Presenter::resetTiming();
        WindowPreferences::restore();
      }
    } else if (!$presenting) {
      WindowPreferences::present();
    }
  }

  /** Commit the current editor buffer without resetting its cursor or history. */
  public static function commit(): bool {
    return self::commitEditorBuffer();
  }

  /** Commit the current Markdown editor value. */
  private static function commitEditorBuffer(): bool {
    return self::$session->commit(self::widget('editor', 'markdown')->getValue());
  }

  /** Update slide layouts and UI state while retaining all editor widget instances. */
  public static function sync(bool $loadEditor = false): void {
    $session = self::$session;
    $session->index = $session->document->clamp($session->index);
    $parsed = $session->document->slide($session->index);
    self::screen('presentation')->setLayout((new SlideLayout($session->theme))->build($parsed['slide'], enterChildren: true));
    EditorPreview::update($parsed);
    $items = [];
    foreach ($session->document->slideTitles() as $index => $title) {
      $number = ($index + 1) . '. ';
      $items[] = ['value' => (string)$index, 'label' => $number . $title, 'searchOffset' => mb_strlen($number)];
    }
    $slides = self::widget('editor', 'slides');
    $currentItems = array_map(fn(array $item): array => [
      'value' => $item['value'],
      'label' => $item['label'],
      'searchOffset' => $item['searchOffset'] ?? 0,
    ], $slides->items());
    if ($currentItems !== $items) {
      $slides->setItems($items);
    }
    if ($slides->getValue() !== (string)$session->index) {
      $slides->setValue((string)$session->index);
    }
    if ($loadEditor) {
      self::widget('editor', 'markdown')->setValue(implode("\n", $session->document->code($session->index)));
    }
    self::widget('editor', 'heading')->setText($session->document->displayTitle() . ($session->dirty ? ' *' : ''));
    self::widget('editor', 'filePath')->setText($session->document->file() ?? 'Unsaved presentation');
    Presenter::update($parsed);
    self::$window->resize();
  }

  /** Refresh the live preview only when an editor buffer has changed. */
  public static function preview(EventContext $event): void {
    if (App::eventLoop()->window(self::$window->id()) === null) {
      Presenter::close();
      return;
    }
    if (isset(self::$session) && self::$screenId === 'editor' && self::commitEditorBuffer()) {
      self::sync();
    }
  }

  /** Apply editor changes immediately. */
  public static function apply(EventContext $event): bool {
    self::commit();
    self::sync();
    return true;
  }

  /** Show the editor for the current slide. */
  public static function edit(EventContext $event): bool {
    self::show('editor');
    return true;
  }

  /** Resume the presentation at the current slide. */
  public static function present(EventContext $event): bool {
    self::apply($event);
    self::show('presentation');
    Presenter::show($event);
    return true;
  }

  /** Start presenting from the first slide. */
  public static function start(EventContext $event): bool {
    self::commit();
    Presenter::resetTiming();
    self::$session->index = 0;
    self::sync(true);
    self::show('presentation');
    Presenter::show($event);
    return true;
  }

  /** Load each valid slide list selection as soon as it changes. */
  public static function slideChanged(EventContext $event): void {
    $value = $event->widget->getValue();
    if ($value === null || (int)$value === self::$session->index) {
      return;
    }
    self::commitEditorBuffer();
    self::$session->index = (int)$value;
    self::sync(true);
  }

  /** Apply list order while retaining the selected slide and its editor buffer. */
  public static function slidesReordered(EventContext $event): void {
    self::commit();
    $order = $event->widget->values();
    $index = array_search($event->widget->getValue(), $order, true);
    self::$session->document->reorder($order);
    self::$session->index = $index;
    self::$session->dirty = true;
    self::sync();
  }

  /** Rebuild the slide tiles using the selected theme. */
  public static function themeChanged(EventContext $event): void {
    self::commit();
    self::$session->theme = $event->widget->getValue() ?? 'Default';
    self::sync();
  }

  /** Advance one slide in presentation or notes mode. */
  public static function next(EventContext $event): bool {
    if (self::$screenId === 'editor') {
      self::commit();
    }
    self::$session->index++;
    self::sync(true);
    return true;
  }

  /** Return to the preceding slide. */
  public static function previous(EventContext $event): bool {
    if (self::$screenId === 'editor') {
      self::commit();
    }
    self::$session->index--;
    self::sync(true);
    return true;
  }

  /** Insert a blank slide after the current slide. */
  public static function add(EventContext $event): void {
    self::commit();
    self::$session->index = self::$session->document->insert(self::$session->index, ['## New slide']);
    self::changed();
  }

  /** Duplicate the current slide's Markdown. */
  public static function cloneSlide(EventContext $event): void {
    self::commit();
    self::$session->index = self::$session->document->insert(self::$session->index, self::$session->document->code(self::$session->index));
    self::changed();
  }

  /** Delete the selected slide while retaining its source for restoration. */
  public static function delete(EventContext $event): void {
    self::commit();
    if (self::$session->document->delete(self::$session->index)) {
      self::changed();
    } else {
      self::status('Keep at least one slide in the presentation.');
    }
  }

  /** Restore the last deleted slide. */
  public static function restore(EventContext $event): void {
    self::commit();
    $index = self::$session->document->restore();
    if ($index !== null) {
      self::$session->index = $index;
      self::changed();
    }
  }

  /** Navigate from the editor only when no widget is editing or searching. */
  public static function editorSlideKey(EventContext $event): bool {
    if (self::screen('editor')->activeLeaf() !== null || KeyNormalizer::normalizeModifiers((int)$event->input->key->mod) !== 0) {
      return false;
    }
    return match (KeyNormalizer::keyName((int)$event->input->key->key, (int)$event->input->key->mod)) {
      'space' => self::next($event),
      'backspace' => self::previous($event),
      default => false,
    };
  }

  /** Handle presentation navigation, editor access, and numbered link shortcuts. */
  public static function presentationKey(EventContext $event, bool $fromPresenter = false): bool {
    $mod = (int)$event->input->key->mod;
    $key = KeyNormalizer::keyName((int)$event->input->key->key, $mod);
    if (!$fromPresenter && ($key === 'enter' || ($key === 'escape' && self::screen('presentation')->navigationDepth() > 0))) {
      return false;
    }
    if (in_array($key, ['space', 'backspace'], true) && KeyNormalizer::normalizeModifiers($mod) !== 0) {
      return false;
    }
    return match ($key) {
      'space' => self::next($event),
      'backspace' => self::previous($event),
      'escape', 'f2' => self::edit($event),
      'f8' => Presenter::show($event),
      'f9' => Settings::show($event),
      'f10' => FileActions::quit($event),
      default => ctype_digit($key) ? Settings::openLink((int)$key) : false,
    };
  }

  /** Show a one-line editor message and update its rendered screen. */
  public static function status(string $message, string $kind = 'notice'): void {
    self::widget('editor', 'status')->{$kind}($message);
    self::$window->resize();
  }

  /** Mark a slide edit as unsaved and load the newly selected source. */
  private static function changed(): void {
    self::$session->dirty = true;
    self::sync(true);
  }

}
