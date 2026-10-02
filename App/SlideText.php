<?php

namespace MADEMO\App;

use SPTK\Core\{Color, Style};
use SPTK\Layout\LayoutNode;
use SPTK\Layout\PixelBox;
use SPTK\Widgets\StyledText\{Fonts, Format, StyledText};

/** Resolves a text tile's theme sizes against its enclosing slide rather than the text tile. */
final class SlideText extends StyledText {

  /** Retain Markdown runs, their semantic role, and the slide's native layout reference. */
  public function __construct(private array $sourceRuns, private string $role, private string $theme, private LayoutNode $slide, private string $backgroundColor, private bool $insideBox = false, private bool $fitWidth = false, private bool $listItem = false) {
    [$r, $g, $b] = Format::color($backgroundColor);
    parent::__construct(style: new Style(background: new Color($r, $g, $b)));
  }

  /** Return the original semantic Markdown runs used to resolve themed typography. */
  public function sourceRuns(): array {
    return $this->sourceRuns;
  }

  /** Put text decoration on its layout leaf; a heading box owns its shared inset. */
  public function pixelBox(): PixelBox {
    $style = SlideTheme::rawStyle($this->theme, $this->role);
    if ($this->insideBox && in_array($this->role, ['body', 'subtitle', 'block-title'], true)) {
      return new PixelBox(background: Color::from($this->backgroundColor));
    }
    $background = ($style['background'] ?? 'transparent') === 'transparent' ? $this->backgroundColor : $style['background'];
    return new PixelBox(
      $style['margin'] ?? 0,
      $style['borderWidth'] ?? 0,
      $style['padding'] ?? 0,
      Color::from($background),
      Color::from($style['borderColor'] ?? '#2a2a2a'),
    );
  }

  /** Reserve enough grid columns for a proportional-font list marker. */
  public function preferredWidth(): ?int {
    if (!$this->fitWidth) {
      return null;
    }
    [, $style, $referenceWidth, $referenceHeight] = $this->content(0, 0);
    $fonts = new Fonts();
    $face = $fonts->face($style, $referenceWidth, $referenceHeight);
    $ink = $fonts->measure($this->sourceRuns[0]['text'] ?? '', $face)[0];
    $margin = Format::edges($style['margin'], $referenceWidth, $referenceHeight);
    $padding = Format::edges($style['padding'], $referenceWidth, $referenceHeight);
    $border = Format::edges($style['borderWidth'], $referenceWidth, $referenceHeight);
    $pixels = $ink + $margin['left'] + $margin['right'] + $padding['left'] + $padding['right'] + $border['left'] + $border['right'] + 2;
    return max(1, (int)ceil($pixels / (\SPTK\App::fontOrNull()?->cellWidth() ?? 8)));
  }

  /** Size list markers to their exact ink width in a pixel layout. */
  public function preferredPixelWidth(): ?int {
    if (!$this->fitWidth) {
      return null;
    }
    [, $style, $referenceWidth, $referenceHeight] = $this->content(0, 0);
    $face = (new Fonts())->face($style, $referenceWidth, $referenceHeight);
    return max(1, (new Fonts())->measure($this->sourceRuns[0]['text'] ?? '', $face)[0] + 2);
  }

  /** Resolve viewport units and inline styles using the measured slide grid. */
  protected function content(int $width, int $height): array {
    $font = \SPTK\App::fontOrNull();
    $referenceWidth = max(1, $this->slide->pixelTile()?->width ?? $this->slide->grid()->width * ($font?->cellWidth() ?? 8));
    $referenceHeight = max(1, $this->slide->pixelTile()?->height ?? $this->slide->grid()->height * ($font?->cellHeight() ?? 16));
    $style = SlideTheme::textStyle($this->theme, $this->role, $referenceWidth, $referenceHeight);
    if ($this->insideBox && in_array($this->role, ['body', 'subtitle'], true)) {
      $style = $this->role === 'body' ? SlideTheme::textStyle($this->theme, 'block', $referenceWidth, $referenceHeight) : $style;
    }
    if ($this->insideBox && in_array($this->role, ['subtitle', 'block-title'], true)) {
      $style['padding'] = SlideTheme::textStyle($this->theme, 'block', $referenceWidth, $referenceHeight)['padding'];
    }
    if (($style['background'] ?? 'transparent') === 'transparent') {
      $style['background'] = $this->backgroundColor;
    }
    if ($this->insideBox && in_array($this->role, ['body', 'subtitle', 'block-title'], true)) {
      $style['margin'] = 0;
      $style['borderWidth'] = 0;
    }
    if ($this->insideBox && $this->listItem) {
      $padding = Format::edges($style['padding'], $referenceWidth, $referenceHeight);
      $style['padding'] = ['top' => 0, 'right' => $padding['right'], 'bottom' => 0, 'left' => $padding['left']];
    }
    if ($this->listItem && $style['textAlign'] === 'center') {
      $style['textAlign'] = 'left';
    }
    if ($this->externalBoxModel()) {
      $style['margin'] = 0;
      $style['padding'] = 0;
      $style['borderWidth'] = 0;
    }
    $style['verticalAlign'] ??= in_array($this->role, ['main-title', 'slide-title'], true) ? 'center' : 'top';
    $runs = [];
    foreach ($this->sourceRuns as $run) {
      $role = $run['role'] ?? null;
      $blockRole = $run['blockRole'] ?? null;
      unset($run['role'], $run['blockRole']);
      if ($blockRole !== null) {
        $blockStyle = SlideTheme::textStyle($this->theme, $blockRole, $referenceWidth, $referenceHeight);
        $run = array_replace($run, array_intersect_key($blockStyle, array_flip(['color', 'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'bold', 'italic'])));
      }
      if (($run['type'] ?? '') !== 'br' && $role !== null) {
        $run = array_replace($run, SlideTheme::runStyle($this->theme, $role, $referenceHeight));
      }
      $runs[] = $run;
    }
    return [Format::runs($runs), array_replace(Format::DEFAULTS, Format::style($style)), $referenceWidth, $referenceHeight];
  }

}
