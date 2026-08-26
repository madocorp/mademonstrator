<?php

namespace MADEMO\App;

final class Presentation {

  private ?string $file = null;
  private array $slides = [];
  private array $trash = [];
  private array $links = [];
  private string $promptTitle = '';
  private string $promptText = '';

  public function __construct(?string $file = null) {
    if ($file !== null) {
      $this->file = realpath($file) ?: null;
      if ($this->file === null || !is_file($this->file)) {
        throw new \RuntimeException("File not found ({$file}).");
      }
      $this->load();
    }
  }

  public function setTarget(string $file): void {
    $this->file = $file;
  }

  public function file(): ?string {
    return $this->file;
  }

  public function count(): int {
    return count($this->slides);
  }

  public function slideTitles(): array {
    return array_map(fn(array $slide): string => $slide['title'], $this->slides);
  }

  public function code(int $index): array {
    return $this->slides[$this->clamp($index)]['code'] ?? [];
  }

  public function show(int $index, SlideView $view, string $styleName = 'Default'): int {
    $index = $this->clamp($index);
    $slide = SlideMarkdown::fromMarkdown($this->code($index), $this->basePath());
    $view->setSlide($slide['slide'], $styleName);
    $this->links = $slide['links'];
    $this->promptTitle = $slide['promptTitle'];
    $this->promptText = $slide['promptText'];
    return $index;
  }

  public function promptTitle(): string {
    return $this->promptTitle;
  }

  public function promptText(): string {
    return $this->promptText;
  }

  public function link(int $index): string|false {
    return $this->links[$index] ?? false;
  }

  public function changeSlide(int $index, array $code, bool $insert = false): void {
    if ($insert) {
      $index++;
      array_splice($this->slides, $index, 0, [['title' => 'new', 'code' => []]]);
    }
    $this->slides[$index] = [
      'title' => $this->titleFromCode($code, $index),
      'code' => array_values($code),
    ];
  }

  public function deleteSlide(int $index): void {
    if (count($this->slides) <= 1) {
      return;
    }
    $index = $this->clamp($index);
    $this->trash[] = [$index, $this->slides[$index]];
    array_splice($this->slides, $index, 1);
  }

  public function restoreSlide(): int|false {
    if ($this->trash === []) {
      return false;
    }
    [$index, $slide] = array_pop($this->trash);
    array_splice($this->slides, $index, 0, [$slide]);
    return $index;
  }

  public function sort(array $keys): void {
    $ordered = [];
    foreach ($keys as $key) {
      if (isset($this->slides[(int)$key])) {
        $ordered[] = $this->slides[(int)$key];
      }
    }
    if ($ordered !== []) {
      $this->slides = $ordered;
    }
  }

  public function save(string $path): void {
    $content = [];
    foreach ($this->slides as $slide) {
      foreach ($slide['code'] as $line) {
        if ($line !== '---') {
          $content[] = $line;
        }
      }
      $content[] = '';
      $content[] = '---';
      $content[] = '';
    }
    file_put_contents($path, preg_replace("/\n\n\n+/", "\n\n", implode("\n", $content)));
    $this->file = $path;
  }

  private function load(): void {
    $lines = file($this->file, FILE_IGNORE_NEW_LINES);
    $slide = null;
    foreach ($lines === false ? [] : $lines as $line) {
      if (preg_match('/^#{1,2} /', $line)) {
        if ($slide !== null) {
          $this->slides[] = $slide;
        }
        $slide = ['title' => ltrim($line, '# ') ?: '#' . count($this->slides), 'code' => []];
      }
      if ($slide !== null) {
        $slide['code'][] = $line;
      }
    }
    if ($slide !== null) {
      $this->slides[] = $slide;
    }
    if ($this->slides === []) {
      $this->slides[] = ['title' => 'Untitled', 'code' => ['# Untitled']];
    }
  }

  private function basePath(): string {
    if ($this->file !== null) {
      return dirname($this->file);
    }
    return getcwd();
  }

  private function titleFromCode(array $code, int $index): string {
    foreach ($code as $line) {
      if (preg_match('/^#{1,2} ([^#].*)$/', $line, $match)) {
        return trim($match[1]);
      }
    }
    return '#' . $index;
  }

  private function clamp(int $index): int {
    $max = max(0, count($this->slides) - 1);
    return max(0, min($max, $index));
  }

}
