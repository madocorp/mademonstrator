<?php

namespace MADEMO\Tokenizer;

class Md extends \SPTK\Tokenizer {

  protected $stylePrefix = 'sx-';
  protected $styleMap = [
    'T1' => 'maintitle',
    'T2' => 'maintitle',
    'T3' => 'title',
    'T4' => 'title',
    'T5' => 'title',
    'T6' => 'title',
    'EMPHASIZED' => 'emp',
    'CODE' => 'code',
    'INLINE_CODE' => 'code',
    'BLOCKQUOTE' => 'quote',
    'LIST' => 'list',
    'ORDERED_LIST' => 'list',
    'LINK' => 'link',
    'IMAGE' => 'link',
    'COMMENT' => 'comment'
  ];
  protected $contextSwitchers = [
    [
      'start' => '**',
      'end' => '**',
      'tokenizer' => '\MADEMO\Tokenizer\MdEmp',
      'type' => 'EMPHASIZED'
    ],
    [
      'start' => '```',
      'end' => '```',
      'tokenizer' => '\MADEMO\Tokenizer\MdCode',
      'type' => 'CODE'
    ],
    [
      'startRegexp' => '/^ *[0-9]+\. /',
      'startFirst' => true,
      'end' => 'empty',
      'tokenizer' => '\MADEMO\Tokenizer\MdList',
      'type' => 'ORDERED_LIST'
    ],
    [
      'startRegexp' => '/^ *\* /',
      'startFirst' => true,
      'end' => 'empty',
      'tokenizer' => '\MADEMO\Tokenizer\MdList',
      'type' => 'LIST'
    ],
    [
      'start' => '<!--',
      'end' => '-->',
      'tokenizer' => '\MADEMO\Tokenizer\MdComment',
      'type' => 'COMMENT'
    ],
  ];
  protected $charRules = [
  ];
  protected $regexpRules = [
    ['type' => 'T6', 'first' => true, 'regexp' => '/^######.*/'],
    ['type' => 'T5', 'first' => true, 'regexp' => '/^#####.*/'],
    ['type' => 'T4', 'first' => true, 'regexp' => '/^####.*/'],
    ['type' => 'T3', 'first' => true, 'regexp' => '/^###.*/'],
    ['type' => 'T2', 'first' => true, 'regexp' => '/^##.*/'],
    ['type' => 'T1', 'first' => true, 'regexp' => '/^#.*/'],
    ['type' => 'INLINE_CODE', 'regexp' => '/^`[^`]+`/'],
    ['type' => 'BLOCKQUOTE', 'first' => true, 'regexp' => '/^>.*/'],
    ['type' => 'HLINE', 'regexp' => '/^---$/'],
    ['type' => 'IMAGE', 'regexp' => '/^!\[[^\]]*\]\([^\)]*\)/'],
    ['type' => 'LINK', 'regexp' => '/^\[[^\]]*\]\([^\)]*\)/'],
    ['type' => 'LINK', 'regexp' => '/^<[^!][^>]+>/'],
    ['type' => 'WORD', 'regexp' => '/^[^\s\*`!\[<]+/'],
    ['type' => 'WORD', 'regexp' => '/^[\*`!\[<]/'],
    ['type' => 'WHITESPACE', 'regexp' => '/^\s+/']
  ];

}

(new Md)->initialize();
