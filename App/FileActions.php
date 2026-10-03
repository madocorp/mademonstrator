<?php

namespace MADEMO\App;

use SPTK\App;
use SPTK\Core\{Color, Style};
use SPTK\Events\{EventContext, KeyNormalizer, EventDefinition};
use SPTK\Layout\{LayoutLeaf, LayoutNode};
use SPTK\Widgets\FileSelector\FileSelector;
use SPTK\Widgets\Input\Input;
use SPTK\Widgets\Text\Text;

/** File operations use the editor's Markdown slot and its status bar. */
final class FileActions {
  private static bool $saving = false;
  private static bool $browsing = false;
  private static ?string $pending = null;
  private static ?string $pendingPath = null;
  private static ?string $overwritePath = null;
  private static bool $continueAfterSave = false;
  private static ?LayoutLeaf $editorLeaf = null;
  private static ?LayoutLeaf $originalFocus = null;
  private static ?LayoutLeaf $selectorLeaf = null;
  private static ?LayoutLeaf $pathLeaf = null;
  private static ?LayoutNode $panel = null;
  private static ?FileSelector $selector = null;
  private static ?Input $pathInput = null;
  private static ?Text $heading = null;

  public static function openScreen(EventContext $event): bool {
    Controller::commit();
    self::browser(false);
    return true;
  }

  /** Accept Ctrl+O only while no editor widget is active. */
  public static function openShortcut(EventContext $event): bool {
    return Controller::screen('editor')->activeLeaf() === null ? self::openScreen($event) : false;
  }

  public static function saveAs(EventContext $event): bool {
    Controller::commit();
    self::browser(true);
    return true;
  }

  public static function save(EventContext $event): bool {
    Controller::commit();
    $path = Controller::$session->document->file();
    if ($path === null) {
      self::browser(true);
    } else if (self::write($path) && self::$continueAfterSave) {
      self::perform();
    }
    return true;
  }

  /** Accept Ctrl+S only while no editor widget is active. */
  public static function saveShortcut(EventContext $event): bool {
    return Controller::screen('editor')->activeLeaf() === null ? self::save($event) : false;
  }

  public static function create(EventContext $event): void {
    self::request('new');
  }

  public static function quit(EventContext $event): bool {
    self::request('quit');
    return true;
  }

  /** Screen-wide browser shortcuts work while either field is active. */
  public static function key(EventContext $event): bool {
    if (!self::$browsing) {
      return false;
    }
    $key = KeyNormalizer::keyName((int)$event->input->key->key, (int)$event->input->key->mod);
    return match ($key) {
      'escape', 'f2' => self::cancel($event),
      'f5' => self::focusPath(),
      'f6' => self::choose($event),
      'enter' => $event->widget === self::$pathInput ? self::choose($event) : false,
      default => true,
    };
  }

  private static function focusPath(): bool {
    Controller::screen('editor')->activateLeaf(self::$pathLeaf);
    Controller::$window->resize();
    return true;
  }

  /** Accepting a file immediately runs the requested operation. */
  public static function fileAccepted(EventContext $event): bool {
    $path = self::$selector->getValue();
    if (is_string($path) && is_file($path)) {
      self::$pathInput->setValue($path);
      return self::choose($event);
    }
    return false;
  }

  public static function directoryChanged(EventContext $event): void {
    if (self::$saving) {
      $name = basename(self::$pathInput->getValue()) ?: 'presentation.md';
      self::$pathInput->setValue(self::$selector->path() . '/' . $name);
    }
  }

  public static function choose(EventContext $event): bool {
    $path = trim(self::$pathInput->getValue());
    if ($path === '') {
      self::error('Enter a file path first.');
      return true;
    }
    if (!str_starts_with($path, '/')) {
      $path = self::$selector->path() . '/' . $path;
    }
    if (!self::$saving) {
      self::request('open', $path);
    } else if (is_file($path) && realpath($path) !== Controller::$session->document->file()) {
      self::$overwritePath = $path;
      self::confirm('Overwrite ' . $path . '?',
        fn() => self::overwrite(),
        fn() => Controller::status('Choose another path. F5 edits the filename.', 'warning'));
    } else if (self::write($path)) {
      self::afterSave();
    }
    return true;
  }

  private static function overwrite(): void {
    $path = self::$overwritePath;
    self::$overwritePath = null;
    if ($path !== null && self::write($path)) {
      self::afterSave();
    }
  }

  public static function saveAndContinue(EventContext $event): void {
    self::$continueAfterSave = true;
    self::save($event);
  }

  public static function continueAction(EventContext $event): void {
    self::perform();
  }

  public static function cancel(EventContext $event): bool {
    self::$pending = null;
    self::$pendingPath = null;
    self::$overwritePath = null;
    self::$continueAfterSave = false;
    self::hideBrowser();
    Controller::status('File action cancelled.');
    return true;
  }

