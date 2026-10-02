<?php

namespace MADEMO\App;

use SPTK\Core\{Color, Widget};
use SPTK\Layout\{LayoutLeaf, LayoutNode};
use SPTK\Widgets\StyledText\Format;
use SPTK\Widgets\Empty\Placeholder;

/** Turns parsed Markdown into native nested tile layouts shared by presentation and editor preview. */
final class SlideLayout {

  private const BOX_MARGIN = '1';

  private LayoutNode $slide;
  private string $background;

  /** Retain the selected presentation theme. */
  public function __construct(private string $theme) {
    $this->background = SlideTheme::palette($theme)['bg'];
  }

  /** Build a complete slide using native text and image leaves, omitting absolute images. */
  public function build(array $data, string $width = '', string $height = '', bool $enterChildren = false): LayoutNode {
    $padding = SlideTheme::slidePadding($this->theme);
    $this->slide = new LayoutNode('horizontal', $width, $height, navigateChildren: !$enterChildren, enterChildren: $enterChildren);
    $this->slide->addLeaf($this->blank($padding['left'] . '%', '1*'));
    $column = new LayoutNode('vertical', '1*', '1*');
    $this->slide->addNode($column);
    $this->slide->addLeaf($this->blank($padding['right'] . '%', '1*'));
    $column->addLeaf($this->blank('1*', $padding['top'] . '%'));
    $content = new LayoutNode('vertical', '1*', '1*');
    $column->addNode($content);
    $column->addLeaf($this->blank('1*', $padding['bottom'] . '%'));
    $main = ($data['type'] ?? 'normal') === 'main';
    if ($main) {
      $content->addLeaf($this->blank('1*', '1*'));
    }
    $title = $this->text($data['title'] ?? [], $main ? 'main-title' : 'slide-title', $this->background);
    $content->addLeaf($this->leaf($title, '1*', 'auto'));
    $elements = array_values(array_filter($data['elements'] ?? [], fn(array $element): bool => ($element['position'] ?? '') !== 'absolute'));
    if (!$main) {
      $content->addLeaf($this->blank('1*', '1*'));
    }
    $body = new LayoutNode('vertical', '1*', $elements !== [] ? 'auto' : '1*');
    $this->elements($body, $elements, $this->background, false);
    if ($body->leaves() === []) {
      $body->addLeaf($this->blank('1*', '1*'));
    }
    $content->addNode($body);
    $content->addLeaf($this->blank('1*', $main ? '1*' : '2*'));
    return $this->slide;
  }

  /** Group adjacent heading boxes into rows using half, third, and quarter widths. */
  private function elements(LayoutNode $parent, array $elements, string $background, bool $insideBox): void {
    $boxes = [];
    $units = 0;
    foreach ($elements as $element) {
      if (($element['position'] ?? '') === 'absolute') {
        continue;
      }
      if (($element['type'] ?? 'text') === 'box') {
        $size = match ($element['size'] ?? 'half') {
          'third' => 4, 'quarter' => 3, default => 6,
        };
        if ($units + $size > 12) {
          $this->boxRow($parent, $boxes, $units);
          $boxes = [];
          $units = 0;
        }
        $boxes[] = [$element, $size];
        $units += $size;
      } else {
        if ($boxes !== []) {
          $this->boxRow($parent, $boxes, $units);
          $boxes = [];
          $units = 0;
        }
        $this->element($parent, $element, $background, $insideBox);
      }
    }
    if ($boxes !== []) {
      $this->boxRow($parent, $boxes, $units);
    }
  }

  /** Add a row of native box layouts, retaining unfilled width as a blank tile. */
  private function boxRow(LayoutNode $parent, array $boxes, int $units): void {
    $row = new LayoutNode('horizontal', '1*', 'auto');
    foreach ($boxes as [$box, $size]) {
      $style = SlideTheme::textStyle($this->theme, 'block', 100, 100);
      $background = ($style['background'] ?? 'transparent') === 'transparent' ? $this->background : $style['background'];
      $margin = new LayoutNode('horizontal', $size . '*', '1*');
      $margin->addLeaf($this->blank(self::BOX_MARGIN, '1*'));
      $column = new LayoutNode('vertical', '1*', '1*');
      $column->addLeaf($this->blank('1*', self::BOX_MARGIN));
      $column->addLeaf($this->leaf($this->text($box['title'], 'block-title', $background, true), '1*', 'auto'));
      $items = new LayoutNode('vertical', '1*', 'auto');
      $this->elements($items, $box['items'] ?? [], $background, true);
      if ($items->leaves() === []) {
        $items->addLeaf($this->blank('1*', '1*', $background));
      }
      $column->addNode($items);
      $column->addLeaf($this->blank('1*', '0*', $background));
      $column->addLeaf($this->blank('1*', self::BOX_MARGIN));
      $margin->addNode($column);
      $margin->addLeaf($this->blank(self::BOX_MARGIN, '1*'));
      $row->addNode($margin);
    }
    if ($units < 12) {
      $row->addLeaf($this->blank((12 - $units) . '*', '1*'));
    }
    $parent->addNode($row);
  }

