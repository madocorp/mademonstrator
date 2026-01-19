<?php

namespace MADEMO\Tokenizer;

class MdList extends \SPTK\Tokenizer {

  protected $stylePrefix = 'sx-';
  protected $styleMap = [
    'LIST' => 'list',
    'ORDERED_LIST' => 'list',
    'INLINE_CODE' => 'code',
    'EMPHASIZED' => 'emp',
    'LINK' => 'link',
    'WORD' => 'list'
  ];
  protected $contextSwitchers = [
    [
      'start' => '**',
      'end' => '**',
      'tokenizer' => '\MADEMO\Tokenizer\MdEmp',
      'type' => 'EMPHASIZED'
    ],
  ];
  protected $charRules = [
  ];
  protected $regexpRules = [
    ['type' => 'LIST', 'first' => true, 'regexp' => '/^ *\* /'],
    ['type' => 'ORDERED_LIST', 'first' => true, 'regexp' => '/^ *[0-9]+\. /'],
    ['type' => 'INLINE_CODE', 'regexp' => '/^`[^`]+`/'],
    ['type' => 'LINK', 'regexp' => '/^\[[^\]]*\]\([^\)]*\)/'],
    ['type' => 'LINK', 'regexp' => '/^<[^>]+>/'],
    ['type' => 'WORD', 'regexp' => '/^[^\s\*`!\[]+/'],
    ['type' => 'WORD', 'regexp' => '/^[\*`!\[]/'],
    ['type' => 'WHITESPACE', 'regexp' => '/^\s+/']
  ];

}

(new MdList)->initialize();