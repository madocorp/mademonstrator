<?php

namespace MADEMO\App;

/** Owns Markdown slide sources, file persistence, and reversible slide editing operations. */
final class Presentation {

  private ?string $file = null;
  private array $slides = [['title' => 'New presentation', 'code' => ['# New presentation']]];
  private array $trash = [];

  /** Load a Markdown file or create a one-slide untitled presentation. */
  public function __construct(?string $file = null) {
    if ($file !== null) {
      $source = @file_get_contents($file);
      if ($source === false) {
        throw new \RuntimeException('Cannot read presentation: ' . $file);
      }
      $this->file = realpath($file) ?: $file;
      $this->load($source);
    }
  }

  /** Return the current file path, or null for an unsaved presentation. */
  public function file(): ?string {
    return $this->file;
  }

  /** Return the number of slides. */
  public function count(): int {
    return count($this->slides);
  }

  /** Return slide titles in presentation order. */
  public function slideTitles(): array {
    return array_column($this->slides, 'title');
  }

  /** Use the first H1 anywhere in the presentation or the file's basename. */
  public function displayTitle(): string {
    foreach (array_keys($this->slides) as $index) {
      $title = $this->slide($index)['firstH1'];
      if ($title !== null) {
        return $title;
      }
    }
    return $this->file === null ? 'Untitled presentation' : basename($this->file);
  }

  /** Return source lines for a clamped slide index. */
  public function code(int $index): array {
    return $this->slides[$this->clamp($index)]['code'];
  }

  /** Parse one slide with image paths relative to its presentation file. */
  public function slide(int $index): array {
    return SlideMarkdown::fromMarkdown($this->code($index), $this->file === null ? getcwd() : dirname($this->file));
  }

  /** Replace one slide's source and refresh its title. */
  public function changeSlide(int $index, array $code): void {
    $index = $this->clamp($index);
    $this->slides[$index] = ['title' => $this->title($code, $index), 'code' => array_values($code)];
  }

  /** Insert a new slide after the current one and return its index. */
  public function insert(int $index, array $code): int {
    $index = $this->clamp($index) + 1;
    array_splice($this->slides, $index, 0, [['title' => $this->title($code, $index), 'code' => array_values($code)]]);
    return $index;
  }

  /** Delete a slide while retaining at least one slide and a restoration record. */
  public function delete(int $index): bool {
    if ($this->count() <= 1) {
      return false;
    }
    $index = $this->clamp($index);
    $this->trash[] = [$index, $this->slides[$index]];
    array_splice($this->slides, $index, 1);
    return true;
  }

  /** Restore the most recently deleted slide at its original position. */
  public function restore(): ?int {
    if ($this->trash === []) {
      return null;
    }
    [$index, $slide] = array_pop($this->trash);
    $index = min($index, $this->count());
    array_splice($this->slides, $index, 0, [$slide]);
    return $index;
  }

  /** Apply a complete permutation of the current slide indices. */
  public function reorder(array $order): void {
    $expected = array_map('strval', array_keys($this->slides));
    $sorted = $order;
    sort($sorted, SORT_NUMERIC);
    if ($sorted !== $expected) {
      throw new \InvalidArgumentException('Slide order must contain every current index exactly once.');
    }
    $this->slides = array_map(fn(string $index): array => $this->slides[(int)$index], $order);
  }

  /** Serialize slides without changing code-block whitespace or internal blank lines. */
  public function source(): string {
    $slides = [];
    foreach ($this->slides as $slide) {
      $slides[] = rtrim(implode("\n", $slide['code']), "\n");
    }
    return implode("\n\n---\n\n", $slides) . "\n";
  }

  /** Write a complete presentation and update its file path only after success. */
  public function save(string $path): void {
    $source = $this->source();
    if (@file_put_contents($path, $source, LOCK_EX) !== strlen($source)) {
      throw new \RuntimeException('Cannot save presentation: ' . $path);
    }
    $this->file = realpath($path) ?: $path;
  }

  /** Keep navigation within the existing slide range. */
  public function clamp(int $index): int {
    return max(0, min($this->count() - 1, $index));
  }

  /** Split on first- and second-level headings outside fenced code blocks. */
  private function load(string $source): void {
    $this->slides = [];
    $code = [];
    $fenced = false;
    $inComment = false;
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $source)) as $line) {
      $commentLine = $inComment;
      if (!$fenced && !$inComment && ($start = strpos($line, '<!--')) !== false) {
        $commentLine = true;
        $inComment = strpos($line, '-->', $start + 4) === false;
      } else if ($inComment) {
        $inComment = !str_contains($line, '-->');
      } else if (str_starts_with(trim($line), '```')) {
        $fenced = !$fenced;
      }
      $heading = !$fenced && !$commentLine && preg_match('/^#{1,2} /', $line);
      if ($heading && $code !== []) {
        $this->append($code);
        $code = [];
      }
      if ($code !== [] || $heading) {
        $code[] = $line;
      }
    }
    if ($code !== []) {
      $this->append($code);
    }
    if ($this->slides === []) {
      $this->slides[] = ['title' => 'Untitled', 'code' => ['## Untitled']];
    }
  }

  /** Append a loaded slide after removing only trailing slide separator lines. */
  private function append(array $code): void {
    while ($code !== [] && in_array(trim((string)end($code)), ['', '---'], true)) {
      array_pop($code);
    }
    $this->slides[] = ['title' => $this->title($code, $this->count()), 'code' => $code];
  }

  /** Find a slide title using the existing heading convention. */
  private function title(array $code, int $index): string {
    foreach ($code as $line) {
      if (preg_match('/^#{1,2} (.*)$/', $line, $match)) {
        return trim($match[1]);
      }
    }
    return 'Slide ' . ($index + 1);
  }

}
