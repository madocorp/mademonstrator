<?php

namespace MADEMO\Tokenizer;

class MdComment extends \SPTK\Tokenizer {

  protected $stylePrefix = 'sx-';
  protected $styleMap = [
    'COMMENT' => 'comment',
  ];
  protected $contextSwitchers = [
  ];
  protected $charRules = [
    '-' => 'COMMENT',
  ];
  protected $regexpRules = [
    ['type' => 'COMMENT', 'regexp' => '/^[^-]+/'],
  ];

}

(new MdComment)->initialize();
