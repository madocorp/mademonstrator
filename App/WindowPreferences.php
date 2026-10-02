<?php

namespace MADEMO\App;

use SPTK\App;
use SPTK\Core\WindowPlacement;

/** Validates saved presentation/helper geometry and restores the editor after presenting. */
final class WindowPreferences {

  public static string $presentation = 'full';
  public static string $helper = '78x36';
  private static ?array $editor = null;

  /** Restore both geometry settings, accepting the predecessor's configuration keys. */
  public static function initialize(array $config): void {
    self::$presentation = self::loaded((string)($config['presentationWindow'] ?? 'full'), false, 'full');
    self::$helper = self::loaded((string)($config['promptBox'] ?? '78x36'), true, '78x36');
  }

  /** Parse modes or dimensions, retaining legacy small dimensions expressed in grid cells. */
  public static function parse(string $value, bool $helper = false): array {
    $value = strtolower(trim($value));
    $mode = match ($value) {
      'full', 'fullscreen' => 'fullscreen',
      'max', 'maximized' => 'maximized',
      'normal', 'windowed' => 'normal',
      'none' => $helper ? 'none' : null,
      default => null,
    };
    if ($mode !== null) {
      return ['mode' => $mode];
    }
    if (preg_match('/^([1-9][0-9]{0,4})\s*x\s*([1-9][0-9]{0,4})$/', $value, $match)) {
      $width = (int)$match[1];
      $height = (int)$match[2];
      return ['mode' => 'normal', 'width' => $width, 'height' => $height, 'cells' => $width <= 160 && $height <= 80];
    }
    throw new \InvalidArgumentException($helper ? 'Helper window: use none, full, max, normal, or WIDTHxHEIGHT.' : 'Presentation window: use full, max, normal, or WIDTHxHEIGHT.');
  }

  /** Convert legacy cell geometry using the current application's actual font metrics. */
  public static function options(string $value, bool $helper = false): array {
    $options = self::parse($value, $helper);
    if ($options['cells'] ?? false) {
      $options['width'] = max(24, $options['width']) * App::font()->cellWidth();
      $options['height'] = max(8, $options['height']) * App::font()->cellHeight();
    }
    unset($options['cells']);
    return $options;
  }

  /** Remember editor geometry and apply the chosen audience window geometry. */
  public static function present(): void {
    self::$editor = WindowPlacement::capture(Controller::$window);
    WindowPlacement::apply(Controller::$window, self::options(self::$presentation));
  }

  /** Restore the editor's pre-presentation mode and size. */
  public static function restore(): void {
    if (self::$editor !== null) {
      WindowPlacement::apply(Controller::$window, self::$editor);
      self::$editor = null;
    }
  }

  /** Recover an invalid stored value without changing its saved file. */
  private static function loaded(string $value, bool $helper, string $fallback): string {
    try {
      self::parse($value, $helper);
      return trim($value);
    } catch (\InvalidArgumentException $error) {
      return $fallback;
    }
  }

}
