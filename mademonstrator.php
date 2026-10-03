#!/usr/bin/env php
<?php

define('APP_DIR', __DIR__);
define('APP_PATH', __FILE__);
require_once APP_DIR . '/SPTK/App.php';
require_once APP_DIR . '/App/Autoload.php';
spl_autoload_register(['MADEMO\\App\\Autoload', 'load']);
new \SPTK\App();