  /** Add a text, image, or list element to its native vertical layout. */
  private function element(LayoutNode $parent, array $element, string $background, bool $insideBox): void {
    $weight = $this->weight($element) . '*';
    if (($element['type'] ?? 'text') === 'image') {
      $parent->addLeaf($this->leaf($this->image($element['src'], $background, $element['alt'] ?? ''), '1*', $weight));
    } else if (($element['type'] ?? 'text') === 'list') {
      $this->list($parent, $element, $background, $insideBox);
    } else {
      $parent->addLeaf($this->leaf($this->text($element['runs'] ?? [], $element['role'] ?? 'body', $background, $insideBox), '1*', 'auto'));
    }
  }

  /** Put the marker beside each item's text so wrapped lines keep their indent. */
  private function list(LayoutNode $parent, array $element, string $background, bool $insideBox): void {
    $list = new LayoutNode('vertical', '1*', 'auto');
    foreach ($element['items'] as $item) {
      $row = new LayoutNode('horizontal', '1*', 'auto', navigateChildren: false);
      $markerText = $item['marker'] ?? '*';
      $marker = ($item['bullet'] ?? null) === null
        ? $this->text([['text' => $markerText]], 'body', $background, $insideBox, true)
        : $this->image($item['bullet'], $background, '*');
      $row->addLeaf($this->leaf($marker, ($item['bullet'] ?? null) === null ? '' : '2', 'auto'));
      $row->addLeaf($this->leaf($this->text($item['runs'] ?? [], 'body', $background, $insideBox), '1*', 'auto'));
      $list->addNode($row);
    }
    $parent->addNode($list);
  }

  /** Create a themed text widget tied to the complete slide's measured grid. */
  private function text(array $runs, string $role, string $background, bool $insideBox = false, bool $fitWidth = false): SlideText {
    return new SlideText($runs, $role, $this->theme, $this->slide, $background, $insideBox, $fitWidth);
  }

  /** Create a noninteractive fitted image or an informative placeholder for missing local assets. */
  private function image(string $path, string $background, string $alt): Widget {
    if (!is_file($path)) {
      return $this->text([['text' => '[Missing image: ' . ($alt ?: basename($path)) . ']']], 'body', $background);
    }
    return new SlideImage($path, $this->color($background));
  }

  /** Estimate relative vertical space without implementing pixel positioning outside the tile system. */
  private function weight(array $element): int {
    if (($element['type'] ?? '') === 'image') {
      return 6;
    }
    if (($element['type'] ?? '') === 'box') {
      $weight = 2;
      foreach ($element['items'] ?? [] as $item) {
        $weight += $this->weight($item);
      }
      return $weight;
    }
    if (($element['type'] ?? '') === 'list') {
      return max(2, count($element['items']) * 2);
    }
    $text = '';
    foreach ($element['runs'] ?? [] as $run) {
      $text .= ($run['type'] ?? '') === 'br' ? "\n" : ($run['text'] ?? '');
    }
    return max(2, substr_count($text, "\n") + (int)ceil(mb_strlen($text) / 65) + 1);
  }

  /** Wrap one widget in a native tile. */
  private function leaf(Widget $widget, string $width, string $height, bool $navigate = true): LayoutLeaf {
    return new LayoutLeaf((new \ReflectionClass($widget))->getShortName(), $width, $height, $widget, navigate: $navigate);
  }

  /** Create an empty background tile for slide margins or unused space. */
  private function blank(string $width, string $height, ?string $background = null): LayoutLeaf {
    return $this->leaf(new Placeholder($this->color($background ?? $this->background)), $width, $height, false);
  }

  /** Resolve theme hexadecimal colors to toolkit RGB colors. */
  private function color(string $value): Color {
    [$r, $g, $b] = Format::color($value);
    return new Color($r, $g, $b);
  }

}
