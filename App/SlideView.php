<?php

namespace MADEMO\App;

use SPTK2\Core\Color;
use SPTK2\Core\Element;
use SPTK2\Core\ImageRenderTarget;
use SPTK2\Core\PixelTextRenderTarget;
use SPTK2\Core\Rect;
use SPTK2\Core\RenderTarget;
use SPTK2\Core\SurfaceRenderTarget;
use SPTK2\Widgets\StyledTextBox;

final class SlideView extends Element {

  private array $slide = ['type' => 'normal', 'title' => [], 'elements' => []];
  private string $themeName = 'Default';

  public function setSlide(array $slide, string $themeName): static {
    $this->slide = $slide;
    $this->themeName = $themeName;
    $this->invalidateRender();
    return $this;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    if (!$target instanceof SurfaceRenderTarget || !$target instanceof PixelTextRenderTarget) {
      $text = $this->plainTitle();
      $target->write($this->frame->x, $this->frame->y, mb_substr($text, 0, $this->frame->width), $this->theme->fg, $this->theme->bg);
      return;
    }
    $surface = $target->currentSurfacePixelRect();
    $palette = SlideTheme::palette($this->themeName);
    $target->fillPixels($surface, $palette['bg']);
    $this->paintAbsoluteImages($target, $surface);
    if (($this->slide['type'] ?? 'normal') === 'main') {
      $this->paintMain($target, $surface);
    } else {
      $this->paintNormal($target, $surface);
    }
  }

  private function paintMain(SurfaceRenderTarget&PixelTextRenderTarget $target, Rect $surface): void {
    $content = $this->percentRect($surface, 7, 0, 86, 100);
    $items = [];
    $items[] = ['element' => ['type' => 'text', 'role' => 'main-title', 'runs' => $this->styledRuns($this->slide['title'] ?? [], 'main-title', $surface->height)], 'width' => $content->width];
    foreach ($this->normalElements() as $element) {
      $items[] = ['element' => $element, 'width' => $content->width];
    }
    $layout = $this->stackLayout($target, $items, $content, true);
    foreach ($layout as $item) {
      $this->paintElement($target, $item['element'], $item['rect'], $surface);
    }
  }

  private function paintNormal(SurfaceRenderTarget&PixelTextRenderTarget $target, Rect $surface): void {
    $titleSlot = $this->percentRect($surface, 7, 4, 86, 15);
    $titleBox = new StyledTextBox('slide-title', $this->styledRuns($this->slide['title'] ?? [], 'slide-title', $surface->height), SlideTheme::textStyle($this->themeName, 'slide-title', $surface->width, $surface->height));
    $titleHeight = min($titleSlot->height, $titleBox->contentHeight($target, $titleSlot->width));
    $titleRect = new Rect($titleSlot->x, $titleSlot->y, $titleSlot->width, max(1, $titleHeight));
    $titleBox->paintPixels($target, $titleRect);
    $body = $this->percentRect($surface, 7, 20, 86, 74);
    $layout = $this->bodyLayout($target, $this->normalElements(), $body, $surface);
    foreach ($layout as $item) {
      $this->paintElement($target, $item['element'], $item['rect'], $surface);
    }
  }

