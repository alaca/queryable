<?php

namespace Queryable;

use Closure;
use Throwable;

class DB
{
    private static array $schema = [];

    public static function init(array $options = []): void
    {
        static::$schema = $options['schema'] ?? [];
    }

    public static function table(string|Closure $name, ?string $alias = null): QueryBuilder
    {
        global $wpdb;
        $prefix = $wpdb->prefix ?? '';

        $tableSchema = [];

        if (is_string($name) && isset(static::$schema[$name])) {
            $tableSchema = static::$schema[$name];

            if (isset($tableSchema['meta']['table'])) {
                $tableSchema['meta']['table'] = $prefix . $tableSchema['meta']['table'];
            }

            if (isset($tableSchema['relations'])) {
                foreach ($tableSchema['relations'] as &$rel) {
                    $rel['table'] = $prefix . $rel['table'];
                }
                unset($rel);
            }
        }

        $builder = new QueryBuilder($tableSchema);

        if ($name instanceof Closure) {
            $builder->table($name, $alias);
        } else {
            $builder->table($prefix . $name, $alias);
        }

        return $builder;
    }

    public static function raw(string $sql, array $params = []): array|QueryResult
    {
        global $wpdb;

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        if (stripos(trim($sql), 'SELECT') === 0) {
            return ['rows' => $wpdb->get_results($sql, OBJECT) ?: []];
        }

        $wpdb->query($sql);

        return new QueryResult(
            (int) $wpdb->rows_affected,
            (int) $wpdb->insert_id,
        );
    }

    public static function transaction(callable $callback): mixed
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $result = $callback();
            $wpdb->query('COMMIT');

            return $result;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public static function getPrefix(): string
    {
        global $wpdb;

        return $wpdb->prefix ?? '';
    }

    public static function reset(): void
    {
        static::$schema = [];
    }
}
