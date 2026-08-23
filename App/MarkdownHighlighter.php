<?php

namespace MADEMO\App;

use SPTK2\Core\Tokenizer;

final class MarkdownHighlighter extends Tokenizer {

  protected array $styleMap = [
    'TEXT' => 'plain',
    'HEADING' => 'heading',
    'LIST' => 'list',
    'QUOTE' => 'quote',
    'CODE_FENCE' => 'code',
    'INLINE_CODE' => 'code',
    'EMPHASIS' => 'emphasis',
    'LINK' => 'link',
    'IMAGE' => 'link',
    'COMMENT' => 'comment',
    'HLINE' => 'muted',
  ];

  protected array $styleColors = [
    'heading' => ['fg' => '#ffffff'],
    'list' => ['fg' => '#ffff00'],
    'quote' => ['fg' => '#004400'],
    'code' => ['fg' => '#003333'],
    'emphasis' => ['fg' => '#aa0000'],
    'link' => ['fg' => '#000088'],
    'comment' => ['fg' => '#555555'],
    'muted' => ['fg' => '#555555'],
  ];

  protected array $regexpRules = [
    ['type' => 'COMMENT', 'regexp' => '/^<!--.*?-->/'],
    ['type' => 'CODE_FENCE', 'regexp' => '/^```.*$/'],
    ['type' => 'HEADING', 'regexp' => '/^#{1,6}\s+.*$/'],
    ['type' => 'HLINE', 'regexp' => '/^---+$/'],
    ['type' => 'QUOTE', 'regexp' => '/^>\s?.*$/'],
    ['type' => 'LIST', 'regexp' => '/^\s*(?:[*+-]|\d+\.)\s+/'],
    ['type' => 'IMAGE', 'regexp' => '/^!\[[^\]]*\]\([^\)]*\)/'],
    ['type' => 'LINK', 'regexp' => '/^\[[^\]]+\]\([^\)]*\)/'],
    ['type' => 'LINK', 'regexp' => '/^<(?:https?:\/\/|mailto:)[^>]+>/'],
    ['type' => 'INLINE_CODE', 'regexp' => '/^`[^`]+`/'],
    ['type' => 'EMPHASIS', 'regexp' => '/^\*\*[^*]+\*\*/'],
    ['type' => 'TEXT', 'regexp' => '/^\s+/'],
    ['type' => 'TEXT', 'regexp' => '/^[^!\[`<*\s]+/'],
    ['type' => 'TEXT', 'regexp' => '/^./'],
  ];

}