  private function bodyLayout(SurfaceRenderTarget&PixelTextRenderTarget $target, array $elements, Rect $body, Rect $surface): array {
    $rows = [];
    $current = [];
    $x = 0;
    $gap = max(8, (int)round($surface->width * 0.02));
    foreach ($elements as $element) {
      if (($element['type'] ?? 'text') !== 'box') {
        if ($current !== []) {
          $rows[] = $current;
          $current = [];
          $x = 0;
        }
        $rows[] = [['element' => $element, 'width' => $body->width]];
        continue;
      }
      $width = match ($element['size'] ?? 'half') {
        'third' => (int)round($body->width * 0.30),
        'quarter' => (int)round($body->width * 0.22),
        default => (int)round($body->width * 0.47),
      };
      if ($current !== [] && $x + $width > $body->width) {
        $rows[] = $current;
        $current = [];
        $x = 0;
      }
      $current[] = ['element' => $element, 'width' => $width];
      $x += $width + $gap;
    }
    if ($current !== []) {
      $rows[] = $current;
    }
    $layout = [];
    $totalHeight = 0;
    $rowGap = max(8, (int)round($surface->height * 0.025));
    foreach ($rows as $rowIndex => $row) {
      $rowHeight = 0;
      foreach ($row as $index => $item) {
        $row[$index]['height'] = $this->elementHeight($target, $item['element'], $item['width'], $surface);
        $rowHeight = max($rowHeight, $row[$index]['height']);
      }
      $rows[$rowIndex] = ['items' => $row, 'height' => $rowHeight];
      $totalHeight += $rowHeight;
    }
    $totalHeight += max(0, count($rows) - 1) * $rowGap;
    $y = $body->y + max(0, intdiv($body->height - $totalHeight, 4));
    foreach ($rows as $row) {
      $usedWidth = array_sum(array_map(fn(array $item): int => $item['width'], $row['items'])) + max(0, count($row['items']) - 1) * $gap;
      $x = $body->x + max(0, intdiv($body->width - $usedWidth, 2));
      foreach ($row['items'] as $item) {
        $layout[] = ['element' => $item['element'], 'rect' => new Rect($x, $y, $item['width'], $item['height'])];
        $x += $item['width'] + $gap;
      }
      $y += $row['height'] + $rowGap;
    }
    return $layout;
  }

  private function normalElements(): array {
    return array_values(array_filter($this->slide['elements'] ?? [], fn(array $element): bool => ($element['position'] ?? '') !== 'absolute'));
  }

  private function paintAbsoluteImages(SurfaceRenderTarget&PixelTextRenderTarget $target, Rect $surface): void {
    foreach ($this->slide['elements'] ?? [] as $element) {
      if (($element['type'] ?? '') !== 'image' || ($element['position'] ?? '') !== 'absolute') {
        continue;
      }
      $this->paintImage($target, $element, $this->absoluteRect($element['rect'] ?? [], $surface));
    }
  }

  private function absoluteRect(array $spec, Rect $surface): Rect {
    $x = $surface->x + $this->resolvePosition((string)($spec['x'] ?? '0'), $surface->width);
    $y = $surface->y + $this->resolvePosition((string)($spec['y'] ?? '0'), $surface->height);
    $width = $this->resolvePosition((string)($spec['width'] ?? '100%'), $surface->width);
    $height = $this->resolvePosition((string)($spec['height'] ?? '100%'), $surface->height);
    return new Rect($x, $y, max(1, $width), max(1, $height));
  }

  private function resolvePosition(string $value, int $total): int {
    $value = trim($value);
    if (str_ends_with($value, '%')) {
      return (int)round($total * ((float)substr($value, 0, -1)) / 100);
    }
    if (str_ends_with(strtolower($value), 'px')) {
      return (int)round((float)substr($value, 0, -2));
    }
    return (int)round((float)$value);
  }

  private function stackLayout(SurfaceRenderTarget&PixelTextRenderTarget $target, array $items, Rect $content, bool $center): array {
    $gap = max(8, (int)round($content->height * 0.035));
    $layout = [];
    $total = 0;
    foreach ($items as $index => $item) {
      $items[$index]['height'] = $this->elementHeight($target, $item['element'], $item['width'], $content);
      $total += $items[$index]['height'];
    }
    $total += max(0, count($items) - 1) * $gap;
    $y = $content->y + ($center ? max(0, intdiv($content->height - $total, 2)) : 0);
    foreach ($items as $item) {
      $layout[] = ['element' => $item['element'], 'rect' => new Rect($content->x, $y, $item['width'], $item['height'])];
      $y += $item['height'] + $gap;
    }
    return $layout;
  }

