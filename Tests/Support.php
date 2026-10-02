<?php

namespace MADEMO\Tests;

class TestFailure extends \Exception {
}

function assertSame(mixed $expected, mixed $actual, string $message): void {
  if ($expected !== $actual) {
    throw new TestFailure($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
  }
}

function assertTrue(bool $actual, string $message): void {
  if (!$actual) {
    throw new TestFailure($message);
  }
}
