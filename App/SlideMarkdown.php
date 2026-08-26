<?php

namespace MADEMO\App;

final class SlideMarkdown {

  public static function fromMarkdown(array $lines, string $basePath): array {
    $elements = [];
    $title = [];
    $titleLevel = 2;
    $links = [];
    $paragraph = [];
    $list = null;
    $quote = [];
    $code = [];
    $box = null;
    $inCode = false;
    $inComment = false;
    $comment = [];
    $promptTitle = '';
    $promptLines = [];
    $bulletImage = null;

    $append = function(array $element) use (&$elements, &$box): void {
      if ($box !== null) {
        $box['items'][] = $element;
        return;
      }
      $elements[] = $element;
    };
    $flushBox = function() use (&$elements, &$box): void {
      if ($box === null) {
        return;
      }
      $elements[] = $box;
      $box = null;
    };
    $flushParagraph = function() use (&$paragraph, &$links, $append): void {
      if ($paragraph === []) {
        return;
      }
      $append(['type' => 'text', 'role' => 'body', 'runs' => self::inline(implode(' ', $paragraph), $links)]);
      $paragraph = [];
    };
    $flushList = function() use (&$list, &$links, &$bulletImage, $append): void {
      if ($list === null) {
        return;
      }
      $items = [];
      foreach ($list['items'] as $index => $item) {
        $items[] = [
          'runs' => self::inline($item, $links),
          'marker' => $list['ordered'] ? ($index + 1) . '.' : '*',
          'bullet' => !$list['ordered'] ? $bulletImage : null,
        ];
      }
      $append(['type' => 'list', 'ordered' => $list['ordered'], 'items' => $items]);
      $list = null;
    };
    $flushQuote = function() use (&$quote, &$links, $append): void {
      if ($quote === []) {
        return;
      }
      $runs = [];
      foreach ($quote as $index => $line) {
        if ($index > 0) {
          $runs[] = ['type' => 'br'];
        }
        array_push($runs, ...self::inline($line, $links));
      }
      $append(['type' => 'text', 'role' => 'quote', 'runs' => $runs]);
      $quote = [];
    };

    foreach ($lines as $line) {
      $trimmed = trim($line);
      if ($trimmed === '---') {
        continue;
      }
      if (str_starts_with($trimmed, '```')) {
        if ($inCode) {
          $append(['type' => 'text', 'role' => 'code', 'runs' => [['text' => implode("\n", $code), 'fontFamily' => 'monospace']]]);
          $code = [];
          $inCode = false;
        } else {
          $flushParagraph();
          $flushList();
          $flushQuote();
          $inCode = true;
        }
        continue;
      }
      if ($inCode) {
        $code[] = $line;
        continue;
      }
      if ($inComment) {
        if (($end = strpos($line, '-->')) !== false) {
          $comment[] = substr($line, 0, $end);
          self::appendPromptComment($comment, $promptLines);
          $comment = [];
          $inComment = false;
        } else {
          $comment[] = $line;
        }
        continue;
      }
      if (($start = strpos($line, '<!--')) !== false) {
        $flushParagraph();
        $flushList();
        $flushQuote();
        $afterStart = substr($line, $start + 4);
        if (($end = strpos($afterStart, '-->')) !== false) {
          self::appendPromptComment([substr($afterStart, 0, $end)], $promptLines);
        } else {
          $comment = [$afterStart];
          $inComment = true;
        }
        continue;
      }
      if ($trimmed === '') {
        $flushParagraph();
        $flushList();
        $flushQuote();
        continue;
      }
      if (preg_match('/^(#{1,6})\s*(.*)$/', $trimmed, $match)) {
        $flushParagraph();
        $flushList();
        $flushQuote();
        $level = strlen($match[1]);
        if ($level !== 6) {
          $flushBox();
        }
        if ($level <= 2) {
          $promptTitle = trim($match[2]);
          $title = self::inline($match[2], $links);
          $titleLevel = $level;
          continue;
        }
        if ($level >= 3 && $level <= 5) {
          $box = [
            'type' => 'box',
            'size' => [3 => 'half', 4 => 'third', 5 => 'quarter'][$level],
            'title' => self::inline($match[2], $links),
            'items' => [],
          ];
          continue;
        }
        if ($level === 6) {
          $append(['type' => 'text', 'role' => 'subtitle', 'runs' => self::inline($match[2], $links)]);
          continue;
        }
      }
      if (preg_match('/^\s*([*+-]|\d+\.)\s+(.*)$/', $line, $match)) {
        $flushParagraph();
        $flushQuote();
        $ordered = str_ends_with($match[1], '.');
        if ($list !== null && $list['ordered'] !== $ordered) {
          $flushList();
        }
        $list ??= ['ordered' => $ordered, 'items' => []];
        $list['items'][] = $match[2];
        continue;
      }
      if (str_starts_with(ltrim($line), '>')) {
        $flushParagraph();
        $flushList();
        $quote[] = ltrim(ltrim($line), "> \t");
        continue;
      }
      if (preg_match('/^!\[([^\]]*)\]\(([^\)]+)\)$/', $trimmed, $match)) {
        $flushParagraph();
        $flushList();
        $flushQuote();
        $imageRole = trim($match[1]);
        if ($imageRole === ':bullet') {
          $bulletImage = self::resolvePath($match[2], $basePath);
          continue;
        }
        $image = ['type' => 'image', 'src' => self::resolvePath($match[2], $basePath), 'alt' => $match[1]];
        if (($absolute = self::absoluteImageSpec($imageRole)) !== null) {
          $image['position'] = 'absolute';
          $image['rect'] = $absolute;
          $elements[] = $image;
          continue;
        }
        $append($image);
        continue;
      }
      $paragraph[] = $line;
    }
    if ($inCode) {
      $append(['type' => 'text', 'role' => 'code', 'runs' => [['text' => implode("\n", $code), 'fontFamily' => 'monospace']]]);
    }
    if ($inComment) {
      self::appendPromptComment($comment, $promptLines);
    }
    $flushParagraph();
    $flushList();
    $flushQuote();
    $flushBox();

    return [
      'slide' => ['type' => $titleLevel === 1 ? 'main' : 'normal', 'title' => $title, 'elements' => $elements],
      'links' => $links,
      'promptTitle' => $promptTitle,
      'promptText' => implode("\n", $promptLines),
    ];
  }

