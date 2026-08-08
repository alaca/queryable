<?php

namespace Queryable;

use Closure;
use Throwable;

class DB
{
    private static array $schema = [];
    private static int $transactionDepth = 0;

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
            $rows = $wpdb->get_results($sql, OBJECT) ?: [];
            self::assertNoDbError($sql);

            return ['rows' => $rows];
        }

        $wpdb->query($sql);
        self::assertNoDbError($sql);

        return new QueryResult(
            (int) $wpdb->rows_affected,
            (int) $wpdb->insert_id,
        );
    }

    /**
     * The same guard the query builder applies, for the raw path.
     *
     * $wpdb answers a failed query with false rather than an exception, so
     * without this a broken raw write reports success and a broken raw read
     * is indistinguishable from a table with no matching rows.
     */
    private static function assertNoDbError(string $sql): void
    {
        global $wpdb;

        if (!empty($wpdb->last_error)) {
            throw new QueryException($wpdb->last_error, $sql);
        }
    }

    /**
     * Run $callback inside a database transaction.
     *
     * Nested calls reuse the outermost transaction instead of
     * issuing another START TRANSACTION (which MySQL implicit-commits the
     * outer, silently dropping atomicity). Only the outermost call runs
     * START TRANSACTION / COMMIT / ROLLBACK.
     */
    public static function transaction(callable $callback): mixed
    {
        global $wpdb;

        if (self::$transactionDepth === 0) {
            $wpdb->query('START TRANSACTION');
        }
        self::$transactionDepth++;

        try {
            $result = $callback();
        } catch (Throwable $e) {
            self::$transactionDepth--;
            if (self::$transactionDepth === 0) {
                $wpdb->query('ROLLBACK');
            }
            throw $e;
        }

        self::$transactionDepth--;
        if (self::$transactionDepth === 0) {
            $wpdb->query('COMMIT');
        }

        return $result;
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