  private function elementHeight(SurfaceRenderTarget&PixelTextRenderTarget $target, array $element, int $width, Rect $surface): int {
    if (($element['type'] ?? 'text') === 'image') {
      return max(1, (int)round($surface->height * 0.42));
    }
    if (($element['type'] ?? 'text') === 'list') {
      return $this->listHeight($target, $element, $width, $surface);
    }
    if (($element['type'] ?? 'text') === 'box') {
      return $this->boxHeight($target, $element, $width, $surface);
    }
    $box = $this->textBoxForElement($element, $surface);
    return min(max(1, $box->contentHeight($target, $width)), max(1, (int)round($surface->height * 0.74)));
  }

  private function paintElement(SurfaceRenderTarget&PixelTextRenderTarget $target, array $element, Rect $rect, Rect $surface): void {
    if (($element['type'] ?? 'text') === 'image') {
      $this->paintImage($target, $element, $rect);
      return;
    }
    if (($element['type'] ?? 'text') === 'list') {
      $this->paintList($target, $element, $rect, $surface);
      return;
    }
    if (($element['type'] ?? 'text') === 'box') {
      $this->paintBox($target, $element, $rect, $surface);
      return;
    }
    $this->textBoxForElement($element, $surface)->paintPixels($target, $rect);
  }

  private function boxHeight(SurfaceRenderTarget&PixelTextRenderTarget $target, array $element, int $width, Rect $surface): int {
    $style = SlideTheme::textStyle($this->themeName, 'block', $surface->width, $surface->height);
    $border = $this->edges($style['borderWidth'] ?? 0);
    $padding = $this->edges($style['padding'] ?? 0);
    $contentWidth = max(1, $width - $border['left'] - $border['right'] - $padding['left'] - $padding['right']);
    $gap = max(4, (int)round($surface->height * 0.01));
    $height = 0;
    $title = new StyledTextBox('block-title', $this->styledRuns($element['title'] ?? [], 'block-title', $surface->height), $this->boxInnerStyle($style));
    $height += $title->contentHeight($target, $contentWidth);
    foreach ($element['items'] ?? [] as $item) {
      $height += $gap + $this->boxItemHeight($target, $item, $contentWidth, $surface, $style);
    }
    return $border['top'] + $padding['top'] + $height + $padding['bottom'] + $border['bottom'];
  }

  private function paintBox(SurfaceRenderTarget&PixelTextRenderTarget $target, array $element, Rect $rect, Rect $surface): void {
    $style = SlideTheme::textStyle($this->themeName, 'block', $surface->width, $surface->height);
    $border = $this->edges($style['borderWidth'] ?? 0);
    $padding = $this->edges($style['padding'] ?? 0);
    if (($style['background'] ?? 'transparent') !== 'transparent') {
      $target->fillPixels($rect, (string)$style['background']);
    }
    $this->paintBorder($target, $rect, $border, (string)($style['borderColor'] ?? 'transparent'));
    $content = $rect->inset($border['left'] + $padding['left'], $border['top'] + $padding['top'], $border['right'] + $padding['right'], $border['bottom'] + $padding['bottom']);
    if ($content->width <= 0 || $content->height <= 0) {
      return;
    }
    $gap = max(4, (int)round($surface->height * 0.01));
    $y = $content->y;
    $title = new StyledTextBox('block-title', $this->styledRuns($element['title'] ?? [], 'block-title', $surface->height), $this->boxInnerStyle($style));
    $titleHeight = $title->contentHeight($target, $content->width);
    $title->paintPixels($target, new Rect($content->x, $y, $content->width, $titleHeight));
    $y += $titleHeight + $gap;
    foreach ($element['items'] ?? [] as $item) {
      $height = $this->boxItemHeight($target, $item, $content->width, $surface, $style);
      if ($y + $height > $content->bottom()) {
        break;
      }
      $this->paintBoxItem($target, $item, new Rect($content->x, $y, $content->width, $height), $surface, $style);
      $y += $height + $gap;
    }
  }

