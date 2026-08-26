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
    foreach ($this->slide['elements'] ?? [] as $element) {
      $items[] = ['element' => $element, 'width' => $content->width];
    }
    $layout = $this->stackLayout($target, $items, $content, true);
    foreach ($layout as $item) {
      $this->paintElement($target, $item['element'], $item['rect'], $surface);
    }
  }

  private function paintNormal(SurfaceRenderTarget&PixelTextRenderTarget $target, Rect $surface): void {
    $titleRect = $this->percentRect($surface, 7, 4, 86, 15);
    $titleBox = new StyledTextBox('slide-title', $this->styledRuns($this->slide['title'] ?? [], 'slide-title', $surface->height), SlideTheme::textStyle($this->themeName, 'slide-title', $surface->width, $surface->height));
    $titleBox->paintPixels($target, $titleRect);
    $body = $this->percentRect($surface, 7, 20, 86, 74);
    $layout = $this->bodyLayout($target, $this->slide['elements'] ?? [], $body, $surface);
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
    $y = $body->y + max(0, intdiv($body->height - $totalHeight, 2));
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
    $box = $this->textBoxForElement($element, $surface);
    return min(max(1, $box->contentHeight($target, $width)), max(1, (int)round($surface->height * 0.74)));
  }

  private function paintElement(SurfaceRenderTarget&PixelTextRenderTarget $target, array $element, Rect $rect, Rect $surface): void {
    if (($element['type'] ?? 'text') === 'image') {
      $this->paintImage($target, $element, $rect);
      return;
    }
    $this->textBoxForElement($element, $surface)->paintPixels($target, $rect);
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

  private function runsForElement(array $element, Rect $surface): array {
    $role = $element['role'] ?? 'body';
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
