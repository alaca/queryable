<?php

declare(strict_types=1);

namespace Queryable\Tests\Support;

class WpdbDouble
{
    public string $prefix = 'wp_';
    public string $charset = 'latin1';
    public string $collate = 'latin1_swedish_ci';

    /** Table names the server is pretending to hold, unprefixed by nothing: full names. */
    public array $tables = [];

    public function prepare(string $query, mixed ...$args): string
    {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    public function get_var(string $query): ?string
    {
        preg_match("/LIKE '(.*)'\$/", $query, $m);

        $name = stripslashes($m[1] ?? '');

        return in_array($name, $this->tables, true) ? $name : null;
    }
}
