<?php

$fixture = sys_get_temp_dir() . '/mademo-settings-' . bin2hex(random_bytes(6));
mkdir($fixture);
foreach (['SPTK', 'App', 'Layout', 'Assets', 'Styles'] as $name) {
  symlink(dirname(__DIR__) . '/' . $name, $fixture . '/' . $name);
}
symlink(dirname(__DIR__) . '/UNLICENSE', $fixture . '/UNLICENSE');
define('APP_DIR', $fixture);
define('APP_PATH', APP_DIR . '/mademonstrator.php');
require_once APP_DIR . '/SPTK/App.php';
require_once APP_DIR . '/App/Autoload.php';
spl_autoload_register(['SPTK\\App', 'load']);
spl_autoload_register(['MADEMO\\App\\Autoload', 'load']);
require_once __DIR__ . '/Support.php';

use MADEMO\App\{Controller, Presenter, Settings, WindowPreferences};
use SPTK\App;
use SPTK\Core\{AppData, Window, WindowPlacement};
use SPTK\Events\{EventContext, EventLoop};
use SPTK\Rendering\Font;
use SPTK\SDLWrapper\{SDL, TTF};
use SPTK\XmlParser\XmlParser;
use function MADEMO\Tests\{assertSame, assertTrue};

$videoDriver = getenv('MADEMO_TEST_VIDEO_DRIVER') ?: 'dummy';
putenv('SDL_VIDEODRIVER=' . $videoDriver);
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
$configPath = AppData::file('config.json');
try {
  AppData::saveJson('config.json', ['config' => ['defaultStyle' => 'Default', 'defaultDir' => $fixture, 'presentationWindow' => 'max', 'promptBox' => 'none', 'browserCmd' => 'legacy-browser %url%']]);
  $xml = new XmlParser();
  $data = $xml->windows[0];
  $data['state'] = 'hidden';
  $window = new Window($data);
  $loop->registerWindow($window);
  Controller::initialize(new EventContext('init'));
  assertSame('max', WindowPreferences::$presentation, 'Legacy nested presentation setting loads.');
  assertSame('none', WindowPreferences::$helper, 'Legacy nested helper setting loads.');
  assertSame('max', Controller::widget('settings', 'presentationWindow')->getValue(), 'Presentation field shows legacy value.');
  assertSame('none', Controller::widget('settings', 'promptBox')->getValue(), 'Helper field shows legacy value.');
  assertTrue(str_contains(Controller::widget('settings', 'presentationWindow')->tip(), '160x80'), 'Presentation sizing help comes from the field tip.');
  assertTrue(str_contains(Controller::widget('settings', 'promptBox')->tip(), 'none to hide notes'), 'Helper visibility help comes from the field tip.');
  Controller::widget('settings', 'presentationWindow')->setValue('1280x720');
  Controller::widget('settings', 'promptBox')->setValue('640x480');
  Settings::save(new EventContext('activate'));
  $saved = AppData::loadJson('config.json');
  assertSame('1280x720', $saved['presentationWindow'], 'Presentation geometry is persisted.');
  assertSame('640x480', $saved['promptBox'], 'Helper geometry is persisted.');
  assertSame('legacy-browser %url%', $saved['browserCmd'], 'Unrelated legacy preferences survive saving.');
  Settings::initialize();
  assertSame('1280x720', WindowPreferences::$presentation, 'Presentation setting reloads after save.');
  assertSame('640x480', WindowPreferences::$helper, 'Helper setting reloads after save.');
  Controller::widget('settings', 'presentationWindow')->setValue('none');
  Settings::save(new EventContext('activate'));
  assertSame($saved, AppData::loadJson('config.json'), 'Invalid geometry leaves saved configuration intact.');
  assertSame('1280x720', WindowPreferences::$presentation, 'Invalid geometry leaves active preferences intact.');
  if ($videoDriver !== 'dummy') {
    $editorGeometry = WindowPlacement::capture($window);
    WindowPreferences::$presentation = 'full';
    WindowPreferences::$helper = 'none';
    Controller::present(new EventContext('activate'));
    assertSame('fullscreen', WindowPlacement::capture($window)['mode'], 'Native fullscreen presentation mode is applied.');
    WindowPreferences::$helper = 'full';
    Presenter::show(new EventContext('activate'));
    assertTrue(Presenter::window() !== null, 'Fullscreen helper opens successfully.');
    Presenter::close();
    WindowPreferences::$helper = 'max';
    Presenter::show(new EventContext('activate'));
    assertTrue(Presenter::window() !== null, 'Maximized helper mode opens successfully.');
    Controller::edit(new EventContext('activate'));
    assertSame($editorGeometry, WindowPlacement::capture($window), 'Native fullscreen exit restores editor geometry.');
  }
  echo "Window settings persistence checks passed\n";
} finally {
  $loop->closeWindows();
  $font->close();
  $ttf->close();
  $sdl->close();
  if (is_file($configPath)) {
    unlink($configPath);
  }
  rmdir(dirname($configPath));
  foreach (['SPTK', 'App', 'Layout', 'Assets', 'Styles', 'UNLICENSE'] as $name) {
    unlink($fixture . '/' . $name);
  }
  rmdir($fixture);
}
