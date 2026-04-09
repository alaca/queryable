<?php

namespace Queryable\Concerns;

use Queryable\Clauses\RawSQL;
use Queryable\QueryResult;

/**
 * Runs queries against $wpdb and returns results
 */
trait ExecutesQueries
{
    private static bool $bigSelectsEnabled = false;

    private function execute(string $sql): array|QueryResult
    {
        global $wpdb;

        if (!self::$bigSelectsEnabled) {
            $wpdb->query('SET SESSION SQL_BIG_SELECTS=1');
            self::$bigSelectsEnabled = true;
        }

        if (stripos(trim($sql), 'SELECT') === 0) {
            return ['rows' => $wpdb->get_results($sql, ARRAY_A) ?: []];
        }

        $wpdb->query($sql);

        return new QueryResult(
            (int) $wpdb->rows_affected,
            (int) $wpdb->insert_id,
        );
    }

    private function run(string $sql): QueryResult
    {
        global $wpdb;

        if (!$wpdb) {
            return new QueryResult();
        }

        return $this->execute($sql);
    }

    /**
     * Single row or null
     */
    public function get(): mixed
    {
        $this->query['limit'] = 1;
        $result = $this->execute($this->toSQL());
        $rows = $this->loadRelations($result['rows']);
        $rows = $this->parseMetaResults($rows);

        return $rows[0] ?? null;
    }

    /**
     * All rows
     */
    public function getAll(): array
    {
        $result = $this->execute($this->toSQL());
        $rows = $this->loadRelations($result['rows']);

        return $this->parseMetaResults($rows);
    }

    /**
     * Shorthand for ->where($column, $value)->get()
     */
    public function find(string $column, mixed $value): mixed
    {
        $this->where($column, $value);
        $this->query['limit'] = 1;
        $result = $this->execute($this->toSQL());
        $rows = $this->loadRelations($result['rows']);
        $rows = $this->parseMetaResults($rows);

        return $rows[0] ?? null;
    }

    public function exists(): bool
    {
        $this->query['select'] = [new RawSQL('1')];
        $this->setQueryType('SELECT');
        $this->query['limit'] = 1;
        $result = $this->execute($this->toSQL());

        return !empty($result['rows']);
    }

    public function pluck(string $column): array
    {
        $this->select($column);
        $result = $this->execute($this->toSQL());

        return array_column($result['rows'], $column);
    }

    public function count(string $column = 'id'): int
    {
        $this->selectRaw("COUNT({$column}) as aggregate");
        $result = $this->execute($this->toSQL());

        return (int) ($result['rows'][0]['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $this->selectRaw("SUM({$column}) as aggregate");
        $result = $this->execute($this->toSQL());

        return (float) ($result['rows'][0]['aggregate'] ?? 0);
    }

    public function avg(string $column): float
    {
        $this->selectRaw("AVG({$column}) as aggregate");
        $result = $this->execute($this->toSQL());

        return (float) ($result['rows'][0]['aggregate'] ?? 0);
    }

    public function min(string $column): float
    {
        $this->selectRaw("MIN({$column}) as aggregate");
        $result = $this->execute($this->toSQL());

        return (float) ($result['rows'][0]['aggregate'] ?? 0);
    }

    public function max(string $column): float
    {
        $this->selectRaw("MAX({$column}) as aggregate");
        $result = $this->execute($this->toSQL());

        return (float) ($result['rows'][0]['aggregate'] ?? 0);
    }
}
