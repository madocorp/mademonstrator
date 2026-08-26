<?php

namespace MADEMO\App;

final class Config {

  private static ?string $path = null;
  private static ?string $home = null;

  public static function defaults(): array {
    return [
      'defaultStyle' => 'Default',
      'defaultDir' => self::home(),
      'presentationWindow' => 'full',
      'promptBox' => 'none',
      'browserCmd' => 'firefox --new-tab %url%',
    ];
  }

  public static function home(): string {
    self::ensurePath();
    return self::$home;
  }

  public static function path(): string {
    self::ensurePath();
    return self::$path;
  }

  public static function filePath(string $name): string {
    return self::path() . '/' . ltrim($name, '/');
  }

  public static function load(): array {
    $file = self::filePath('config.json');
    if (!is_file($file)) {
      return ['config' => self::defaults()];
    }
    $json = file_get_contents($file);
    $data = $json === false ? [] : json_decode($json, true);
    $config = is_array($data) && isset($data['config']) && is_array($data['config']) ? $data['config'] : [];
    return ['config' => array_replace(self::defaults(), $config)];
  }

  public static function save(array $config): bool {
    $file = self::filePath('config.json');
    $json = json_encode(['config' => $config], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents($file, $json . "\n", LOCK_EX) !== false;
  }

  private static function ensurePath(): void {
    if (self::$path !== null && self::$home !== null) {
      return;
    }
    $home = getenv('HOME') ?: getenv('USERPROFILE') ?: getcwd();
    self::$home = realpath($home) ?: $home;
    $configHome = getenv('XDG_CONFIG_HOME');
    if ($configHome === false || $configHome === '') {
      $configHome = rtrim(self::$home, '/') . '/.config';
    }
    self::$path = rtrim($configHome, '/') . '/mademonstrator';
    if (!is_dir(self::$path)) {
      mkdir(self::$path, 0700, true);
    }
  }

}
