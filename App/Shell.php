<?php

namespace MADEMO\App;

use SPTK2\Core\Element;
use SPTK2\Core\InputEvent;
use SPTK2\Core\Rect;
use SPTK2\Core\RenderTarget;
use SPTK2\Core\SurfaceRenderTarget;
use SPTK2\Widgets\Dock;

final class Shell extends Dock {

  private array $hidden = [];

  public function __construct(string $name, private $shortcutHandler) {
    parent::__construct($name);
    $this->focusable = true;
  }

  public function setElementVisible(Element $element, bool $visible): void {
    if ($visible) {
      unset($this->hidden[spl_object_id($element)]);
    } else {
      $this->hidden[spl_object_id($element)] = true;
    }
    $this->invalidateLayout();
  }

  protected function handleShortcut(InputEvent $event): bool {
    return ($this->shortcutHandler)($event);
  }

  public function shortcut(InputEvent $event): bool {
    if ($this->handleShortcut($event)) {
      return true;
    }
    foreach ($this->children as $child) {
      if (!isset($this->hidden[spl_object_id($child)]) && $child->shortcut($event)) {
        return true;
      }
    }
    return false;
  }

  public function layout(): void {
    $remaining = $this->frame;
    $this->separators = [];
    foreach ($this->children as $child) {
      if (isset($this->hidden[spl_object_id($child)])) {
        $child->setFrame(new Rect($this->frame->x, $this->frame->y, 0, 0));
        continue;
      }
      if ($child->isAbsolute()) {
        if ($child instanceof \SPTK2\Widgets\DialogLayer) {
          $child->setFrame($this->frame);
        }
        continue;
      }
      $placement = $this->placements[spl_object_id($child)] ?? ['mode' => 'fill', 'options' => []];
      if ($placement['mode'] === 'dock') {
        [$childFrame, $separatorFrame, $remaining] = $this->dockCellFrames($remaining, $placement['edge'], $placement['options']);
        if ($separatorFrame !== null) {
          $this->separators[] = ['frame' => $separatorFrame, 'orientation' => $this->separatorOrientation($placement['edge'])];
        }
        $child->setFrame($childFrame);
      } else if ($placement['mode'] === 'grid') {
        $child->setFrame($this->gridFrame($placement, $remaining));
      } else if ($placement['mode'] === 'pixels') {
        $child->setFrame($this->pixelFallbackFrame($placement, $remaining));
      } else {
        $child->setFrame($remaining);
      }
    }
  }

  public function render(RenderTarget $target): void {
    if ($target instanceof SurfaceRenderTarget) {
      $remaining = $target->currentSurfacePixelRect();
      $pixelSeparators = [];
      foreach ($this->children as $child) {
        if (isset($this->hidden[spl_object_id($child)])) {
          continue;
        }
        if ($child->isAbsolute()) {
          continue;
        }
        $placement = $this->placements[spl_object_id($child)] ?? ['mode' => 'fill', 'options' => []];
        if ($placement['mode'] === 'dock') {
          [$pixelFrame, $separatorFrame, $remaining] = $this->dockPixelFrames($target, $remaining, $placement['edge'], $placement['options']);
          if ($separatorFrame !== null) {
            $pixelSeparators[] = ['frame' => $separatorFrame, 'orientation' => $this->separatorOrientation($placement['edge'])];
          }
        } else if ($placement['mode'] === 'grid') {
          $pixelFrame = $this->gridPixelFrame($target, $remaining, $placement);
        } else if ($placement['mode'] === 'pixels') {
          $pixelFrame = $this->absolutePixelFrame($target, $remaining, $placement);
        } else {
          $pixelFrame = $remaining;
        }
        $this->renderChildSurface($target, $child, $pixelFrame);
      }
      $this->paintPixelSeparators($target, $pixelSeparators);
      foreach ($this->children as $child) {
        if (!isset($this->hidden[spl_object_id($child)]) && $child->isAbsolute()) {
          $child->render($target);
        }
      }
      return;
    }
    $target->pushClip($this->frame);
    $this->paint($target);
    foreach ($this->children as $child) {
      if (!isset($this->hidden[spl_object_id($child)])) {
        $child->render($target);
      }
    }
    $target->popClip();
  }

}
