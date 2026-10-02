<?php

namespace MADEMO\App;

/** Tracks the current document, slide, theme, and unsaved changes for all screens. */
final class Session {

  public Presentation $document;
  public int $index = 0;
  public string $theme = 'Default';
  public bool $dirty = false;

  /** Start with a new presentation. */
  public function __construct() {
    $this->document = new Presentation();
  }

  /** Commit editor text only when it differs from the current slide's source. */
  public function commit(string $text): bool {
    $lines = explode("\n", $text);
    if ($lines === $this->document->code($this->index)) {
      return false;
    }
    $this->document->changeSlide($this->index, $lines);
    $this->dirty = true;
    return true;
  }

  /** Replace the document after a successful open or new action. */
  public function open(?string $path): void {
    $document = new Presentation($path);
    $this->document = $document;
    $this->index = 0;
    $this->dirty = $path === null;
  }

}
