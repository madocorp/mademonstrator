#!/usr/bin/env php
<?php

define('SPTK\DEBUG', false);
define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'MADEMO');

require_once 'SPTK/Autoload.php';

new SPTK\App(
  'Layout/mademo.xml',
  'Layout/style.xss',
  ['\MADEMO\App\Controller', 'init'],
  null,
  null,
  null
);
