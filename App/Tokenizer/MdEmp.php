<?php

namespace MADEMO\Tokenizer;

class MdEmp extends \SPTK\Tokenizer {

  protected $stylePrefix = 'sx-';
  protected $styleMap = [
    'EMPHASIZED' => 'emp'
  ];
  protected $contextSwitchers = [
  ];
  protected $charRules = [
  ];
  protected $regexpRules = [
    ['type' => 'EMPHASIZED', 'regexp' => '/^[^\*\s]+/'],
    ['type' => 'WHITESPACE', 'regexp' => '/^\s+/']
  ];

}

(new MdEmp)->initialize();