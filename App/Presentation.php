<?php

namespace MADEMO\App;

class Presentation {

  protected $file;
  protected $slides = [];
  protected $changed = false;
  protected $trash = [];
  protected $slide = false;

  public function __construct(string $file) {
    $this->file = realpath($file);
    if (file_exists($this->file)) {
      $this->load();
    }
  }

  protected function load(): void {
    $md = file($this->file, FILE_IGNORE_NEW_LINES);
    $slide = false;
    $i = 0;
    foreach ($md as $line) {
      if (strpos($line, '# ') === 0 || strpos($line, '## ') === 0) {
        if ($slide !== false) {
          $this->slides[] = $slide;
        }
        $title = ltrim($line, '# ');
        if (empty($title)) {
          $title = "#{$i}";
        }
        $slide = [
          'title' => $title,
          'code' => []
        ];
        $i++;
      }
      if ($slide === false) {
        continue;
      }
      $slide['code'][] = $line;
    }
    if ($slide !== false) {
      $this->slides[] = $slide;
    }
  }

  public function setTarget(string $file): void {
    $this->file = $file;
  }

  public function changeSlide(int $i, string $code, bool $new = false): void {
    if ($new) {
      $i++;
      array_splice($this->slides, $i, 0, [['title' => 'new', 'code' => '']]);
    }
    if ($this->slides[$i]['code'] === $code) {
      return;
    }
    $title = "#{$i}";
    foreach ($code as $line) {
      if (preg_match("/^#{1,2}[^#].*/", $line)) {
        $title = ltrim($line, "# ");
      }
    }
    $this->slides[$i] = [
      'title' => $title,
      'code' => $code
    ];
    $this->changed = true;
  }

  public function save(string $path): void {
    $content = [];
    foreach ($this->slides as $slide) {
      foreach ($slide['code'] as $line) {
        if ($line === '---') {
          continue;
        }
        $content[] = $line;
      }
      $content[] = '';
      $content[] = '---';
      $content[] = '';
    }
    $content = implode("\n", $content);
    $content = preg_replace("/\n\n\n+/", "\n\n", $content);
    file_put_contents($path, $content);
  }

  public function getSlideList(): array {
    $list = [];
    foreach ($this->slides as $slide) {
      $list[] = $slide['title'];
    }
    return $list;
  }

  public function showSlide(int $i): int {
    if ($i < 0) {
      $i = 0;
    }
    $n = count($this->slides);
    if ($i >= $n) {
      $i = $n - 1;
    }
    $code = $this->slides[$i]['code'];
    $this->slide = new Slide($code);
    return $i;
  }

  public function getCode(int $i): string {
    if ($i < 0) {
      $i = 0;
    }
    $n = count($this->slides);
    if ($i >= $n) {
      $i = $n - 1;
    }
    return $this->slides[$i]['code'];
  }

  public function deleteSlide(int $i): void {
    $this->trash[] = [$i, $this->slides[$i]];
    array_splice($this->slides, $i, 1);
  }

  public function restoreSlide(): array|false {
    if (empty($this->trash)) {
      return false;
    }
    $trash = array_pop($this->trash);
    array_splice($this->slides, $trash[0], 0, [$trash[1]]);
    return $trash[0];
  }

  public function sort(array $keys): array {
    $ordered = [];
    foreach ($keys as $key) {
      $ordered[] = $this->slides[$key];
    }
    $this->slides = $ordered;
  }

  public function getLink(int $link): string|false  {
    return $this->slide->links[$link] ?? false;
  }

  public function getFile(): string {
    return $this->file;
  }

}