  private function boxItemHeight(SurfaceRenderTarget&PixelTextRenderTarget $target, array $item, int $width, Rect $surface, array $boxStyle): int {
    if (($item['type'] ?? 'text') === 'image') {
      return $this->imageHeight($item, $width, $surface, 0.32);
    }
    if (($item['type'] ?? 'text') === 'list') {
      return $this->listHeight($target, $item, $width, $surface);
    }
    return $this->boxTextForItem($item, $surface, $boxStyle)->contentHeight($target, $width);
  }

  private function paintBoxItem(SurfaceRenderTarget&PixelTextRenderTarget $target, array $item, Rect $rect, Rect $surface, array $boxStyle): void {
    if (($item['type'] ?? 'text') === 'image') {
      $this->paintImage($target, $item, $rect);
      return;
    }
    if (($item['type'] ?? 'text') === 'list') {
      $this->paintList($target, $item, $rect, $surface);
      return;
    }
    $this->boxTextForItem($item, $surface, $boxStyle)->paintPixels($target, $rect);
  }

  private function listHeight(SurfaceRenderTarget&PixelTextRenderTarget $target, array $element, int $width, Rect $surface): int {
    $metrics = $this->listMetrics($target, $element, $width, $surface);
    $style = $metrics['style'];
    $itemGap = $metrics['itemGap'];
    $textWidth = $metrics['textWidth'];
    $height = 0;
    foreach ($element['items'] ?? [] as $index => $item) {
      $box = new StyledTextBox('list-item', $this->styledRuns($item['runs'] ?? [], 'body', $surface->height), $this->listItemStyle($style));
      $height += max($metrics['markerHeight'], $box->contentHeight($target, $textWidth));
      if ($index < count($element['items'] ?? []) - 1) {
        $height += $itemGap;
      }
    }
    return max(1, $height);
  }

  private function paintList(SurfaceRenderTarget&PixelTextRenderTarget $target, array $element, Rect $rect, Rect $surface): void {
    $metrics = $this->listMetrics($target, $element, $rect->width, $surface);
    $style = $metrics['style'];
    $itemStyle = $this->listItemStyle($style);
    $itemGap = $metrics['itemGap'];
    $textWidth = $metrics['textWidth'];
    $markerWidth = $metrics['markerWidth'];
    $x = $rect->x + ($metrics['alignCenter'] ? max(0, intdiv($rect->width - $metrics['width'], 2)) : 0);
    $y = $rect->y;
    foreach ($element['items'] ?? [] as $item) {
      $box = new StyledTextBox('list-item', $this->styledRuns($item['runs'] ?? [], 'body', $surface->height), $itemStyle);
      $itemHeight = max($metrics['markerHeight'], $box->contentHeight($target, $textWidth));
      $markerY = $y + max(0, intdiv($itemHeight - $metrics['markerHeight'], 2));
      if (($item['bullet'] ?? null) !== null) {
        $this->paintImage($target, ['type' => 'image', 'src' => (string)$item['bullet'], 'alt' => ''], new Rect($x, $markerY, $metrics['markerHeight'], $metrics['markerHeight']));
      } else {
        $marker = (string)($item['marker'] ?? '*');
        (new StyledTextBox('list-marker', $marker, $itemStyle))->paintPixels($target, new Rect($x, $markerY, $markerWidth, $metrics['markerHeight']));
      }
      $box->paintPixels($target, new Rect($x + $markerWidth + $metrics['gap'], $y, $textWidth, $itemHeight));
      $y += $itemHeight + $itemGap;
      if ($y >= $rect->bottom()) {
        break;
      }
    }
  }