  private static function inline(string $text, array &$links): array {
    $runs = [];
    $pattern = '/(`[^`]+`|\*\*[^*]+\*\*|(?<!!)\[[^\]]+\]\([^\)]+\)|<(?:https?:\/\/|mailto:)[^>]+>)/';
    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    foreach ($parts as $part) {
      if ($part === '') {
        continue;
      }
      if (preg_match('/^`([^`]+)`$/', $part, $m)) {
        $runs[] = ['text' => $m[1], 'role' => 'code'];
      } else if (preg_match('/^\*\*([^*]+)\*\*$/', $part, $m)) {
        $runs[] = ['text' => $m[1], 'role' => 'strong'];
      } else if (preg_match('/^\[([^\]]+)\]\(([^\)]+)\)$/', $part, $m)) {
        $id = count($links);
        if ($id <= 9) {
          $links[$id] = $m[2];
        }
        $runs[] = ['text' => '[' . $id . '] ' . $m[1], 'role' => 'link'];
      } else if (preg_match('/^<((?:https?:\/\/|mailto:)[^>]+)>$/', $part, $m)) {
        $id = count($links);
        if ($id <= 9) {
          $links[$id] = $m[1];
        }
        $runs[] = ['text' => '[' . $id . '] ' . $m[1], 'role' => 'link'];
      } else {
        $runs[] = ['text' => $part];
      }
    }
    return $runs;
  }

  private static function appendPromptComment(array $comment, array &$promptLines): void {
    $lines = array_map(fn(string $line): string => trim($line), $comment);
    while ($lines !== [] && $lines[0] === '') {
      array_shift($lines);
    }
    while ($lines !== [] && end($lines) === '') {
      array_pop($lines);
    }
    foreach ($lines as $line) {
      $promptLines[] = trim((string)$line);
    }
    while ($promptLines !== [] && end($promptLines) === '') {
      array_pop($promptLines);
    }
  }

  private static function absoluteImageSpec(string $text): ?array {
    if (!preg_match('/^:absolute:([^x]+)x([^-]+)-([^-]+)-([^-]+)$/', $text, $match)) {
      return null;
    }
    return [
      'width' => $match[1],
      'height' => $match[2],
      'x' => $match[3],
      'y' => $match[4],
    ];
  }

  private static function resolvePath(string $path, string $basePath): string {
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) || str_starts_with($path, '/')) {
      return $path;
    }
    $basePath = rtrim($basePath, '/');
    $candidates = [$basePath . '/' . $path, dirname($basePath) . '/' . $path, getcwd() . '/' . $path];
    foreach ($candidates as $candidate) {
      if (is_file($candidate)) {
        return $candidate;
      }
    }
    return $candidates[0];
  }

}
