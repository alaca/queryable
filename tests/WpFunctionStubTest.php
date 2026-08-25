<?php

declare(strict_types=1);

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;

class WpFunctionStubTest extends TestCase
{
    /**
     * With WordPress loaded only the global get_option exists, so a
     * function_exists() check on the namespaced name is not a guard at all. The
     * stubs would be declared anyway, win resolution inside Queryable, and turn
     * dbDelta() into an array append while the integration suite reported green.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_the_stubs_are_inert_when_wordpress_is_loaded(): void
    {
        define('ABSPATH', '/nowhere/');

        require __DIR__ . '/stubs/wp-functions.php';

        self::assertFalse(function_exists('Queryable\get_option'));
        self::assertFalse(function_exists('Queryable\update_option'));
        self::assertFalse(function_exists('Queryable\dbDelta'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_the_stubs_are_declared_without_wordpress(): void
    {
        require __DIR__ . '/stubs/wp-functions.php';

        self::assertTrue(function_exists('Queryable\get_option'));
        self::assertTrue(function_exists('Queryable\update_option'));
        self::assertTrue(function_exists('Queryable\dbDelta'));
    }
}