  private function listMetrics(PixelTextRenderTarget $target, array $element, int $width, Rect $surface): array {
    $style = SlideTheme::textStyle($this->themeName, 'body', $surface->width, $surface->height);
    $fontSize = (int)($style['fontSize'] ?? 24);
    $markerHeight = max(8, (int)round($fontSize * 0.75));
    $gap = max(6, (int)round($markerHeight * 0.65));
    $itemGap = max(4, (int)round($surface->height * 0.008));
    $markerWidth = $markerHeight;
    $naturalWidth = 0;
    foreach ($element['items'] ?? [] as $item) {
      if (($item['bullet'] ?? null) === null) {
        [$measuredMarkerWidth, $measuredMarkerHeight] = $target->measureTextPixels((string)($item['marker'] ?? '*'), $this->fontOptions($style, []));
        $markerWidth = max($markerWidth, $measuredMarkerWidth);
        $markerHeight = max($markerHeight, $measuredMarkerHeight);
      }
      $naturalWidth = max($naturalWidth, $this->runsWidth($target, $this->styledRuns($item['runs'] ?? [], 'body', $surface->height), $style));
    }
    $listWidth = min($width, max(1, $markerWidth + $gap + $naturalWidth));
    $textWidth = max(1, $listWidth - $markerWidth - $gap);
    return [
      'style' => $style,
      'width' => $listWidth,
      'textWidth' => $textWidth,
      'markerWidth' => $markerWidth,
      'markerHeight' => $markerHeight,
      'gap' => $gap,
      'itemGap' => $itemGap,
      'alignCenter' => ($style['textAlign'] ?? 'left') === 'center',
    ];
  }

  private function listItemStyle(array $style): array {
    return array_replace($style, ['textAlign' => 'left']);
  }

  private function runsWidth(PixelTextRenderTarget $target, array $runs, array $baseStyle): int {
    $width = 0;
    foreach ($runs as $run) {
      if (($run['type'] ?? 'text') === 'br') {
        continue;
      }
      [$runWidth] = $target->measureTextPixels((string)($run['text'] ?? ''), $this->fontOptions($baseStyle, $run));
      $width += $runWidth;
    }
    return $width;
  }

  private function fontOptions(array $baseStyle, array $runStyle): array {
    $family = $runStyle['fontFamily'] ?? $baseStyle['fontFamily'] ?? 'sans-serif';
    return [
      'family' => $family,
      'families' => is_array($family) ? $family : [$family],
      'size' => (int)($runStyle['fontSize'] ?? $baseStyle['fontSize'] ?? 24),
      'style' => (($runStyle['italic'] ?? false) || ($runStyle['fontStyle'] ?? $baseStyle['fontStyle'] ?? 'normal') === 'italic') ? 'italic' : 'normal',
      'weight' => (($runStyle['bold'] ?? false) || ($runStyle['fontWeight'] ?? $baseStyle['fontWeight'] ?? 'normal') === 'bold') ? 'bold' : 'normal',
    ];
  }

  private function textBoxForElement(array $element, Rect $surface): StyledTextBox {
    if (($element['type'] ?? 'text') === 'box') {
      $runs = $this->styledRuns($element['title'] ?? [], 'block-title', $surface->height);
      $runs[] = ['type' => 'br'];
      foreach ($element['items'] ?? [] as $index => $item) {
        if ($index > 0) {
          $runs[] = ['type' => 'br'];
        }
        array_push($runs, ...$this->runsForElement($item, $surface));
      }
      return new StyledTextBox('block', $runs, SlideTheme::textStyle($this->themeName, 'block', $surface->width, $surface->height));
    }
    $role = $element['role'] ?? 'body';
    return new StyledTextBox($role, $this->runsForElement($element, $surface), SlideTheme::textStyle($this->themeName, $role, $surface->width, $surface->height));
  }

  private function boxTextForItem(array $element, Rect $surface, array $boxStyle): StyledTextBox {
    $role = $element['role'] ?? 'body';
    $style = $role === 'body' || $role === 'subtitle' ? $this->boxInnerStyle($boxStyle) : SlideTheme::textStyle($this->themeName, $role, $surface->width, $surface->height);
    return new StyledTextBox($role, $this->runsForElement($element, $surface), $style);
  }

  private function boxInnerStyle(array $boxStyle): array {
    return array_replace($boxStyle, [
      'background' => 'transparent',
      'borderWidth' => 0,
      'padding' => 0,
    ]);
  }

  private function runsForElement(array $element, Rect $surface): array {
    $role = $element['role'] ?? 'body';
    if ($role === 'code') {
      return $element['runs'] ?? [];
    }
    return $this->styledRuns($element['runs'] ?? [], $role, $surface->height);
  }

