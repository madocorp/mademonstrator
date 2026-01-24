#!/usr/bin/env php
<?php

define('SPTK\DEBUG', true);
define('APP_PATH', __FILE__);

require_once 'SPTK/App.php';
require_once 'App/Controller.php';
require_once 'App/Presentation.php';
require_once 'App/Slide.php';
require_once 'App/Tokenizer/Md.php';
require_once 'App/Tokenizer/MdEmp.php';
require_once 'App/Tokenizer/MdCode.php';
require_once 'App/Tokenizer/MdList.php';
require_once 'App/Tokenizer/MdComment.php';

new SPTK\App(
  'Layout/mademo.xml',
  'Layout/style.xss',
  ['\MADEMO\Controller', 'init'],
  false,
  false,
  false
);
