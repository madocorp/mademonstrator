<?php

define('APP_DIR', dirname(__DIR__));
define('APP_PATH', APP_DIR . '/mademonstrator.php');
require_once APP_DIR . '/SPTK/App.php';
require_once APP_DIR . '/App/Autoload.php';
spl_autoload_register(['SPTK\\App', 'load']);
spl_autoload_register(['MADEMO\\App\\Autoload', 'load']);
require_once __DIR__ . '/Support.php';

use MADEMO\App\{Controller, EditorPreview, FileActions, Presenter, Settings, WindowPreferences};
use SPTK\App;
use SPTK\Core\{Window, WindowPlacement};
use SPTK\Events\{EventContext, EventLoop};
use SPTK\Rendering\Font;
use SPTK\SDLWrapper\{SDL, TTF};
use SPTK\XmlParser\XmlParser;
use function MADEMO\Tests\{assertSame, assertTrue};

/** Select an editor widget and send Return through the real screen input path. */
function activateWidget(string $screenId, string $id, SDL $sdl): void {
  $screen = Controller::screen($screenId);
  foreach ($screen->layout->leaves() as $leaf) {
    if ($leaf->instance()->id() === $id) {
      $screen->selectLeaf($leaf);
      break;
    }
  }
  $event = $sdl->ffi->new('SDL_Event');
  $event->type = SDL::SDL_EVENT_KEY_DOWN;
  $event->key->key = SDL::KEY_RETURN;
  $event->key->mod = 0;
  $screen->handleEvent($event);
}

/** Send a key through the window so status confirmation sees native input. */
function pressKey(Window $window, SDL $sdl, int $key, int $mod = 0): void {
  $input = $sdl->ffi->new('SDL_Event');
  $input->type = SDL::SDL_EVENT_KEY_DOWN;
  $input->key->key = $key;
  $input->key->mod = $mod;
  $window->handleEvent($input);
}

function typeText(Window $window, string $value): void {
  $buffer = \FFI::new('char[64]');
  \FFI::memcpy($buffer, $value, strlen($value));
  $window->handleEvent((object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $buffer]]);
}

/** Export the actual SDL window framebuffer for optional visual inspection. */
function snapshotWindow(Window $window, SDL $sdl, ?string $path = null): GdImage {
  $renderer = (new ReflectionProperty($window, 'ffiRenderer'))->getValue($window);
  $state = new \SPTK\Rendering\RenderState($renderer);
  $frame = (new ReflectionProperty($window, 'frameTexture'))->getValue($window);
  $sdl->checkReturnValue($sdl->ffi->SDL_SetRenderTarget($renderer, $frame), 'SDL_SetRenderTarget');
  $surface = $sdl->ffi->SDL_RenderReadPixels($renderer, null);
  if ($surface === null) {
    $state->restore();
    throw new RuntimeException('Cannot snapshot the application framebuffer.');
  }
  $rgba = $sdl->ffi->SDL_ConvertSurface($surface, SDL::SDL_PIXELFORMAT_RGBA8888);
  try {
    $width = $rgba->w;
    $height = $rgba->h;
    $pitch = $rgba->pitch;
    $bytes = FFI::string($rgba->pixels, $pitch * $height);
    $image = imagecreatetruecolor($width, $height);
    for ($y = 0; $y < $height; $y++) {
      $row = unpack('L*', substr($bytes, $y * $pitch, $width * 4));
      for ($x = 0; $x < $width; $x++) {
        imagesetpixel($image, $x, $y, $row[$x + 1] >> 8);
      }
    }
    if ($path !== null) {
      imagepng($image, $path);
    }
    return $image;
  } finally {
    $sdl->ffi->SDL_DestroySurface($rgba);
    $sdl->ffi->SDL_DestroySurface($surface);
    $state->restore();
  }
}

/** Verify title ink survives complete native tile painting in the captured frame. */
function assertPresentationTitle(GdImage $image): void {
  $ink = 0;
  for ($y = 0; $y < 150; $y++) {
    for ($x = 100; $x < imagesx($image) - 100; $x++) {
      $pixel = imagecolorat($image, $x, $y);
      if (($pixel >> 16) > 120 && (($pixel >> 8) & 255) > 120) {
        $ink++;
      }
    }
  }
  assertTrue($ink > 100, 'Presentation title ink reaches the retained SDL frame.');
}

