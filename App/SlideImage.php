<?php

namespace MADEMO\App;

use SPTK\Core\{Color, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Layout\Tile;
use SPTK\Rendering\{GridWriter, PixelRenderer};
use SPTK\Widgets\Image\Image;

/** Uses SPTK's fitted image widget while keeping presentation colors independent of tile focus. */
final class SlideImage extends Widget {

  use WidgetEventEmitter;

  private Image $image;

  /** Configure a noninteractive image with the enclosing slide's background. */
  public function __construct(string $path, Color $background) {
    $this->image = new Image($path, bg: $background, fit: 'contain', interactive: false);
  }

  /** Use the slide's color for both letterboxing and layout gutters. */
  public function background(): Color {
    return $this->image->background();
  }

  /** Clear the text cells beneath the image. */
  public function paint(GridWriter $writer): void {
    $this->image->paint($writer);
  }

  /** Fit through the existing image renderer without dimming presentation assets. */
  public function paintPixels(PixelRenderer $renderer, Tile $area, bool $selected): void {
    $renderer->fill($area, $selected ? $this->background() : $this->background()->darkened());
    $this->image->paintPixels($renderer, $area, $selected);
  }

  /** Include the native tile's padding in its image and background area. */
  public function pixelPadding(): bool {
    return false;
  }

  /** Reserve enough natural height for a custom image bullet. */
  public function preferredHeight(): ?int {
    return 2;
  }

  /** Keep a list bullet square at the exact pixel width assigned by its row. */
  public function preferredPixelHeight(int $width): ?int {
    return max(1, $width);
  }

  /** Report image pixels for complete tile redraws. */
  public function paintsPixels(): bool {
    return true;
  }

  /** Keep presentation images out of input mode. */
  public function canActivate(): bool {
    return false;
  }

  /** Let the presentation screen handle all image tile input. */
  public function handleInput(mixed $event): bool {
    return false;
  }

}
