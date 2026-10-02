<?php

namespace MADEMO\App;

/** Loads application classes independently of SPTK's library autoloader. */
final class Autoload {

  /** Resolve an application namespace to its class file. */
  public static function load(string $class): void {
    if (str_starts_with($class, 'MADEMO\\App\\')) {
      require_once APP_DIR . '/App/' . substr($class, strlen('MADEMO\\App\\')) . '.php';
    }
  }

}
