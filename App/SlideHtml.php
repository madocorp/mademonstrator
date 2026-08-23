<?php

namespace MADEMO\App;

final class SlideHtml {

  public static function fromMarkdown(array $lines, string $basePath): array {
    $html = [];
    $links = [];
    $paragraph = [];
    $list = null;
    $quote = [];
    $code = [];
    $inCode = false;

    $flushParagraph = function() use (&$html, &$paragraph, &$links): void {
      if ($paragraph === []) {
        return;
      }
      $html[] = '<p>' . self::inline(implode(' ', $paragraph), $links) . '</p>';
      $paragraph = [];
    };
    $flushList = function() use (&$html, &$list): void {
      if ($list === null) {
        return;
      }
      $html[] = '<' . $list['tag'] . '>' . implode('', $list['items']) . '</' . $list['tag'] . '>';
      $list = null;
    };
    $flushQuote = function() use (&$html, &$quote, &$links): void {
      if ($quote === []) {
        return;
      }
      $parts = array_map(fn(string $line): string => self::inline($line, $links), $quote);
      $html[] = '<blockquote>' . implode('<br>', $parts) . '</blockquote>';
      $quote = [];
    };

    foreach ($lines as $line) {
      $trimmed = trim($line);
      if ($trimmed === '---') {
        continue;
      }
      if (str_starts_with($trimmed, '```')) {
        if ($inCode) {
          $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
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
        $html[] = '<h' . $level . '>' . self::inline($match[2], $links) . '</h' . $level . '>';
        continue;
      }
      if (preg_match('/^\s*([*+-]|\d+\.)\s+(.*)$/', $line, $match)) {
        $flushParagraph();
        $flushQuote();
        $tag = str_ends_with($match[1], '.') ? 'ol' : 'ul';
        if ($list !== null && $list['tag'] !== $tag) {
          $flushList();
        }
        $list ??= ['tag' => $tag, 'items' => []];
        $list['items'][] = '<li>' . self::inline($match[2], $links) . '</li>';
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
        $src = self::resolvePath($match[2], $basePath);
        $alt = htmlspecialchars($match[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html[] = '<p class="image"><img src="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . $alt . '"></p>';
        continue;
      }
      $paragraph[] = $line;
    }
    if ($inCode) {
      $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    }
    $flushParagraph();
    $flushList();
    $flushQuote();

    return [
      'html' => '<section class="slide">' . implode("\n", $html) . '</section>',
      'links' => $links,
    ];
  }

  private static function inline(string $text, array &$links): string {
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escaped = preg_replace_callback('/`([^`]+)`/', fn(array $m): string => '<code>' . $m[1] . '</code>', $escaped) ?? $escaped;
    $escaped = preg_replace_callback('/\*\*([^*]+)\*\*/', fn(array $m): string => '<strong>' . $m[1] . '</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace_callback('/\[([^\]]+)\]\(([^\)]+)\)/', function(array $m) use (&$links): string {
      $id = count($links);
      if ($id <= 9) {
        $links[$id] = html_entity_decode($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      }
      return '<a href="' . $m[2] . '">[' . $id . '] ' . $m[1] . '</a>';
    }, $escaped) ?? $escaped;
    $escaped = preg_replace_callback('/&lt;((?:https?:\/\/|mailto:)[^&]+)&gt;/', function(array $m) use (&$links): string {
      $id = count($links);
      if ($id <= 9) {
        $links[$id] = html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      }
      return '<a href="' . $m[1] . '">[' . $id . '] ' . $m[1] . '</a>';
    }, $escaped) ?? $escaped;
    return $escaped;
  }

  private static function resolvePath(string $path, string $basePath): string {
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) || str_starts_with($path, '/')) {
      return $path;
    }
    return rtrim($basePath, '/') . '/' . $path;
  }

}
