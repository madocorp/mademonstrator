<?php

namespace MADEMO\App;

final class SlideTheme {

  private const PALETTES = [
    'Default' => ['bg' => '#050505', 'fg' => '#e8e8e8', 'title' => '#fff36a', 'accent' => '#75f0bd', 'muted' => '#101010', 'code' => '#79e9ff', 'strong' => '#ff8f8f', 'link' => '#6eb6ff', 'border' => '#2a2a2a'],
    'DarkAcademic' => ['bg' => '#101010', 'fg' => '#eeeeee', 'title' => '#f5d36b', 'accent' => '#c9a86a', 'muted' => '#191919', 'code' => '#9fd6ff', 'strong' => '#f19999', 'link' => '#80bfff', 'border' => '#3a3328'],
    'BrightAcademic' => ['bg' => '#f7f4ed', 'fg' => '#202020', 'title' => '#775400', 'accent' => '#856018', 'muted' => '#ebe4d5', 'code' => '#005f88', 'strong' => '#9d2727', 'link' => '#0059b2', 'border' => '#d2c6ab'],
    'DarkEsoteric' => ['bg' => '#0d0911', 'fg' => '#f2eaf9', 'title' => '#e0b0ff', 'accent' => '#9ee6d8', 'muted' => '#181020', 'code' => '#bcecff', 'strong' => '#ff9ab3', 'link' => '#9cc7ff', 'border' => '#39264c'],
    'BrightEsoteric' => ['bg' => '#fbf7ff', 'fg' => '#261d2d', 'title' => '#6d368f', 'accent' => '#147a6c', 'muted' => '#eee3f7', 'code' => '#006a8e', 'strong' => '#a12850', 'link' => '#295fb0', 'border' => '#d8c4e5'],
    'DarkFriendly' => ['bg' => '#101417', 'fg' => '#edf5f4', 'title' => '#ffd166', 'accent' => '#70e0b7', 'muted' => '#182024', 'code' => '#86dfff', 'strong' => '#ff9c8a', 'link' => '#88bdff', 'border' => '#294047'],
    'BrightFriendly' => ['bg' => '#f4fbf8', 'fg' => '#172421', 'title' => '#8a5a00', 'accent' => '#087d63', 'muted' => '#e4f2ed', 'code' => '#006a95', 'strong' => '#a43b2d', 'link' => '#1d66b3', 'border' => '#b9d7cf'],
    'DarkMinimal' => ['bg' => '#080808', 'fg' => '#eeeeee', 'title' => '#ffffff', 'accent' => '#cfcfcf', 'muted' => '#141414', 'code' => '#d8d8d8', 'strong' => '#ffffff', 'link' => '#a9c7ff', 'border' => '#303030'],
    'BrightMinimal' => ['bg' => '#ffffff', 'fg' => '#202020', 'title' => '#000000', 'accent' => '#555555', 'muted' => '#f1f1f1', 'code' => '#303030', 'strong' => '#000000', 'link' => '#225fa8', 'border' => '#d0d0d0'],
    'DarkTechnical' => ['bg' => '#02070a', 'fg' => '#d8f7ff', 'title' => '#64ffda', 'accent' => '#00d1ff', 'muted' => '#081217', 'code' => '#b7f7ff', 'strong' => '#ff7b72', 'link' => '#58a6ff', 'border' => '#12404c'],
    'BrightTechnical' => ['bg' => '#f3fbff', 'fg' => '#10242d', 'title' => '#006b5f', 'accent' => '#006f93', 'muted' => '#e2f1f6', 'code' => '#004f6b', 'strong' => '#a12f2f', 'link' => '#005fb8', 'border' => '#b7d5df'],
  ];

  public static function names(): array {
    $names = array_keys(self::PALETTES);
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);
    return $names;
  }

  public static function palette(string $name): array {
    return self::PALETTES[$name] ?? self::PALETTES['Default'];
  }

  public static function textStyle(string $name, string $role, int $width, int $height): array {
    $p = self::palette($name);
    $vh = fn(float $value): int => max(1, (int)round($height * $value / 100));
    $base = [
      'color' => $p['fg'],
      'background' => 'transparent',
      'borderWidth' => 0,
      'borderColor' => $p['border'],
      'padding' => 0,
      'fontFamily' => str_contains($name, 'Academic') || str_contains($name, 'Esoteric') ? 'serif' : (str_contains($name, 'Technical') ? 'monospace' : 'sans-serif'),
      'fontSize' => $vh(3),
      'textAlign' => 'left',
      'lineGap' => $vh(0.5),
    ];
    return match ($role) {
      'main-title' => array_replace($base, ['color' => $p['title'], 'fontSize' => $vh(7), 'fontWeight' => 'bold', 'textAlign' => 'center']),
      'slide-title' => array_replace($base, ['color' => $p['title'], 'fontSize' => $vh(4.8), 'fontWeight' => 'bold', 'textAlign' => 'center']),
      'block' => array_replace($base, ['background' => $p['muted'], 'borderWidth' => 1, 'padding' => $vh(1.4), 'fontSize' => $vh(2.7)]),
      'quote' => array_replace($base, ['color' => $p['accent'], 'background' => $p['muted'], 'borderWidth' => ['left' => 4], 'padding' => $vh(1.4)]),
      'code' => array_replace($base, ['color' => $p['code'], 'background' => $p['muted'], 'borderWidth' => ['left' => 4], 'padding' => $vh(1.3), 'fontFamily' => 'monospace', 'fontSize' => $vh(2.5)]),
      default => $base,
    };
  }

  public static function runStyle(string $name, string $role, int $height): array {
    $p = self::palette($name);
    $vh = fn(float $value): int => max(1, (int)round($height * $value / 100));
    return match ($role) {
      'strong' => ['bold' => true, 'color' => $p['strong']],
      'code' => ['fontFamily' => 'monospace', 'color' => $p['code'], 'background' => $p['muted'], 'fontSize' => $vh(2.5)],
      'link' => ['color' => $p['link']],
      'block-title' => ['bold' => true, 'color' => $p['accent'], 'fontSize' => $vh(3.4)],
      'subtitle' => ['bold' => true, 'color' => $p['title'], 'fontSize' => $vh(2.7)],
      default => [],
    };
  }

}
