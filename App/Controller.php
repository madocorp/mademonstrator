<?php

namespace MADEMO\App;

use SPTK2\Core\InputAction;
use SPTK2\Core\InputEvent;
use SPTK2\Core\Place;
use SPTK2\Core\Theme;
use SPTK2\Runtime\SdlApp;
use SPTK2\Runtime\SdlWindow;
use SPTK2\Runtime\SdlWindowOptions;
use SPTK2\Widgets\Button;
use SPTK2\Widgets\DialogLayer;
use SPTK2\Widgets\DialogPanel;
use SPTK2\Widgets\Dock;
use SPTK2\Widgets\FileSelector;
use SPTK2\Widgets\Flow;
use SPTK2\Widgets\FlowRow;
use SPTK2\Widgets\ImageView;
use SPTK2\Widgets\Input;
use SPTK2\Widgets\Label;
use SPTK2\Widgets\ListItem;
use SPTK2\Widgets\ListView;
use SPTK2\Widgets\MenuBar;
use SPTK2\Widgets\MenuItem;
use SPTK2\Widgets\StatusBar;
use SPTK2\Widgets\TextBlock;
use SPTK2\Widgets\TextEditor;

final class Controller {

  private Presentation $presentation;
  private int $currentSlide = 0;
  private bool $newSlide = false;
  private bool $presentationMode = false;
  private array $config;
  private MenuBar $menu;
  private MenuItem $slideMenu;
  private MenuItem $styleMenu;
  private SlideView $slide;
  private DialogLayer $dialogs;
  private ?Shell $root = null;
  private ?SdlWindow $window = null;
  private ?SdlWindow $helperWindow = null;
  private ?Shell $helperRoot = null;
  private ?StatusBar $helperTitle = null;
  private ?TextBlock $helperContent = null;

  public function __construct(private string $appDir, private SdlApp $app) {
    $loaded = Config::load();
    $this->config = $loaded['config'];
  }

  public function setWindow(SdlWindow $window): void {
    $this->window = $window;
  }

  public function build(): Dock {
    $root = new Shell('mademonstrator-root', fn(InputEvent $event): bool => $this->handleSlideShortcut($event));
    $this->root = $root;
    $this->menu = new MenuBar('menu');
    $this->slide = new SlideView('slide');
    $this->dialogs = new DialogLayer('dialogs');
    $this->buildMenu();
    $root->place($this->menu, Place::dock('top'));
    $root->place($this->slide, Place::fill());
    $root->add($this->dialogs);
    $this->open($this->appDir . '/Layout/doc.md');
    return $root;
  }

  public function open(string $path): void {
    try {
      $this->presentation = new Presentation($path);
      $this->currentSlide = 0;
      $this->showCurrentSlide();
    } catch (\Throwable $e) {
      $this->message('Failed to open the presentation', $e->getMessage(), 'error');
    }
  }

  private function create(): void {
    $this->presentation = new Presentation();
    $this->presentation->changeSlide(0, ['# New presentation']);
    $this->currentSlide = 0;
    $this->showCurrentSlide();
  }

  private function start(): void {
    $this->presentationMode = true;
    $this->setMenuVisible(false);
    $this->configurePresentationWindow();
    $this->openHelperWindow();
    $this->setCurrentSlide(0);
    $this->slide->root()->requestFocus();
  }

  private function resume(): void {
    $this->presentationMode = true;
    $this->setMenuVisible(false);
    $this->configurePresentationWindow();
    $this->openHelperWindow();
    $this->showCurrentSlide();
    $this->slide->root()->requestFocus();
  }

  private function leavePresentationMode(): void {
    $this->presentationMode = false;
    $this->setMenuVisible(true);
    $this->closeHelperWindow();
    $this->window?->setFullscreen(false);
    $this->menu->requestFocus();
  }

  public function handleSlideShortcut(InputEvent $event): bool {
    if (
      $event->type !== 'key' ||
      $this->dialogs->top() !== null ||
      !isset($this->presentation) ||
      !$this->slideNavigationFocusActive()
    ) {
      return false;
    }
    if (InputAction::activate($event, 'slide') || InputAction::right($event, 'slide') || InputAction::pageDown($event, 'slide')) {
      $this->setCurrentSlide($this->currentSlide + 1);
      return true;
    }
    if (InputAction::backspace($event, 'slide') || InputAction::left($event, 'slide') || InputAction::pageUp($event, 'slide')) {
      $this->setCurrentSlide($this->currentSlide - 1);
      return true;
    }
    if ($this->presentationMode && InputAction::cancel($event, 'slide')) {
      $this->leavePresentationMode();
      return true;
    }
    $key = InputAction::normalizedKey($event->key);
    if ($this->presentationMode && preg_match('/^[0-9]$/', $key)) {
      $this->gotoLink((int)$key);
      return true;
    }
    return false;
  }

