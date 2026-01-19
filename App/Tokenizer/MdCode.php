<?php

namespace MADEMO\Tokenizer;

class MdCode extends \SPTK\Tokenizer {

  protected $stylePrefix = 'sx-';
  protected $styleMap = [
    'CODE' => 'code',
  ];
  protected $contextSwitchers = [
  ];
  protected $charRules = [
    '`' => 'CODE',
    '~' => 'CODE',
  ];
  protected $regexpRules = [
    ['type' => 'CODE', 'regexp' => '/^[^`~]+/'],
  ];

}

(new MdCode)->initialize();
