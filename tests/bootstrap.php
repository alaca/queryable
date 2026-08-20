<?php

$tmpDir = getenv('TMPDIR') ?: '/tmp';
$wpTestsDir = getenv('WP_TESTS_DIR') ?: rtrim($tmpDir, '/') . '/wordpress-tests-lib';

if (file_exists($wpTestsDir . '/includes/functions.php')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills');

    require_once $wpTestsDir . '/includes/functions.php';

    tests_add_filter('muplugins_loaded', function () {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
    });

    require_once $wpTestsDir . '/includes/bootstrap.php';
} elseif (getenv('QUERYABLE_REQUIRE_WP') === '1') {
    fwrite(STDERR, "\nThe WordPress test library is not at {$wpTestsDir}.\n"
        . "Every file in this suite is a no-op without it, so the run would report\n"
        . "success having tested nothing. Set WP_TESTS_DIR or install the library.\n\n");
    exit(1);
} else {
    require_once __DIR__ . '/../vendor/autoload.php';

    defined('OBJECT') || define('OBJECT', 'OBJECT');
    defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
    defined('ARRAY_N') || define('ARRAY_N', 'ARRAY_N');
    defined('OBJECT_K') || define('OBJECT_K', 'OBJECT_K');
}
