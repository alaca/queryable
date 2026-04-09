<?php

namespace Queryable\Clauses;

/**
 * Raw SQL
 */
class RawSQL
{
    public string $sql;

    public function __construct(string $sql, array $args = [])
    {
        $this->sql = empty($args) ? $sql : vsprintf($sql, array_map(fn ($v) => self::escape($v), $args));
    }

    public static function escape(mixed $val): string|int|float
    {
        if ($val === null) {
            return 'NULL';
        }
        if (is_int($val) || is_float($val)) {
            return $val;
        }
        if (is_bool($val)) {
            return $val ? 1 : 0;
        }

        global $wpdb;

        $str = (string) $val;

        if ($wpdb && method_exists($wpdb, '_real_escape')) {
            return "'" . $wpdb->_real_escape($str) . "'";
        }

        // Fallback for tests / SQL-only mode
        return "'" . str_replace("'", "''", $str) . "'";
    }
}
