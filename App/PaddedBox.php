<?php

namespace MADEMO\App;

use SPTK\Core\Element;
use SPTK\Core\RenderTarget;

final class PaddedBox extends Element {

  public function __construct(string $name, private Element $child, private int $padding = 1) {
    parent::__construct($name);
    $this->add($child);
    $this->setGridAlignment('top-left');
  }

  public function layout(): void {
    $padding = max(0, $this->padding);
    $this->child->setFrame($this->frame->inset($padding, $padding));
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
  }

}