  private static function request(string $action, ?string $path = null): void {
    Controller::commit();
    self::$pending = $action;
    self::$pendingPath = $path;
    if (Controller::$session->dirty) {
      self::confirm('Unsaved changes. Save before continuing?',
        fn() => self::saveAndContinue(new EventContext('activate')),
        fn() => self::perform());
    } else {
      self::perform();
    }
  }

  private static function perform(): void {
    $action = self::$pending;
    $path = self::$pendingPath;
    self::$pending = null;
    self::$pendingPath = null;
    self::$continueAfterSave = false;
    try {
      if ($action === 'quit') {
        App::eventLoop()->stop();
        return;
      }
      Controller::$session->open($action === 'new' ? null : $path);
      Controller::sync(true);
      self::hideBrowser();
      Controller::show('editor');
      Controller::status($action === 'new' ? 'New presentation created.' : 'Opened ' . $path);
    } catch (\Throwable $error) {
      self::error($error->getMessage());
    }
  }

  private static function write(string $path): bool {
    try {
      Controller::$session->document->save($path);
      Controller::$session->dirty = false;
      Controller::sync();
      Controller::status('Saved ' . Controller::$session->document->file());
      return true;
    } catch (\Throwable $error) {
      self::error($error->getMessage());
      return false;
    }
  }

  private static function afterSave(): void {
    if (self::$continueAfterSave) {
      self::perform();
    } else {
      self::hideBrowser();
      Controller::show('editor');
      Controller::status('Saved ' . Controller::$session->document->file());
    }
  }

  /** Keep both widget instances and swap the visible layout subtree. */
  private static function browser(bool $saving): void {
    self::buildPanel();
    self::$saving = $saving;
    $file = Controller::$session->document->file();
    $directory = $file === null ? Settings::$directory : dirname($file);
    $browseError = null;
    try {
      self::$selector->setPath($directory);
    } catch (\Throwable $error) {
      $browseError = $error->getMessage();
    }
    self::$pathInput->setValue($saving ? ($file ?? self::$selector->path() . '/presentation.md') : '');
    self::$heading->setText($saving ? 'SAVE AS — Return selects a file; F5 edits path; F6 saves; Esc cancels' : 'OPEN — Return selects a file; F5 edits path; F6 opens; Esc cancels');
    Controller::show('editor');
    $screen = Controller::screen('editor');
    if (!self::$browsing) {
      self::$originalFocus = $screen->selectedLeaf();
      $screen->release();
      $screen->layout->replaceChild(self::$editorLeaf, self::$panel);
      $screen->setLayout($screen->layout);
      self::$browsing = true;
    }
    $screen->activateLeaf(self::$selectorLeaf);
    Controller::$window->resize();
    if ($browseError !== null) {
      self::error($browseError);
    } else {
      Controller::status($saving ? 'Select a file or press F5 to enter a save path.' : 'Select a Markdown file or press F5 to enter its path.');
    }
  }

  private static function hideBrowser(): void {
    if (!self::$browsing) {
      return;
    }
    $screen = Controller::screen('editor');
    $screen->release();
    $screen->layout->replaceChild(self::$panel, self::$editorLeaf);
    $screen->setLayout($screen->layout);
    self::$browsing = false;
    if (self::$originalFocus !== null) {
      $screen->selectLeaf(self::$originalFocus);
    }
    self::$originalFocus = null;
    Controller::$window->resize();
  }

  private static function buildPanel(): void {
    if (self::$panel !== null) {
      return;
    }
    $screen = Controller::screen('editor');
    foreach ($screen->layout->leaves() as $leaf) {
      if ($leaf->instance()->id() === 'markdown') {
        self::$editorLeaf = $leaf;
        break;
      }
    }
    if (self::$editorLeaf === null) {
      throw new \RuntimeException('Markdown editor slot is missing.');
    }
    $style = new Style(background: Color::from('#0000aa'), cursorBackground: Color::from('#000055'));
    self::$selector = new FileSelector(Settings::$directory, false, true, true, $style);
    self::$selector->setId('browser');
    self::$pathInput = new Input('', $style, label: 'FILE PATH (F5 to edit)');
    self::$pathInput->setId('path');
    self::$heading = new Text('', $style, false);
    self::$heading->setId('fileHeading');
    self::$panel = new LayoutNode('vertical', '1*', '');
    self::$panel->addLeaf(new LayoutLeaf('Text', '', '1', self::$heading));
    self::$selectorLeaf = new LayoutLeaf('FileSelector', '', '1*', self::$selector, [
      new EventDefinition('keyDown', 'enter', self::class . '::fileAccepted'),
      new EventDefinition('change', null, self::class . '::directoryChanged'),
    ]);
    self::$panel->addLeaf(self::$selectorLeaf);
    self::$pathLeaf = new LayoutLeaf('Input', '', '2', self::$pathInput);
    self::$panel->addLeaf(self::$pathLeaf);
  }

  private static function confirm(string $message, callable $yes, callable $no): void {
    Controller::widget('editor', 'status')->confirm($message, $yes, $no, fn() => self::cancel(new EventContext('cancel')));
    Controller::$window->resize();
  }

  private static function error(string $message): void {
    Controller::status($message, 'error');
  }
}
