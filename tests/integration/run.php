<?php
/**
 * Slate — integration-test runner (Phase 1).
 *
 * Unlike the unit runner (autoloader-only, no DB), integration tests boot the
 * full app (config.php) and exercise new code against the REAL database — e.g.
 * proving a repository is at parity with a legacy Database:: path. Read-mostly;
 * any probe rows are created and cleaned up within the test.
 *
 * Usage: php tests/integration/run.php   (exit 0 = all passed)
 */

declare(strict_types=1);

require __DIR__ . '/../../config.php';       // full bootstrap (autoloader + DB + aliases)
require __DIR__ . '/../unit/harness.php';    // reuse the assertion harness

echo "# Slate integration tests\n";

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    // The repository snapshot does not include the optional Studio plugin.
    // Keep its tests visible as explicit skips instead of failing unrelated CI.
    if (str_starts_with(basename($file), 'Studio') && !is_file(__DIR__ . '/../../plugins/studio/StudioAPI.php')) {
        echo '# SKIP optional Studio plugin is not present: ' . basename($file) . "\n";
        continue;
    }
    require $file;
}

exit(unit_summary());