putenv('SDL_VIDEODRIVER=dummy');
$sdl = new SDL();
$sdl->checkReturnValue($sdl->ffi->SDL_Init(SDL::SDL_INIT_VIDEO), 'SDL_Init');
$ttf = new TTF();
$sdl->checkReturnValue($ttf->ffi->TTF_Init(), 'TTF_Init');
$app = (new ReflectionClass(App::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(App::class, 'instance'))->setValue(null, $app);
(new ReflectionProperty(App::class, 'sdl'))->setValue($app, $sdl);
(new ReflectionProperty(App::class, 'ttf'))->setValue($app, $ttf);
$font = new Font($ttf);
$font->open('LiberationMono-Regular', 16);
(new ReflectionProperty(App::class, 'font'))->setValue($app, $font);
$loop = new EventLoop();
(new ReflectionProperty(App::class, 'eventLoop'))->setValue($app, $loop);
$xml = new XmlParser();
$data = $xml->windows[0];
$data['state'] = 'hidden';
$data['width'] = 132;
$data['height'] = 54;
$window = new Window($data);
$loop->registerWindow($window);
$path = tempnam(sys_get_temp_dir(), 'mademo-integration-');
$event = new EventContext('activate');
try {
  Controller::initialize(new EventContext('init'));
  $frameBeforeRefresh = (new ReflectionProperty($window, 'frameTexture'))->getValue($window);
  $window->resize();
  assertTrue($frameBeforeRefresh === (new ReflectionProperty($window, 'frameTexture'))->getValue($window), 'Refreshing an unchanged window reuses its framebuffer.');
  assertTrue(Controller::$session->document->count() > 10, 'Bundled slides open at startup.');
  assertSame('editor', Controller::$screenId, 'Startup uses editor mode.');
  assertSame(0, (new ReflectionProperty($window, 'currentScreen'))->getValue($window), 'Native window starts on the first editor screen.');
  assertSame(null, Presenter::window(), 'Startup does not open speaker notes.');
  assertSame(1, count($loop->windows()), 'Only the editor window opens at startup.');
  assertSame('Markdown Presentation Engine', Controller::widget('editor', 'heading')->text(), 'Editor heading uses the first H1 instead of the first slide label.');
  assertTrue(Controller::widget('editor', 'status') instanceof \SPTK\Widgets\StatusBar\StatusBar, 'Editor uses the reusable status widget.');
  assertTrue(str_contains(Controller::widget('editor', 'status')->text(), 'Presentation title'), 'Initial status follows the selected heading tip.');
  $previewBlank = null;
  foreach (Controller::screen('editor')->layout->leaves() as $leaf) {
    if ($leaf->isPixel() && $leaf->instance() instanceof \SPTK\Widgets\Empty\Placeholder && $leaf->pixelContent()->width > 0 && $leaf->pixelContent()->height > 0) {
      $previewBlank = $leaf;
      break;
    }
  }
  assertTrue($previewBlank !== null, 'The inactive editor preview has a visible slide background tile.');
  $blankArea = $previewBlank->pixelContent();
  $previewFrame = snapshotWindow($window, $sdl);
  $blankPixel = imagecolorat($previewFrame, $blankArea->x + intdiv($blankArea->width, 2), $blankArea->y + intdiv($blankArea->height, 2)) & 0xffffff;
  $blankPaddingPixel = imagecolorat($previewFrame, $blankArea->x - 1, $blankArea->y + intdiv($blankArea->height, 2)) & 0xffffff;
  $blankColor = $previewBlank->instance()->background();
  $dimmedBlankColor = $blankColor->darkened();
  assertSame(($dimmedBlankColor->r << 16) | ($dimmedBlankColor->g << 8) | $dimmedBlankColor->b, $blankPixel, 'Inactive preview content darkens with its slide tile.');
  assertSame($blankPixel, $blankPaddingPixel, 'Inactive preview padding darkens with its content.');
  imagedestroy($previewFrame);
  WindowPreferences::$presentation = 'normal';
  WindowPreferences::$helper = '78x36';
  Controller::present($event);
  $keys = $sdl->ffi->new('SDL_Event');
  $keys->type = SDL::SDL_EVENT_KEY_DOWN;
  $slideScreen = Controller::screen('presentation');
  assertSame(0, $slideScreen->navigationDepth(), 'Presentation starts at the slide container.');
  $keys->key->key = SDL::KEY_RETURN;
  $keys->key->mod = 0;
  $window->handleEvent($keys);
  assertSame(1, $slideScreen->navigationDepth(), 'Return enters the slide.');
  assertTrue($slideScreen->selectedLeaf()->instance() instanceof \MADEMO\App\SlideText, 'Slide entry selects a visible element.');
  $slideEdge = \SPTK\Core\Color::from(\MADEMO\App\SlideTheme::palette(Controller::$session->theme)['bg'])->darkened();
  $navigatingFrame = snapshotWindow($window, $sdl);
  assertSame(($slideEdge->r << 16) | ($slideEdge->g << 8) | $slideEdge->b, imagecolorat($navigatingFrame, 0, 0) & 0xffffff, 'Slide edges dim with the content during element navigation.');
  imagedestroy($navigatingFrame);
  $firstSlideElement = $slideScreen->selectedLeaf();
  $keys->key->key = SDL::KEY_DOWN;
  $window->handleEvent($keys);
  assertSame(1, $slideScreen->navigationDepth(), 'Arrow movement stays inside the slide.');
  assertTrue($slideScreen->selectedLeaf() !== $firstSlideElement, 'Down moves between pixel slide elements.');
  $keys->key->key = SDL::KEY_ESCAPE;
  $window->handleEvent($keys);
  assertSame(0, $slideScreen->navigationDepth(), 'Escape leaves the slide.');
  assertSame('presentation', Controller::$screenId, 'Leaving the slide keeps presentation mode.');
  $keys->key->key = SDL::KEY_SPACE;
  $window->handleEvent($keys);
  assertSame(1, Controller::$session->index, 'Presentation navigation is consumed by slide actions.');
  foreach ([SDL::KEY_RIGHT, SDL::KEY_DOWN, SDL::KEY_PAGEDOWN, SDL::KEY_LEFT, SDL::KEY_PAGEUP, SDL::KEY_HOME, SDL::KEY_END, SDL::KEY_F5] as $otherKey) {
    pressKey($window, $sdl, $otherKey);
  }
  assertSame(1, Controller::$session->index, 'Other presentation keys do not change slides.');
  Controller::edit($event);
  $editorScreen = Controller::screen('editor');
  pressKey($window, $sdl, SDL::KEY_SPACE);
  assertSame(2, Controller::$session->index, 'Space advances from the editor screen.');
  pressKey($window, $sdl, SDL::KEY_BACKSPACE);
  assertSame(1, Controller::$session->index, 'Backspace returns from the editor screen.');
  Controller::widget('editor', 'nextSlide')->press();
  assertSame(2, Controller::$session->index, 'Editor Next button advances one slide.');
  Controller::widget('editor', 'previousSlide')->press();
  assertSame(1, Controller::$session->index, 'Editor Previous button returns one slide.');
  assertTrue(count($editorScreen->layout->leaves()) > count($editorScreen->layout->leaves(true)), 'Preview tiles render but are excluded from navigation.');
  activateWidget('editor', 'slides', $sdl);
  $editorScreen->release();
  $keys->key->key = SDL::KEY_RIGHT;
  $window->handleEvent($keys);
  assertSame('previewTile', $editorScreen->selectedLeaf()->instance()->id(), 'Right arrow selects the preview as one tile.');
  assertTrue(str_contains(Controller::widget('editor', 'status')->text(), 'Slide preview'), 'Preview XML tip reaches the status bar.');
  $selectedBlank = null;
  foreach ($editorScreen->layout->leaves() as $leaf) {
    if ($leaf->isPixel() && $leaf->instance() instanceof \SPTK\Widgets\Empty\Placeholder && $leaf->pixelContent()->width > 0 && $leaf->pixelContent()->height > 0) {
      $selectedBlank = $leaf;
      break;
    }
  }
  assertTrue($selectedBlank !== null, 'Selected preview has a visible slide background tile.');
  $selectedBlankArea = $selectedBlank->pixelContent();
  $selectedBlankColor = $selectedBlank->instance()->background();
  $selectedFrame = snapshotWindow($window, $sdl);
  $selectedBlankPixel = imagecolorat($selectedFrame, $selectedBlankArea->x + intdiv($selectedBlankArea->width, 2), $selectedBlankArea->y + intdiv($selectedBlankArea->height, 2)) & 0xffffff;
  $selectedPaddingPixel = imagecolorat($selectedFrame, $selectedBlankArea->x - 1, $selectedBlankArea->y + intdiv($selectedBlankArea->height, 2)) & 0xffffff;
  assertSame(($selectedBlankColor->r << 16) | ($selectedBlankColor->g << 8) | $selectedBlankColor->b, $selectedBlankPixel, 'Selected preview content uses the slide theme color.');
  assertSame($selectedBlankPixel, $selectedPaddingPixel, 'Selected preview padding matches its content.');
  imagedestroy($selectedFrame);
  pressKey($window, $sdl, SDL::KEY_RETURN);
  assertSame(0, $editorScreen->navigationDepth(), 'Return does not enter the preview tile.');
  $previewFocus = $editorScreen->selectedLeaf();
  $previewLeaves = $editorScreen->layout->focusedLeaves($previewFocus);
  assertTrue(count($previewLeaves) > 1, 'Preview group contains the rendered slide elements.');
  foreach ($previewLeaves as $previewLeaf) {
    assertTrue($previewLeaf->grid()->height > 0, 'Preview elements retain a nonzero height.');
  }
  Controller::sync();
  assertTrue($editorScreen->selectedLeaf() === $previewFocus, 'Preview focus survives slide rebuilding.');
  $window->handleEvent($keys);
  assertSame('themes', $editorScreen->selectedLeaf()->instance()->id(), 'Next arrow skips preview descendants.');
  activateWidget('editor', 'themes', $sdl);
  assertSame('Up/Down changes the slide style; Esc finishes.', Controller::widget('editor', 'status')->text(), 'Active XML tip overrides the list default.');
  $editorScreen->release();
  assertTrue(!($editorScreen->selectedLeaf()->instance() instanceof \MADEMO\App\SlideText), 'Arrow navigation skips preview text tiles.');
  assertTrue(!($editorScreen->selectedLeaf()->instance() instanceof \MADEMO\App\SlideImage), 'Arrow navigation skips preview image tiles.');
  $editor = Controller::widget('editor', 'markdown');
  $editor->setValue("## Edited slide\n\nA **styled** preview.\n\n<!-- Only the presenter sees this. -->");
  activateWidget('editor', 'markdown', $sdl);
  assertTrue($editor->editing(), 'Real screen activates Markdown editor.');
  assertTrue(str_contains(Controller::widget('editor', 'status')->text(), 'Editing text:'), 'Activated editor shows editing controls instead of slide-list help.');
  Controller::preview(new EventContext('timer'));
  assertTrue($editor->editing(), 'Live preview retains the activated editor.');
  assertSame('Edited slide', Controller::$session->document->slideTitles()[1], 'Live preview commits the new title.');
  assertSame('Markdown Presentation Engine *', Controller::widget('editor', 'heading')->text(), 'Editing an H2 slide keeps the first H1 as the document title.');
  assertSame(true, Controller::$session->dirty, 'Live edit marks the document dirty.');
  Controller::cloneSlide($event);
  assertSame(2, Controller::$session->index, 'Clone selects the new slide.');
  Controller::delete($event);
  Controller::restore($event);
  assertSame('Edited slide', Controller::$session->document->slideTitles()[2], 'Deleted source can be restored.');
  Controller::screen('editor')->release();
  activateWidget('editor', 'slides', $sdl);
  $beforeOrder = Controller::$session->document->slideTitles();
  $beforeCode = $editor->getValue();
  $keys->key->key = SDL::KEY_UP;
  $keys->key->mod = SDL::MOD_SHIFT;
  assertTrue(str_contains(Controller::widget('editor', 'status')->text(), 'Shift+Up/Down reorders'), 'Active slide list explains its reorder shortcut.');
  Controller::screen('editor')->handleEvent($keys);
  [$beforeOrder[1], $beforeOrder[2]] = [$beforeOrder[2], $beforeOrder[1]];
  assertSame($beforeOrder, Controller::$session->document->slideTitles(), 'Native list reordering updates document order.');
  assertSame($beforeCode, $editor->getValue(), 'Reordering retains the selected slide buffer.');
  assertSame('1', Controller::widget('editor', 'slides')->getValue(), 'Reordering retains list selection.');
  assertSame(1, Controller::$session->index, 'Slide ordering updates selected index.');
  $keys->key->key = SDL::KEY_DOWN;
  Controller::screen('editor')->handleEvent($keys);
  assertSame(2, Controller::$session->index, 'Repeated reorder retains active list input.');
  $keys->key->key = SDL::KEY_UP;
  Controller::screen('editor')->handleEvent($keys);
  assertSame($beforeOrder, Controller::$session->document->slideTitles(), 'Reverse reorder restores the expected order.');
  $keys->key->mod = 0;
  EditorPreview::toggle($event);
  assertSame('editor', Controller::$screenId, 'Notes toggle retains the editor screen.');
  assertTrue(Controller::widget('editor', 'previewNotes') instanceof \SPTK\Widgets\Text\Text, 'Notes use the existing preview area with the app font.');
  assertSame($beforeCode, $editor->getValue(), 'Notes toggle retains Markdown editor text.');
  $previewLines = (new ReflectionProperty(Controller::widget('editor', 'previewNotes'), 'lines'))->getValue(Controller::widget('editor', 'previewNotes'));
  assertTrue(in_array('Only the presenter sees this.', $previewLines, true), 'Notes preview displays hidden Markdown comments.');
  EditorPreview::toggle($event);
  assertSame(false, EditorPreview::$notes, 'Second toggle restores the slide preview.');
  Controller::present($event);
  assertSame('presentation', Controller::$screenId, 'Projector continues showing slides.');
  assertSame(2, count($loop->windows()), 'Presentation has a separate helper window.');
  $helper = Presenter::window();
  $timingWidget = $helper->screen('presenter')->widget('timing');
  $timingLines = (new ReflectionProperty($timingWidget, 'lines'))->getValue($timingWidget);
  assertTrue(str_starts_with($timingLines[0], 'Time left: '), 'Helper displays the presentation countdown.');
  $helperLines = (new ReflectionProperty($helper->screen('presenter')->widget('notes'), 'lines'))->getValue($helper->screen('presenter')->widget('notes'));
  assertTrue(in_array('Only the presenter sees this.', $helperLines, true), 'Separate helper displays the selected slide notes.');
  if (($notesSnapshotDirectory = getenv('MADEMO_SNAPSHOT_DIR')) !== false) {
    imagedestroy(snapshotWindow($helper, $sdl, $notesSnapshotDirectory . '/mademonstrator-notes.png'));
  }
  foreach (Controller::screen('presentation')->layout->leaves() as $slideLeaf) {
    if ($slideLeaf->instance() instanceof \MADEMO\App\SlideText) {
      assertTrue(!str_contains(json_encode($slideLeaf->instance()->sourceRuns()), 'Only the presenter sees this.'), 'Speaker notes stay out of the projector slide.');
    }
  }
  $helperKey = $sdl->ffi->new('SDL_Event');
  $helperKey->type = SDL::SDL_EVENT_KEY_DOWN;
  $helperKey->key->key = SDL::KEY_RIGHT;
  $keys->key->key = SDL::KEY_RETURN;
  $window->handleEvent($keys);
  assertSame(1, Controller::screen('presentation')->navigationDepth(), 'Audience can explore while the helper remains open.');
  $helper->handleEvent($helperKey);
  $helperKey->key->key = SDL::KEY_PAGEDOWN;
  $helper->handleEvent($helperKey);
  $helperKey->key->key = SDL::KEY_HOME;
  $helper->handleEvent($helperKey);
  assertSame(1, Controller::$session->index, 'Helper arrows, page keys, and Home do not change slides.');
  $helperKey->key->key = SDL::KEY_SPACE;
  $helper->handleEvent($helperKey);
  assertSame(2, Controller::$session->index, 'Helper Space advances the projector slide.');
  $helperKey->key->key = SDL::KEY_BACKSPACE;
  $helper->handleEvent($helperKey);
  assertSame(1, Controller::$session->index, 'Helper Backspace returns to the previous slide.');
  $helperKey->key->key = SDL::KEY_SPACE;
  $helper->handleEvent($helperKey);
  assertSame(2, Controller::$session->index, 'Helper Space can advance again.');
  foreach ($helper->screen('presenter')->layout->leaves() as $leaf) {
    if ($leaf->instance()->id() === 'notes') {
      $helper->screen('presenter')->activateLeaf($leaf);
      break;
    }
  }
  $helperKey->key->key = SDL::KEY_SPACE;
  $helper->handleEvent($helperKey);
  assertSame(3, Controller::$session->index, 'Helper Space works while notes are active.');
  $helperKey->key->key = SDL::KEY_BACKSPACE;
  $helper->handleEvent($helperKey);
  assertSame(2, Controller::$session->index, 'Helper Backspace works while notes are active.');
  assertSame(0, Controller::screen('presentation')->navigationDepth(), 'Changing slides restores outer navigation.');
  assertSame('presentation', Controller::$screenId, 'Helper navigation retains the audience presentation screen.');
  $loop->quitWindow($helper->id());
  Controller::next($event);
  assertSame(null, Presenter::window(), 'Closing helper leaves slide navigation usable.');
  Presenter::show($event);
  assertSame(2, count($loop->windows()), 'Helper can reopen after being closed.');
  $helperKey->key->key = SDL::KEY_F2;
  Presenter::window()->handleEvent($helperKey);
  assertSame('editor', Controller::$screenId, 'Helper can return to the editor through native input.');
  assertSame(1, count($loop->windows()), 'Returning to editor closes the helper window.');
  $editorGeometry = WindowPlacement::capture($window);
  WindowPreferences::$presentation = '1280x720';
  WindowPreferences::$helper = '640x480';
  Controller::present($event);
  assertSame(['mode' => 'normal', 'width' => 1280, 'height' => 720], WindowPlacement::capture($window), 'Configured presentation pixel size is applied.');
  assertSame(['mode' => 'normal', 'width' => 640, 'height' => 480], WindowPlacement::capture(Presenter::window()), 'Configured helper pixel size is applied.');
  Controller::edit($event);
  assertSame($editorGeometry, WindowPlacement::capture($window), 'Leaving presentation restores editor geometry.');
  WindowPreferences::$helper = 'none';
  Controller::present($event);
  assertSame(1, count($loop->windows()), 'Disabled helper does not open while presenting.');
  Presenter::show($event);
  assertSame(null, Presenter::window(), 'F8 respects the disabled helper preference.');
  Controller::edit($event);
  WindowPreferences::$presentation = 'normal';
  WindowPreferences::$helper = '78x36';
  Settings::show($event);
  assertSame('settings', Controller::$screenId, 'Settings and about screen is available.');
  FileActions::saveAs($event);
  assertSame('editor', Controller::$screenId, 'File selector appears in the editor.');
  assertSame('browser', Controller::screen('editor')->selectedLeaf()->instance()->id(), 'File selector receives focus.');
  assertTrue(Controller::widget('editor', 'browser')->active(), 'File selector is activated.');
  Controller::widget('editor', 'path')->setValue($path);
  FileActions::choose($event);
  assertTrue(Controller::widget('editor', 'status')->confirming(), 'Save As asks before overwriting an existing file.');
  assertSame('warning', Controller::widget('editor', 'status')->kind(), 'Confirmation uses warning color.');
  pressKey($window, $sdl, ord('y'));
  assertSame(false, Controller::$session->dirty, 'Successful save clears the unsaved state.');
  assertSame(realpath($path), Controller::$session->document->file(), 'Successful save updates the file path.');
  assertSame($beforeOrder, (new \MADEMO\App\Presentation($path))->slideTitles(), 'Reordered slides survive save and reload.');
  activateWidget('editor', 'markdown', $sdl);
  $beforeHotkeyText = Controller::widget('editor', 'markdown')->getValue();
  $beforeHotkeySlides = Controller::$session->document->count();
  $beforeHotkeyFile = file_get_contents($path);
  pressKey($window, $sdl, ord('s'));
  assertSame($beforeHotkeyText, Controller::widget('editor', 'markdown')->getValue(), 'Character keydown does not insert text early.');
  typeText($window, 's');
  assertTrue(Controller::widget('editor', 'markdown')->getValue() !== $beforeHotkeyText, 'Active Markdown editor receives the typed character.');
  $afterS = Controller::widget('editor', 'markdown')->getValue();
  pressKey($window, $sdl, ord('a'));
  typeText($window, 'a');
  assertSame($beforeHotkeySlides, Controller::$session->document->count(), 'Typing a in the active Markdown editor does not add a slide.');
  assertSame(mb_strlen($afterS) + 1, mb_strlen(Controller::widget('editor', 'markdown')->getValue()), 'Typing a inserts one character in the Markdown editor.');
  assertSame($beforeHotkeyFile, file_get_contents($path), 'Typing s does not save while the editor is active.');
  pressKey($window, $sdl, ord('s'), SDL::MOD_CTRL);
  assertSame($beforeHotkeyFile, file_get_contents($path), 'Ctrl+S does not save while a widget is active.');
  pressKey($window, $sdl, ord('o'), SDL::MOD_CTRL);
  assertSame('markdown', Controller::screen('editor')->activeLeaf()?->instance()->id(), 'Ctrl+O does not open the file browser while a widget is active.');
  FileActions::save($event);
  assertSame(false, Controller::$session->dirty, 'Explicit Save commits the edited Markdown.');
  $savedDocument = Controller::$session->document;
  $originalFocus = Controller::screen('editor')->selectedLeaf();
  FileActions::openScreen($event);
  Controller::widget('editor', 'browser')->setValue($path);
  $keys->key->key = SDL::KEY_ESCAPE;
  $window->handleEvent($keys);
  assertSame('editor', Controller::$screenId, 'Escape cancels the active file browser.');
  assertTrue(Controller::screen('editor')->selectedLeaf() === $originalFocus, 'Cancelling restores original editor focus.');
  assertTrue(Controller::$session->document === $savedDocument, 'Cancelling a browser does not accept its selected file.');
  FileActions::openScreen($event);
  pressKey($window, $sdl, SDL::KEY_F5);
  assertSame('path', Controller::screen('editor')->selectedLeaf()->instance()->id(), 'F5 activates the typed path field.');
  pressKey($window, $sdl, ord('n'));
  typeText($window, 'n');
  assertSame('n', Controller::widget('editor', 'path')->getValue(), 'Typing in the path field inserts its character.');
  assertTrue(Controller::widget('editor', 'browser') !== null, 'Typing in the browser does not trigger editor buttons.');
  Controller::widget('editor', 'path')->setValue($path . '.missing');
  pressKey($window, $sdl, SDL::KEY_F6);
  assertSame('error', Controller::widget('editor', 'status')->kind(), 'A failed open uses the error color.');
  assertTrue(Controller::$session->document === $savedDocument, 'A failed open retains the current document.');
  pressKey($window, $sdl, SDL::KEY_ESCAPE);
  FileActions::openScreen($event);
  Controller::widget('editor', 'browser')->setValue($path);
  $keys->key->key = SDL::KEY_RETURN;
  $window->handleEvent($keys);
  assertSame('editor', Controller::$screenId, 'Return on a selected file opens it through the real widget event.');
  assertTrue(Controller::screen('editor')->selectedLeaf() === $originalFocus, 'Accepting a file restores original editor focus.');
  assertTrue(Controller::$session->document !== $savedDocument, 'Accepted file creates a successfully loaded document.');
  $count = Controller::$session->document->count();
  Controller::widget('editor', 'markdown')->setValue("## Unsaved\n\nKeep me");
  Controller::apply($event);
  FileActions::create($event);
  assertTrue(Controller::widget('editor', 'status')->confirming(), 'New document asks before discarding unsaved source.');
  pressKey($window, $sdl, SDL::KEY_ESCAPE);
  assertSame($count, Controller::$session->document->count(), 'Cancel keeps the current presentation.');
  FileActions::openScreen($event);
  Controller::widget('editor', 'path')->setValue($path);
  FileActions::choose($event);
  pressKey($window, $sdl, ord('n'));
  assertSame($count, Controller::$session->document->count(), 'Saved presentation reloads with all slides.');
  assertSame(false, Controller::$session->dirty, 'Reloaded document is clean.');
  Controller::$session->index = 3;
  Controller::$session->theme = 'Default';
  Controller::sync(true);
  Controller::edit($event);
  if (($snapshotDirectory = getenv('MADEMO_SNAPSHOT_DIR')) !== false) {
    imagedestroy(snapshotWindow($window, $sdl, $snapshotDirectory . '/mademonstrator-editor.png'));
    Controller::present($event);
    $image = snapshotWindow($window, $sdl, $snapshotDirectory . '/mademonstrator-presentation.png');
    assertPresentationTitle($image);
    imagedestroy($image);
  } else {
    Controller::present($event);
    $image = snapshotWindow($window, $sdl);
    assertPresentationTitle($image);
    imagedestroy($image);
  }
  Controller::edit($event);
  Controller::$session->index = 2;
  Controller::sync(true);
  $slides = Controller::widget('editor', 'slides');
  $technologyIndex = array_search('Technology Stack', Controller::$session->document->slideTitles(), true);
  assertTrue(is_int($technologyIndex), 'Test presentation has a multiword slide title.');
  activateWidget('editor', 'slides', $sdl);
  pressKey($window, $sdl, ord('4'));
  typeText($window, '4');
  assertSame(null, $slides->getValue(), 'Typing a row number finds no slide title.');
  pressKey($window, $sdl, SDL::KEY_BACKSPACE);
  typeText($window, 'Technology');
  pressKey($window, $sdl, SDL::KEY_SPACE);
  typeText($window, ' ');
  typeText($window, 'Stack');
  assertSame('Technology Stack', $slides->filter(), 'Slide search keeps spaces and survives slide selection.');
  assertSame((string)$technologyIndex, $slides->getValue(), 'Slide search matches the title without its row number.');
  assertSame(2, Controller::$session->index, 'Searching highlights a slide without immediately loading it.');
  $slideTimers = array_values(array_filter((new ReflectionProperty($loop, 'timers'))->getValue($loop), fn(array $timer): bool => $timer['action'] === Controller::class . '::finishSlideSelection'));
  assertSame(60, $slideTimers[0]['period'], 'Search keeps the short settling delay.');
  Controller::finishSlideSelection(new EventContext('timer'));
  assertSame($technologyIndex, Controller::$session->index, 'Settled search loads its selected slide.');
  pressKey($window, $sdl, SDL::KEY_ESCAPE);
  activateWidget('editor', 'slides', $sdl);
  assertSame('', $slides->filter(), 'Starting cursor navigation clears the prior search.');
  pressKey($window, $sdl, SDL::KEY_DOWN);
  assertSame($technologyIndex, Controller::$session->index, 'The first cursor move waits for key repeat or release.');
  $slideTimers = array_values(array_filter((new ReflectionProperty($loop, 'timers'))->getValue($loop), fn(array $timer): bool => $timer['action'] === Controller::class . '::finishSlideSelection'));
  assertSame(600, $slideTimers[0]['period'], 'The first cursor move waits longer than a repeated move.');
  pressKey($window, $sdl, SDL::KEY_DOWN);
  $latestSlide = (int)$slides->getValue();
  assertSame($technologyIndex, Controller::$session->index, 'Rapid list movement keeps the current slide loaded.');
  $slideTimers = array_values(array_filter((new ReflectionProperty($loop, 'timers'))->getValue($loop), fn(array $timer): bool => $timer['action'] === Controller::class . '::finishSlideSelection'));
  assertSame(100, $slideTimers[0]['period'], 'Repeated movement uses the shorter settling delay.');
  $releasedDown = $sdl->ffi->new('SDL_Event');
  $releasedDown->type = SDL::SDL_EVENT_KEY_UP;
  $releasedDown->key->key = SDL::KEY_DOWN;
  $releasedDown->key->mod = 0;
  $window->handleEvent($releasedDown);
  assertSame($latestSlide, Controller::$session->index, 'Releasing the arrow loads only the latest selected slide.');
  Controller::finishSlideSelection(new EventContext('timer'));
  assertSame($latestSlide, Controller::$session->index, 'A stale timer callback leaves the loaded slide alone.');
  assertSame(implode("\n", Controller::$session->document->code($latestSlide)), Controller::widget('editor', 'markdown')->getValue(), 'Loading after key release updates the Markdown editor.');
  pressKey($window, $sdl, SDL::KEY_DOWN);
  $acceptedSlide = (int)$slides->getValue();
  assertSame($latestSlide, Controller::$session->index, 'Another cursor move is deferred.');
  pressKey($window, $sdl, SDL::KEY_ESCAPE);
  assertSame($acceptedSlide, Controller::$session->index, 'Leaving the slide list applies its pending selection.');
  activateWidget('editor', 'slides', $sdl);
  pressKey($window, $sdl, SDL::KEY_UP);
  $actionSlide = (int)$slides->getValue();
  assertSame($acceptedSlide, Controller::$session->index, 'Selection remains pending before an editor action.');
  Controller::commit();
  assertSame($actionSlide, Controller::$session->index, 'An editor action applies the pending slide before continuing.');
  Controller::screen('editor')->release();
  activateWidget('editor', 'markdown', $sdl);
  $typingSlide = Controller::$session->index;
  $typingText = Controller::widget('editor', 'markdown')->getValue();
  pressKey($window, $sdl, SDL::KEY_SPACE);
  typeText($window, ' ');
  assertSame($typingSlide, Controller::$session->index, 'Space edits Markdown instead of navigating while the editor is active.');
  assertTrue($typingText !== Controller::widget('editor', 'markdown')->getValue(), 'Active Markdown editor receives the typed space.');
  pressKey($window, $sdl, SDL::KEY_BACKSPACE);
  assertSame($typingSlide, Controller::$session->index, 'Backspace edits Markdown instead of navigating while the editor is active.');
  $loop->quitWindow($window->id());
  Controller::preview(new EventContext('timer'));
  assertSame([], $loop->windows(), 'Closing the audience window also cleans up the helper.');
  echo "Application integration checks passed\n";
} finally {
  unlink($path);
  $loop->closeWindows();
  $font->close();
  $ttf->close();
  $sdl->close();
}