  private function styledRuns(array $runs, string $fallbackRole, int $height): array {
    $styled = [];
    foreach ($runs as $run) {
      if (($run['type'] ?? 'text') === 'br') {
        $styled[] = $run;
        continue;
      }
      $role = $run['role'] ?? $fallbackRole;
      $styled[] = array_replace($run, SlideTheme::runStyle($this->themeName, $role, $height));
    }
    return $styled;
  }

  private function paintImage(SurfaceRenderTarget&PixelTextRenderTarget $target, array $element, Rect $rect): void {
    if (!$target instanceof ImageRenderTarget || !is_file((string)($element['src'] ?? ''))) {
      (new StyledTextBox('missing-image', [['text' => (string)($element['alt'] ?? 'Image')]], ['color' => '#ffffff']))->paintPixels($target, $rect);
      return;
    }
    $data = file_get_contents((string)$element['src']);
    $image = $data === false || !function_exists('imagecreatefromstring') ? false : @imagecreatefromstring($data);
    if (!$image instanceof \GdImage) {
      return;
    }
    $source = new Rect(0, 0, imagesx($image), imagesy($image));
    $scale = min($rect->width / max(1, $source->width), $rect->height / max(1, $source->height));
    $width = max(1, (int)round($source->width * $scale));
    $height = max(1, (int)round($source->height * $scale));
    $target->drawImagePixels($image, $source, new Rect($rect->x + intdiv($rect->width - $width, 2), $rect->y + intdiv($rect->height - $height, 2), $width, $height));
  }

  private function imageHeight(array $element, int $width, Rect $surface, float $maxSurfaceRatio): int {
    $path = (string)($element['src'] ?? '');
    if ($path !== '' && is_file($path) && function_exists('getimagesize')) {
      $size = @getimagesize($path);
      if (is_array($size) && ($size[0] ?? 0) > 0 && ($size[1] ?? 0) > 0) {
        $height = (int)round($width * ((int)$size[1] / max(1, (int)$size[0])));
        return max(1, min($height, (int)round($surface->height * $maxSurfaceRatio)));
      }
    }
    return max(1, (int)round($surface->height * $maxSurfaceRatio));
  }

  private function edges(int|array|string $value): array {
    if (!is_array($value)) {
      $value = (int)$value;
      return ['top' => $value, 'right' => $value, 'bottom' => $value, 'left' => $value];
    }
    return [
      'top' => (int)($value['top'] ?? 0),
      'right' => (int)($value['right'] ?? 0),
      'bottom' => (int)($value['bottom'] ?? 0),
      'left' => (int)($value['left'] ?? 0),
    ];
  }

  private function paintBorder(SurfaceRenderTarget $target, Rect $box, array $border, string $color): void {
    if ($color === 'transparent') {
      return;
    }
    if ($border['top'] > 0) {
      $target->fillPixels(new Rect($box->x, $box->y, $box->width, $border['top']), $color);
    }
    if ($border['bottom'] > 0) {
      $target->fillPixels(new Rect($box->x, $box->bottom() - $border['bottom'], $box->width, $border['bottom']), $color);
    }
    if ($border['left'] > 0) {
      $target->fillPixels(new Rect($box->x, $box->y, $border['left'], $box->height), $color);
    }
    if ($border['right'] > 0) {
      $target->fillPixels(new Rect($box->right() - $border['right'], $box->y, $border['right'], $box->height), $color);
    }
  }

  private function percentRect(Rect $surface, float $x, float $y, float $width, float $height): Rect {
    return new Rect(
      $surface->x + (int)round($surface->width * $x / 100),
      $surface->y + (int)round($surface->height * $y / 100),
      (int)round($surface->width * $width / 100),
      (int)round($surface->height * $height / 100)
    );
  }

  private function plainTitle(): string {
    return implode('', array_map(fn(array $run): string => (string)($run['text'] ?? ''), $this->slide['title'] ?? []));
  }

}
