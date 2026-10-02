<?php

namespace MADEMO\App;

final class SlideTheme {

  private const DEFAULT_NAME = 'Default';

  private const SELECTORS = [
    'slide' => 'slide',
    'body' => 'body',
    'maintitle' => 'main-title',
    'main-title' => 'main-title',
    'slidetitle' => 'slide-title',
    'slide-title' => 'slide-title',
    'block' => 'block',
    'quote' => 'quote',
    'code' => 'code',
    'strong' => 'strong',
    'inlinecode' => 'inline-code',
    'inline-code' => 'inline-code',
    'link' => 'link',
    'blocktitle' => 'block-title',
    'block-title' => 'block-title',
    'subtitle' => 'subtitle',
  ];

  private const PROPERTIES = [
    'background-color' => 'background',
    'backgroundcolor' => 'background',
    'background' => 'background',
    'backgound-color' => 'background',
    'backgoundcolor' => 'background',
    'border-color' => 'borderColor',
    'bordercolor' => 'borderColor',
    'border-width' => 'borderWidth',
    'borderwidth' => 'borderWidth',
    'color' => 'color',
    'font-family' => 'fontFamily',
    'fontfamily' => 'fontFamily',
    'font-size' => 'fontSize',
    'fontsize' => 'fontSize',
    'font-style' => 'fontStyle',
    'fontstyle' => 'fontStyle',
    'font-weight' => 'fontWeight',
    'fontweight' => 'fontWeight',
    'line-gap' => 'lineGap',
    'linegap' => 'lineGap',
    'margin' => 'margin',
    'padding' => 'padding',
    'text-align' => 'textAlign',
    'textalign' => 'textAlign',
    'vertical-align' => 'verticalAlign',
    'verticalalign' => 'verticalAlign',
  ];

  private const FALLBACK = [
    'slide' => ['background' => '#050505', 'padding' => '5%'],
    'body' => ['color' => '#e8e8e8', 'background' => 'transparent', 'borderWidth' => 0, 'borderColor' => '#2a2a2a', 'padding' => 0, 'fontFamily' => 'sans-serif', 'fontSize' => '3vh', 'textAlign' => 'left', 'lineGap' => '0.5vh'],
    'main-title' => ['color' => '#fff36a', 'fontSize' => '7vh', 'fontWeight' => 'bold', 'textAlign' => 'center'],
    'slide-title' => ['color' => '#fff36a', 'fontSize' => '4.8vh', 'fontWeight' => 'bold', 'textAlign' => 'center'],
    'block' => ['background' => '#101010', 'borderWidth' => 1, 'borderColor' => '#2a2a2a', 'padding' => '1.4vh', 'margin' => ['top' => '0.8vh', 'right' => '0.6vw', 'bottom' => '0.8vh', 'left' => '0.6vw'], 'fontSize' => '2.7vh'],
    'quote' => ['color' => '#75f0bd', 'background' => '#101010', 'borderWidth' => ['left' => 4], 'padding' => '1.4vh'],
    'code' => ['color' => '#79e9ff', 'background' => '#101010', 'borderWidth' => ['left' => 4], 'padding' => '1.3vh', 'fontFamily' => 'monospace', 'fontSize' => '2.5vh'],
    'strong' => ['bold' => true, 'color' => '#ff8f8f'],
    'inline-code' => ['fontFamily' => 'monospace', 'color' => '#79e9ff', 'background' => '#101010', 'fontSize' => '2.5vh'],
    'link' => ['color' => '#6eb6ff'],
    'block-title' => ['bold' => true, 'color' => '#75f0bd', 'fontSize' => '3.4vh'],
    'subtitle' => ['bold' => true, 'color' => '#fff36a', 'fontSize' => '2.7vh'],
  ];

  private static array $themes = [];

