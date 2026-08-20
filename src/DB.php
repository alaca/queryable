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
     * Only the outermost call runs START TRANSACTION / COMMIT / ROLLBACK: a
     * second START TRANSACTION implicit-commits the first, which would drop the
     * atomicity of everything already written. A nested call instead takes a
     * SAVEPOINT and, on the way out, rolls back to it or releases it, so a
     * throw undoes that block alone and leaves the enclosing transaction open.
     *
     * The savepoint name is a fixed prefix plus the nesting depth, so it is
     * unique among the savepoints currently open and can only ever be digits.
     * Levels the callback has already left reuse a name safely: MySQL replaces
     * a same-named savepoint, and that one is gone either way.
     */
    public static function transaction(callable $callback): mixed
    {
        global $wpdb;

        $savepoint = null;

        if (self::$transactionDepth === 0) {
            $wpdb->query('START TRANSACTION');
        } else {
            $savepoint = 'queryable_sp_' . self::$transactionDepth;
            $wpdb->query('SAVEPOINT ' . $savepoint);
            // Running the callback without a savepoint to undo it would restore
            // the silent no-op this exists to remove: the block would look
            // transactional and roll back nothing. Nothing has run yet, so the
            // caller can still be told before any of it is attempted.
            self::assertNoDbError('SAVEPOINT ' . $savepoint);
        }

        self::$transactionDepth++;

        try {
            $result = $callback();
        } catch (Throwable $e) {
            self::$transactionDepth--;
            if ($savepoint === null) {
                $wpdb->query('ROLLBACK');
            } else {
                // Deliberately unchecked: the callback's exception is the
                // caller's cause and callers dispatch on its type, so it has to
                // arrive unchanged. A savepoint can only have vanished here
                // through an implicit commit or a lost connection, neither of
                // which this layer can undo.
                $wpdb->query('ROLLBACK TO SAVEPOINT ' . $savepoint);
            }
            throw $e;
        }

        self::$transactionDepth--;
        if ($savepoint === null) {
            $wpdb->query('COMMIT');
        } else {
            // Unchecked for the same reason, minus the exception: the block
            // succeeded, and a savepoint that is already gone cannot be given
            // back. Releasing at all is what stops them accumulating for the
            // life of the enclosing transaction.
            $wpdb->query('RELEASE SAVEPOINT ' . $savepoint);
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
