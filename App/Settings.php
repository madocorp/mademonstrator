<?php

namespace MADEMO\App;

use SPTK\Core\AppData;
use SPTK\Events\EventContext;

/** Loads application preferences and connects the settings, help, and numbered-link controls. */
final class Settings {

  public static string $directory;
  private static string $theme = 'Default';
  private static array $config = [];

  /** Restore supported preferences and populate the settings screen. */
  public static function initialize(): void {
    $stored = AppData::loadJson('config.json');
    $config = array_replace(is_array($stored['config'] ?? null) ? $stored['config'] : [], array_diff_key($stored, ['config' => true]));
    self::$config = $config;
    WindowPreferences::initialize($config);
    self::$directory = (string)($config['defaultDir'] ?? getcwd());
    if (!is_dir(self::$directory)) {
      self::$directory = getcwd();
    }
    $theme = (string)($config['defaultStyle'] ?? 'Default');
    self::$theme = in_array($theme, SlideTheme::names(), true) ? $theme : 'Default';
    Controller::$session->theme = self::$theme;
    Controller::widget('settings', 'defaultTheme')->setItems(SlideTheme::names());
    Controller::widget('settings', 'defaultTheme')->setValue(self::$theme);
    Controller::widget('settings', 'directory')->setValue(self::$directory);
    Controller::widget('settings', 'presentationWindow')->setValue(WindowPreferences::$presentation);
    Controller::widget('settings', 'promptBox')->setValue(WindowPreferences::$helper);
    Controller::widget('settings', 'help')->setText("MARKDOWN REFERENCE\n\n" . file_get_contents(APP_DIR . '/Assets/cheatsheet.txt'));
    Controller::widget('settings', 'license')->setText("UNLICENSE\n\n" . file_get_contents(APP_DIR . '/UNLICENSE'));
  }

  /** Show settings and about information after committing the current slide. */
  public static function show(EventContext $event): bool {
    Controller::apply($event);
    Controller::show('settings');
    return true;
  }

  /** Validate and persist the selected default theme and file browser directory. */
  public static function save(EventContext $event): void {
    $directory = trim(Controller::widget('settings', 'directory')->getValue());
    $theme = Controller::widget('settings', 'defaultTheme')->getValue() ?? 'Default';
    if (!is_dir($directory) || !is_readable($directory)) {
      self::status('Choose a readable default directory.');
      return;
    }
    $presentation = trim(Controller::widget('settings', 'presentationWindow')->getValue());
    $helper = trim(Controller::widget('settings', 'promptBox')->getValue());
    try {
      WindowPreferences::parse($presentation);
      WindowPreferences::parse($helper, true);
    } catch (\InvalidArgumentException $error) {
      self::status($error->getMessage());
      return;
    }
    $directory = realpath($directory) ?: $directory;
    $config = array_replace(self::$config, ['defaultStyle' => $theme, 'defaultDir' => $directory, 'presentationWindow' => $presentation, 'promptBox' => $helper]);
    if (!AppData::saveJson('config.json', $config)) {
      self::status('Could not save settings.');
      return;
    }
    self::$config = $config;
    WindowPreferences::$presentation = $presentation;
    WindowPreferences::$helper = $helper;
    self::$directory = $directory;
    self::$theme = $theme;
    Controller::$session->theme = $theme;
    Controller::widget('editor', 'themes')->setValue($theme);
    Controller::sync();
    self::status('Settings saved.');
  }

  /** Open a numbered link through the desktop URL handler without invoking a command shell. */
  public static function openLink(int $index): bool {
    $links = Controller::$session->document->slide(Controller::$session->index)['links'];
    $url = $links[$index] ?? null;
    if ($url === null || !preg_match('/^(https?:\/\/|mailto:)/i', $url)) {
      return false;
    }
    $process = proc_open(['xdg-open', $url], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    if (is_resource($process)) {
      proc_close($process);
    }
    return true;
  }

  /** Refresh the settings status line. */
  private static function status(string $message): void {
    Controller::widget('settings', 'status')->notify($message);
    Controller::$window->resize();
  }

}
