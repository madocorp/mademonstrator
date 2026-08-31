<?php

namespace MADEMO\App;

use SPTK\Core\Element;
use SPTK\Core\InputEvent;
use SPTK\Widgets\Dock;

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

  protected function shouldLayoutChild(Element $child): bool {
    return !isset($this->hidden[spl_object_id($child)]);
  }

  protected function shouldRenderChild(Element $child): bool {
    return !isset($this->hidden[spl_object_id($child)]);
  }

}
