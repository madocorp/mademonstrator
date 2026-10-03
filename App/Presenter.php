<?php

namespace MADEMO\App;

use SPTK\App;
use SPTK\Core\{Color, Style, Window, WindowPlacement};
use SPTK\Events\{EventContext, KeyNormalizer};
use SPTK\Layout\LayoutLeaf;
use SPTK\Widgets\Text\Text;
use SPTK\XmlParser\ScreenParser;

/** Owns a separate speaker-notes window synchronized with the slide shown to the audience. */
final class Presenter {

  private static ?Window $window = null;
  private static ?PresentationTiming $timing = null;
  private static ?int $timer = null;
  private static ?string $timingColor = null;
  private static ?string $timingLabel = null;

  private const COLORS = [
    'green' => '#52d98a',
    'yellow' => '#ffd166',
    'red' => '#ff6e6e',
  ];

  /** Return the registered helper window, forgetting one that the user has closed. */
  public static function window(): ?Window {
    if (self::$window !== null && App::eventLoop()->window(self::$window->id()) === null) {
      self::$window = null;
    }
    return self::$window;
  }

  /** Open the helper window without changing the projector's presentation screen. */
  public static function show(EventContext $event): bool {
    $options = WindowPreferences::options(WindowPreferences::$helper, true);
    if ($options['mode'] === 'none') {
      self::close();
      return true;
    }
    if (self::window() === null) {
      $screen = (new ScreenParser())->parse('presenter.xml', new Style(), 'presenter', 'Speaker notes');
      self::$window = new Window(['title' => 'MaDemonstrator — Speaker notes', 'width' => 78, 'height' => 36, 'state' => 'hidden', 'resizable' => true, 'screens' => [$screen]]);
      App::eventLoop()->registerWindow(self::$window);
      self::$timingColor = null;
      self::$timingLabel = null;
    }
    self::$timing ??= new PresentationTiming();
    self::$timer ??= App::eventLoop()->addTimer(self::class . '::tick', 250);
    self::update(Controller::$session->document->slide(Controller::$session->index));
    self::$window->show();
    WindowPlacement::apply(self::$window, $options);
    return true;
  }

  /** Close the helper when leaving presentation mode. */
  public static function close(): void {
    if (self::$timer !== null) {
      App::eventLoop()->removeTimer(self::$timer);
      self::$timer = null;
    }
    if (self::window() !== null) {
      App::eventLoop()->quitWindow(self::$window->id());
      self::$window = null;
    }
  }

  /** Begin a fresh presentation on the next timed slide. */
  public static function resetTiming(): void {
    self::$timing = new PresentationTiming();
  }

  /** Refresh the countdown even when the slide does not change. */
  public static function tick(EventContext $event): void {
    if (self::window() === null) {
      if (self::$timer !== null) {
        App::eventLoop()->removeTimer(self::$timer);
        self::$timer = null;
      }
      return;
    }
    self::updateTiming();
  }

  /** Refresh only an existing helper, keeping both windows on the same slide. */
  public static function update(array $parsed): void {
    $window = self::window();
    if ($window === null) {
      return;
    }
    $screen = $window->screen('presenter');
    $screen->widget('title')->setText(self::title($parsed));
    $screen->widget('notes')->setText(self::notesText($parsed));
    self::updateTiming(false);
    $window->resize();
  }

  private static function updateTiming(bool $render = true): void {
    $window = self::window();
    if ($window === null) {
      return;
    }
    $screen = $window->screen('presenter');
    $timing = self::$timing ??= new PresentationTiming();
    $state = $timing->state(Controller::$session->document, Controller::$session->index);
    if ($timing->stopped() && self::$timer !== null) {
      App::eventLoop()->removeTimer(self::$timer);
      self::$timer = null;
    }
    $label = 'Time left: ' . $state['text'];
    if (self::$timingColor === $state['status'] && self::$timingLabel === $label) {
      return;
    }
    if (self::$timingColor !== $state['status']) {
      foreach ($screen->layout->leaves(true) as $leaf) {
        if ($leaf->instance()->id() !== 'timing') {
          continue;
        }
        $color = Color::from(self::COLORS[$state['status']]);
        $replacement = new Text($label, (new Style())->with(['Foreground' => $color]));
        $replacement->setId('timing');
        $screen->layout->replaceChild($leaf, new LayoutLeaf('Text', '', '1', $replacement, navigate: false));
        $screen->setLayout($screen->layout);
        self::$timingColor = $state['status'];
        break;
      }
    } else {
      $screen->widget('timing')->setText($label);
    }
    self::$timingLabel = $label;
    if ($render) {
      $window->resize();
    }
  }

  /** Format the current slide number and its speaker-facing title. */
  public static function title(array $parsed): string {
    $session = Controller::$session;
    return ($session->index + 1) . '/' . $session->document->count() . ' — ' . $parsed['promptTitle'];
  }

  /** Return hidden Markdown comments or a useful empty-notes message. */
  public static function notesText(array $parsed): string {
    return $parsed['promptText'] ?: 'No speaker notes on this slide. Add an HTML comment to the Markdown.';
  }

  /** Control slides with Space/Backspace while leaving other keys to notes and tile navigation. */
  public static function key(EventContext $event): bool {
    if (App::eventLoop()->window(Controller::$window->id()) === null) {
      self::close();
      return true;
    }
    $key = KeyNormalizer::keyName((int)$event->input->key->key, (int)$event->input->key->mod);
    return in_array($key, ['space', 'backspace', 'f2', 'escape', 'f10'], true)
      ? Controller::presentationKey($event, true) : false;
  }

}