  public static function names(): array {
    $names = [];
    foreach (glob(self::styleDir() . '/*.style') ?: [] as $file) {
      $names[] = basename($file, '.style');
    }
    if ($names === []) {
      $names[] = self::DEFAULT_NAME;
    }
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);
    return $names;
  }

  public static function palette(string $name): array {
    $theme = self::theme($name);
    return [
      'bg' => (string)($theme['slide']['background'] ?? '#050505'),
      'fg' => (string)($theme['body']['color'] ?? '#e8e8e8'),
    ];
  }

  /** Return slide edge sizes as percentages of the slide dimensions. */
  public static function slidePadding(string $name): array {
    $value = self::theme($name)['slide']['padding'];
    $sides = is_array($value) ? $value : array_fill_keys(['top', 'right', 'bottom', 'left'], $value);
    $padding = [];
    foreach (['top', 'right', 'bottom', 'left'] as $side) {
      $size = $sides[$side] ?? null;
      if (!is_string($size) || !preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)%$/', $size)) {
        throw new \InvalidArgumentException("Slide padding $side must be a percentage.");
      }
      $padding[$side] = (float)substr($size, 0, -1);
    }
    if ($padding['left'] + $padding['right'] >= 100 || $padding['top'] + $padding['bottom'] >= 100) {
      throw new \InvalidArgumentException('Opposite slide padding values must add up to less than 100%.');
    }
    return $padding;
  }

  public static function textStyle(string $name, string $role, int $width, int $height): array {
    $theme = self::theme($name);
    $style = array_replace($theme['body'] ?? [], $theme[$role] ?? []);
    return self::resolveStyle($style, $width, $height);
  }

  /** Keep viewport-based layout dimensions unresolved until the pixel slide is measured. */
  public static function rawStyle(string $name, string $role): array {
    $theme = self::theme($name);
    return array_replace($theme['body'] ?? [], $theme[$role] ?? []);
  }

  public static function runStyle(string $name, string $role, int $height): array {
    $role = $role === 'code' ? 'inline-code' : $role;
    $theme = self::theme($name);
    return self::resolveStyle($theme[$role] ?? [], 0, $height);
  }

  public static function parse(string $source): array {
    $source = preg_replace('/\/\*.*?\*\//s', '', $source) ?? $source;
    $theme = self::FALLBACK;
    if (!preg_match_all('/([A-Za-z][A-Za-z0-9_-]*)\s*\{(.*?)\}/s', $source, $blocks, PREG_SET_ORDER)) {
      return $theme;
    }
    foreach ($blocks as $block) {
      $selector = strtolower($block[1]);
      $role = self::SELECTORS[$selector] ?? null;
      if ($role === null) {
        continue;
      }
      foreach (explode(';', $block[2]) as $declaration) {
        if (!str_contains($declaration, ':')) {
          continue;
        }
        [$property, $value] = array_map('trim', explode(':', $declaration, 2));
        $property = self::PROPERTIES[strtolower($property)] ?? null;
        if ($property === null || $value === '') {
          continue;
        }
        $theme[$role][$property] = self::parseValue($property, $value);
      }
    }
    return $theme;
  }

  private static function theme(string $name): array {
    $name = basename($name) ?: self::DEFAULT_NAME;
    if (isset(self::$themes[$name])) {
      return self::$themes[$name];
    }
    $file = self::styleDir() . '/' . $name . '.style';
    if (!is_file($file)) {
      $file = self::styleDir() . '/' . self::DEFAULT_NAME . '.style';
    }
    $source = is_file($file) ? file_get_contents($file) : false;
    return self::$themes[$name] = $source === false ? self::FALLBACK : self::parse($source);
  }

  private static function parseValue(string $property, string $value): mixed {
    $value = trim($value);
    if (in_array($property, ['borderWidth', 'margin', 'padding'], true)) {
      return self::parseBoxValue($value);
    }
    if ($property === 'fontSize' || $property === 'lineGap') {
      return self::parseSize($value);
    }
    if ($property === 'fontFamily' && str_contains($value, ',')) {
      return array_values(array_filter(array_map('trim', explode(',', $value)), fn(string $family): bool => $family !== ''));
    }
    return $value;
  }

  private static function parseBoxValue(string $value): int|string|array {
    $parts = preg_split('/\s+/', trim($value)) ?: [];
    $parts = array_values(array_filter($parts, fn(string $part): bool => $part !== ''));
    if (count($parts) === 1) {
      return self::parseSize($parts[0]);
    }
    $values = array_map(fn(string $part): int|string => self::parseSize($part), $parts);
    return match (count($values)) {
      2 => ['top' => $values[0], 'right' => $values[1], 'bottom' => $values[0], 'left' => $values[1]],
      3 => ['top' => $values[0], 'right' => $values[1], 'bottom' => $values[2], 'left' => $values[1]],
      default => ['top' => $values[0] ?? 0, 'right' => $values[1] ?? 0, 'bottom' => $values[2] ?? 0, 'left' => $values[3] ?? 0],
    };
  }

  private static function parseSize(string $value): int|string {
    $value = trim($value);
    if (preg_match('/^-?\d+(?:\.\d+)?(?:vh|vw|px)$/i', $value)) {
      return strtolower($value);
    }
    if (is_numeric($value)) {
      return (int)round((float)$value);
    }
    return $value;
  }

  private static function resolveStyle(array $style, int $width, int $height): array {
    foreach (['fontSize', 'lineGap', 'margin', 'padding', 'borderWidth'] as $property) {
      if (array_key_exists($property, $style)) {
        $style[$property] = self::resolveValue($style[$property], $width, $height);
      }
    }
    if (($style['fontWeight'] ?? '') === 'bold') {
      $style['bold'] = true;
    }
    return $style;
  }

  private static function resolveValue(mixed $value, int $width, int $height): mixed {
    if (is_array($value)) {
      return array_map(fn(mixed $item): mixed => self::resolveValue($item, $width, $height), $value);
    }
    if (!is_string($value)) {
      return $value;
    }
    if (!preg_match('/^(-?\d+(?:\.\d+)?)(vh|vw|px)$/i', $value, $match)) {
      return $value;
    }
    $number = (float)$match[1];
    return match (strtolower($match[2])) {
      'vh' => max(1, (int)round($height * $number / 100)),
      'vw' => max(1, (int)round($width * $number / 100)),
      default => (int)round($number),
    };
  }

  private static function styleDir(): string {
    return dirname((string)(defined('APP_PATH') ? APP_PATH : dirname(__DIR__) . '/mademonstrator.php')) . '/Styles';
  }

}
