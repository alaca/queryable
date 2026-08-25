<?php

declare(strict_types=1);

/**
 * Model resolves WordPress functions unqualified, so PHP looks in the Queryable
 * namespace before the global one. Declaring them here lets the unit suite drive
 * Model::migrate() without WordPress loaded. Never load this file in the WP
 * integration suite: it would shadow the real functions.
 */

namespace Queryable;

// function_exists() on the namespaced name is no guard: with WordPress loaded
// only the global functions exist, so the stubs would be declared anyway and
// would win resolution inside this namespace.
if (defined('ABSPATH')) {
    return;
}

if (!function_exists('Queryable\get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['queryable_options'][$key] ?? $default;
    }
}

if (!function_exists('Queryable\update_option')) {
    function update_option(string $key, mixed $value): bool
    {
        $GLOBALS['queryable_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('Queryable\dbDelta')) {
    function dbDelta(string $sql): array
    {
        $GLOBALS['queryable_dbdelta'][] = $sql;

        return [];
    }
}