  private function setCurrentSlide(int $index): void {
    $this->currentSlide = $this->presentation->show($index, $this->slide, $this->selectedStyleName());
    $this->syncHelperWindow();
    $this->rebuildSlideMenu();
  }

  private function showCurrentSlide(): void {
    $this->setCurrentSlide($this->currentSlide);
  }

  private function gotoLink(int $index): void {
    $link = $this->presentation->link($index);
    if ($link === false) {
      return;
    }
    $cmd = str_replace('%url%', escapeshellarg($link), $this->config['browserCmd']);
    exec($cmd);
  }

  private function edit(bool $new): void {
    $this->newSlide = $new;
    $panel = new DialogPanel('edit-slide', ['title' => $new ? 'Add slide' : 'Edit slide', 'size' => 'big', 'contentColumns' => 92]);
    $editor = new TextEditor('mdeditor', $new ? '' : implode("\n", $this->presentation->code($this->currentSlide)));
    $editor->setTokenizer(MarkdownHighlighter::class);
    $editor->setPreferredRows(18);
    $cheatsheet = new TextEditor('cheatsheet');
    $cheatsheet->setFile($this->appDir . '/Layout/cheatsheet.txt')->setReadOnly(true);
    $cheatsheet->setPreferredRows(18);
    $row = new FlowRow('editor-row', 'left', 1);
    $row->place($editor, 58)
      ->place($cheatsheet, 33);
    $panel->addContent($row, 18);
    $panel->addButton($this->button('Save', function() use ($panel, $editor): void {
      $code = $editor->getValue();
      $this->presentation->changeSlide($this->currentSlide, $code, $this->newSlide);
      if ($this->newSlide) {
        $this->currentSlide++;
      }
      $this->dialogs->pop($panel);
      $this->showCurrentSlide();
    }));
    $panel->addButton($this->button('Cancel', fn() => $this->dialogs->pop($panel)));
    $this->dialogs->push($panel);
  }

  private function cloneSlide(): void {
    $this->presentation->changeSlide($this->currentSlide, $this->presentation->code($this->currentSlide), true);
    $this->currentSlide++;
    $this->showCurrentSlide();
  }

  private function deleteSlide(): void {
    $this->presentation->deleteSlide($this->currentSlide);
    $this->showCurrentSlide();
  }

  private function restoreSlide(): void {
    $restored = $this->presentation->restoreSlide();
    if ($restored !== false) {
      $this->currentSlide = $restored;
      $this->showCurrentSlide();
    }
  }

  private function sortSlides(): void {
    $items = [];
    foreach ($this->presentation->slideTitles() as $index => $title) {
      $items[] = new ListItem(['text' => $title, 'value' => $index, 'selectable' => true, 'selected' => true]);
    }
    $list = new ListView('order', $items, ['selectionOrder' => true]);
    $list->setSelectedValues(array_keys($items));
    $panel = new DialogPanel('sort-slides', ['title' => 'Sort slides', 'size' => 'normal', 'contentColumns' => 56]);
    $panel->addContent(new TextBlock('', 'Select slides in the desired order with Shift+Space.'), 2);
    $panel->addContent($list, 12);
    $panel->addButton($this->button('Save', function() use ($panel, $list): void {
      $this->presentation->sort($list->selectedValues());
      $this->currentSlide = 0;
      $this->dialogs->pop($panel);
      $this->showCurrentSlide();
    }));
    $panel->addButton($this->button('Cancel', fn() => $this->dialogs->pop($panel)));
    $this->dialogs->push($panel);
  }

  private function openFileDialog(bool $save): void {
    $panel = new DialogPanel($save ? 'save-file' : 'open-file', ['title' => $save ? 'Save presentation' : 'Open presentation', 'size' => 'big']);
    $selector = new FileSelector('file', $this->config['defaultDir'], $this->presentation->file() ?? '', [
      'extensions' => ['.md'],
      'createFile' => $save,
    ]);
    $panel->addContent($selector);
    $panel->addButton($this->button($save ? 'Save' : 'Open', function() use ($panel, $selector, $save): void {
      $path = (string)$selector->getValue();
      if ($path === '') {
        return;
      }
      $this->dialogs->pop($panel);
      $save ? $this->save($path) : $this->open($path);
    }));
    $panel->addButton($this->button('Cancel', fn() => $this->dialogs->pop($panel)));
    $this->dialogs->push($panel);
  }

  private function save(?string $path = null): void {
    $path ??= $this->presentation->file();
    if ($path === null) {
      $this->openFileDialog(true);
      return;
    }
    $this->presentation->save($path);
  }

