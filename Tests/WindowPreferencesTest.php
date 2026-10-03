<?php

namespace MADEMO\Tests;

use MADEMO\App\WindowPreferences;

/** Verify geometry validation and preservation of the predecessor's window settings. */
function windowPreferences(): void {
  assertSame(['mode' => 'fullscreen'], WindowPreferences::parse('full'), 'Legacy full mode is retained.');
  assertSame(['mode' => 'maximized'], WindowPreferences::parse('max'), 'Legacy max mode is retained.');
  assertSame(['mode' => 'none'], WindowPreferences::parse('none', true), 'Helper may be disabled.');
  assertSame(['mode' => 'normal', 'width' => 1280, 'height' => 720, 'cells' => false], WindowPreferences::parse('1280x720'), 'Large geometry uses pixel dimensions.');
  assertSame(['mode' => 'normal', 'width' => 72, 'height' => 18, 'cells' => true], WindowPreferences::parse('72 x 18', true), 'Legacy small geometry uses grid dimensions.');
  foreach (['none', '0x720', '-1280x720', '1280x', '1280x720 trailing'] as $invalid) {
    try {
      WindowPreferences::parse($invalid);
      throw new TestFailure('Invalid presentation geometry should be rejected: ' . $invalid);
    } catch (\InvalidArgumentException $error) {
      assertTrue(str_contains($error->getMessage(), 'Presentation window'), 'Validation identifies the invalid field.');
    }
  }
  WindowPreferences::initialize(['presentationWindow' => 'max', 'promptBox' => 'none']);
  assertSame('max', WindowPreferences::$presentation, 'Stored presentation geometry is restored.');
  assertSame('none', WindowPreferences::$helper, 'Stored disabled helper is restored.');
  WindowPreferences::initialize(['presentationWindow' => 'invalid', 'promptBox' => '0x0']);
  assertSame('full', WindowPreferences::$presentation, 'Invalid stored presentation mode falls back safely.');
  assertSame('78x36', WindowPreferences::$helper, 'Invalid stored helper size falls back safely.');
}

return ['Window geometry preferences' => __NAMESPACE__ . '\\windowPreferences'];