  private function settings(): void {
    $panel = new DialogPanel('settings', ['title' => 'Settings', 'size' => 'normal', 'contentColumns' => 70]);
    $defaultStyle = new Input('defaultStyle', $this->config['defaultStyle']);
    $defaultDir = new FileSelector('defaultDir', $this->config['defaultDir'], $this->config['defaultDir']);
    $presentationWindow = new Input('presentationWindow', $this->config['presentationWindow']);
    $promptBox = new Input('promptBox', $this->config['promptBox']);
    $browserCmd = new Input('browserCmd', $this->config['browserCmd']);
    foreach ([
      'Default style:' => $defaultStyle,
      'Default directory:' => $defaultDir,
      'Presentation window:' => $presentationWindow,
      'Helper window:' => $promptBox,
      'Browser command:' => $browserCmd,
    ] as $label => $field) {
      $panel->addContent(new Label('', $label));
      $panel->addContent($field);
    }
    $panel->addButton($this->button('Save', function() use ($panel, $defaultStyle, $defaultDir, $presentationWindow, $promptBox, $browserCmd): void {
      $this->config = [
        'defaultStyle' => $defaultStyle->getValue(),
        'defaultDir' => $defaultDir->getValue(),
        'presentationWindow' => $presentationWindow->getValue(),
        'promptBox' => $promptBox->getValue(),
        'browserCmd' => $browserCmd->getValue(),
      ];
      Config::save($this->config);
      $this->dialogs->pop($panel);
      $this->showCurrentSlide();
      $this->rebuildStyleMenu();
    }));
    $panel->addButton($this->button('Cancel', fn() => $this->dialogs->pop($panel)));
    $this->dialogs->push($panel);
  }

  private function about(): void {
    $license = is_file($this->appDir . '/UNLICENSE') ? file_get_contents($this->appDir . '/UNLICENSE') : '';
    $license = preg_replace("/(?<!\n)\n(?!\n)/", ' ', trim((string)$license)) ?? trim((string)$license);
    $panel = new DialogPanel('about', ['title' => 'MaDemonstrator', 'size' => 'big', 'contentColumns' => 82]);
    $left = new Flow('about-left');
    $logo = new ImageView('mademonstrator-logo', $this->appDir . '/Layout/mademo.png');
    $logo->setCellSize(26, 11)->setFit('contain');
    $left->place($logo, $logo->preferredRows());
    $left->place(new TextBlock('', "MaDemonstrator\nSPTK2 migration preview\nVersion:\n0.0.0.0.0.0.1"));
    $licenseText = new TextBlock('license', "Unlicense:\n\n" . $license);
    $licenseText->setPreferredRows(22);
    $row = new FlowRow('about-row', 'left', 3);
    $row->place($left, 30);
    $row->place($licenseText, 49);
    $panel->addContent($row, 24);
    $panel->addButton($this->button('Close', fn() => $this->dialogs->pop($panel)));
    $this->dialogs->push($panel);
  }

  private function message(string $title, string $message, string $variant = 'normal'): void {
    $panel = new DialogPanel('message', ['title' => $title, 'variant' => $variant, 'size' => 'normal']);
    $panel->addContent(new TextBlock('', $message));
    $panel->addButton($this->button('OK', fn() => $this->dialogs->pop($panel)));
    $this->dialogs->push($panel);
  }

  private function buildMenu(): void {
    $this->menu->addItem(new MenuItem(['label' => 'Presentation', 'items' => [
      ['label' => 'Start', 'action' => fn() => $this->start()],
      ['label' => 'Resume', 'action' => fn() => $this->resume()],
      ['label' => 'Open', 'action' => fn() => $this->openFileDialog(false)],
      ['label' => 'Create new', 'action' => fn() => $this->create()],
      ['label' => 'Save', 'action' => fn() => $this->save()],
      ['label' => 'Save as', 'action' => fn() => $this->openFileDialog(true)],
    ]]));
    $this->menu->addItem(new MenuItem(['label' => 'Edit', 'items' => [
      ['label' => 'Edit', 'action' => fn() => $this->edit(false)],
      ['label' => 'Add', 'action' => fn() => $this->edit(true)],
      ['label' => 'Clone', 'action' => fn() => $this->cloneSlide()],
      ['label' => 'Delete', 'action' => fn() => $this->deleteSlide()],
      ['label' => 'Restore', 'action' => fn() => $this->restoreSlide()],
      ['label' => 'Sort', 'action' => fn() => $this->sortSlides()],
    ]]));
    $this->slideMenu = new MenuItem(['label' => 'Slide']);
    $this->styleMenu = new MenuItem(['label' => 'Style']);
    $this->menu->addItem($this->slideMenu);
    $this->menu->addItem($this->styleMenu);
    $this->menu->addItem(new MenuItem(['label' => 'MaDemonstrator', 'items' => [
      ['label' => 'Settings', 'action' => fn() => $this->settings()],
      ['label' => 'About', 'action' => fn() => $this->about()],
      ['label' => 'Exit', 'action' => fn() => $this->app->quit()],
    ]]));
    $this->rebuildStyleMenu();
  }

  private function rebuildSlideMenu(): void {
    $items = [];
    foreach ($this->presentation->slideTitles() as $index => $title) {
      $items[] = ['label' => $title, 'checked' => $index === $this->currentSlide, 'action' => fn() => $this->setCurrentSlide($index)];
    }
    $this->slideMenu->update(['items' => $items]);
  }

  private function rebuildStyleMenu(): void {
    $items = [];
    foreach (SlideTheme::names() as $name) {
      $label = $name;
      $items[] = ['label' => $label, 'checked' => $name === $this->config['defaultStyle'], 'action' => function() use ($name): void {
        $this->config['defaultStyle'] = $name;
        $this->showCurrentSlide();
        $this->rebuildStyleMenu();
      }];
    }
    $this->styleMenu->update(['items' => $items]);
  }

  private function selectedStyleName(): string {
    $name = basename((string)($this->config['defaultStyle'] ?? ''));
    if ($name === '') {
      $name = 'Default';
    }
    if (in_array($name, SlideTheme::names(), true)) {
      return $name;
    }
    return 'Default';
  }

  private function button(string $label, callable $callback): Button {
    return (new Button('', $label))->setOnPress($callback);
  }

  private function setMenuVisible(bool $visible): void {
    $this->root?->setElementVisible($this->menu, $visible);
  }

  private function slideNavigationFocusActive(): bool {
    if ($this->slide->root()->context()?->currentFocus() === $this->slide->root()) {
      return true;
    }
    return $this->presentationMode && $this->helperRoot !== null && $this->helperRoot->context()?->currentFocus() === $this->helperRoot;
  }

  private function openHelperWindow(): void {
    $mode = strtolower(trim((string)($this->config['promptBox'] ?? 'none')));
    if ($mode === '' || str_contains($mode, 'none')) {
      return;
    }
    if ($this->helperWindow !== null && !$this->helperWindow->isClosed()) {
      $this->syncHelperWindow();
      return;
    }
    $root = new Shell('prompt-root', fn(InputEvent $event): bool => $this->handleSlideShortcut($event));
    $root->setGridAlignment('top-left');
    $root->setTheme(new Theme(fg: '#cccccc', bg: '#000000', muted: '#cccccc'));
    $this->helperTitle = new StatusBar('prompt-title');
    $this->helperContent = new TextBlock('prompt-text');
    $root->place($this->helperTitle, Place::dock('top'));
    $root->place(new PaddedBox('prompt-padding', $this->helperContent, 1), Place::fill());
    $this->helperRoot = $root;
    $this->helperWindow = $this->app->addWindow($root, $this->windowOptionsFromGeometry((string)$this->config['promptBox'], 'PromptBox', 72, 18));
    $this->syncHelperWindow();
  }

  private function closeHelperWindow(): void {
    if ($this->helperWindow !== null) {
      $this->app->closeWindow($this->helperWindow);
    }
    $this->helperWindow = null;
    $this->helperRoot = null;
    $this->helperTitle = null;
    $this->helperContent = null;
  }

  private function syncHelperWindow(): void {
    if ($this->helperTitle === null || $this->helperContent === null || !isset($this->presentation)) {
      return;
    }
    $this->helperTitle->setText($this->presentation->promptTitle());
    $this->helperContent->setText($this->presentation->promptText());
  }

  private function configurePresentationWindow(): void {
    $mode = strtolower((string)($this->config['presentationWindow'] ?? 'full'));
    if (str_contains($mode, 'full')) {
      $this->window?->setFullscreen(true);
      return;
    }
    $this->window?->setFullscreen(false);
    if (str_contains($mode, 'max')) {
      $this->window?->maximize();
    } else {
      $this->window?->restore();
    }
  }

  private function windowOptionsFromGeometry(string $geometry, string $title, int $columns, int $rows): SdlWindowOptions {
    $options = new SdlWindowOptions(title: $title, columns: $columns, rows: $rows);
    $mode = strtolower(trim($geometry));
    if (str_contains($mode, 'max')) {
      $options->maximized = true;
      return $options;
    }
    if (preg_match('/(\d+)\s*x\s*(\d+)/i', $mode, $match)) {
      $width = (int)$match[1];
      $height = (int)$match[2];
      if ($width > 160 || $height > 80) {
        $options->columns = max(24, intdiv($width, 10));
        $options->rows = max(8, intdiv($height, 24));
      } else {
        $options->columns = max(24, $width);
        $options->rows = max(8, $height);
      }
    }
    return $options;
  }

}
