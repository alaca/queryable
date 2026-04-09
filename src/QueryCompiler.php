<?php

namespace Queryable;

use Queryable\Clauses\{Data, Join, RawSQL, SelectBuilder, Where};

/**
 * Compiles the query array into a SQL string
 */
class QueryCompiler
{
    private array $query;

    public function __construct(array &$query)
    {
        $this->query = &$query;
    }

    /**
     * Strip a leading SQL keyword if present. Faster than preg_replace.
     */
    private function stripKeyword(string $sql, string $keyword): string
    {
        $len = strlen($keyword);
        if (strncasecmp(ltrim($sql), $keyword, $len) === 0) {
            return ltrim(substr(ltrim($sql), $len));
        }

        return $sql;
    }

    private function compileSelect(): string
    {
        $statements = [];

        foreach ($this->query['select'] as $select) {
            if ($select instanceof RawSQL) {
                $statements[] = $this->stripKeyword($select->sql, 'SELECT ');
                continue;
            }

            if ($select instanceof SelectBuilder) {
                $builder = new QueryBuilder();
                ($select->callback)($builder);
                $statements[] = "({$builder->toSQL()}) AS {$select->column}";
                continue;
            }

            if ($select->alias) {
                $statements[] = "{$select->column} AS {$select->alias}";
                continue;
            }

            $statements[] = $select->column;
        }

        $compiled = !empty($statements) ? implode(', ', $statements) : '*';
        $distinct = $this->query['distinct'] ? 'DISTINCT ' : '';

        return "SELECT {$distinct}{$compiled}";
    }

    private function compileFrom(): string
    {
        $clauses = [];

        foreach ($this->query['table'] as $table) {
            if ($table instanceof RawSQL) {
                $clauses[] = $table->sql;
                continue;
            }
            $clauses[] = $table->alias ? "{$table->name} AS {$table->alias}" : $table->name;
        }

        return 'FROM ' . implode(', ', $clauses);
    }

    private function compileInto(): string
    {
        $table = $this->query['table'][0];

        return 'INSERT INTO ' . ($table instanceof RawSQL ? $table->sql : $table->name);
    }

    public function compileWhere(bool $nestedStatement = false): ?string
    {
        if (empty($this->query['where'])) {
            return null;
        }

        $clauses = [];

        foreach ($this->query['where'] as $where) {
            if ($where instanceof RawSQL) {
                $clauses[] = $this->stripKeyword($where->sql, 'WHERE ');
                continue;
            }

            if ($where instanceof Where) {
                $clauses[] = $this->compileWhereClause($where);
                continue;
            }

            $clauses[] = $where;
        }

        $compiled = implode(' ', $clauses);

        return $nestedStatement ? $compiled : "WHERE {$compiled}";
    }

    private function compileWhereClause(Where $where): string
    {
        $column = $where->column;
        $value = $where->value;
        $comparison = $where->comparisonOperator;
        $op = $this->getOperator($where->logicalOperator);

        return match ($comparison) {
            'LIKE', 'NOT LIKE' => is_string($value)
                ? "{$op}{$column} {$comparison} " . RawSQL::escape(str_contains($value, '%') ? $value : "%{$value}%")
                : '',

            'BETWEEN', 'NOT BETWEEN' => is_array($value)
                ? "{$op}{$column} {$comparison} " . RawSQL::escape($value[0]) . ' AND ' . RawSQL::escape($value[1])
                : '',

            'IN', 'NOT IN' => is_array($value)
                ? "{$op}{$column} {$comparison} (" . implode(', ', array_map(fn ($v) => RawSQL::escape($v), $value)) . ')'
                : '',

            'IS NULL', 'IS NOT NULL' => "{$op}{$column} {$comparison}",

            default => "{$op}{$column} {$comparison} " . RawSQL::escape($value),
        };
    }

    private function compileJoin(): ?string
    {
        if (empty($this->query['join'])) {
            return null;
        }

        $clauses = [];

        foreach ($this->query['join'] as $callback) {
            if ($callback instanceof RawSQL) {
                $clauses[] = $callback->sql;
                continue;
            }

            $builder = new JoinQueryBuilder();
            $callback($builder);

            foreach ($builder->getJoins() as $join) {
                if ($join instanceof RawSQL) {
                    $clauses[] = $join->sql;
                } elseif ($join instanceof Join) {
                    $clauses[] = $join->alias
                        ? "{$join->type} JOIN {$join->table} AS {$join->alias}"
                        : "{$join->type} JOIN {$join->table}";
                } else {
                    // JoinCondition
                    $val = $join->quote ? RawSQL::escape($join->column2) : $join->column2;
                    $clauses[] = "{$join->logicalOperator} {$join->column1} {$join->comparisonOperator} {$val}";
                }
            }
        }

        return implode(' ', $clauses);
    }

    private function compileHaving(): ?string
    {
        if (empty($this->query['having'])) {
            return null;
        }

        $clauses = [];

        foreach ($this->query['having'] as $having) {
            if ($having instanceof RawSQL) {
                $clauses[] = $this->stripKeyword($having->sql, 'HAVING ');
                continue;
            }

            $op = $this->getOperator($having->logicalOperator);
            $escaped = RawSQL::escape($having->value);

            $clauses[] = $having->mathFunction
                ? "{$op}{$having->mathFunction}({$having->column}) {$having->comparisonOperator} {$escaped}"
                : "{$op}{$having->column} {$having->comparisonOperator} {$escaped}";
        }

        return 'HAVING ' . implode(' ', $clauses);
    }

    private function compileGroupBy(): ?string
    {
        return !empty($this->query['groupBy'])
            ? 'GROUP BY ' . implode(', ', $this->query['groupBy'])
            : null;
    }

    private function compileOrderBy(): ?string
    {
        if (empty($this->query['orderBy'])) {
            return null;
        }

        $statements = [];
        foreach ($this->query['orderBy'] as $order) {
            $statements[] = $order instanceof RawSQL
                ? $order->sql
                : "{$order->column} {$order->direction}";
        }

        return 'ORDER BY ' . implode(', ', $statements);
    }

    private function compileLimit(): ?string
    {
        return $this->query['limit'] ? "LIMIT {$this->query['limit']}" : null;
    }

    private function compileOffset(): ?string
    {
        return $this->query['offset'] ? "OFFSET {$this->query['offset']}" : null;
    }

    private function compileInsertData(): ?string
    {
        $data = $this->query['data'];
        if (empty($data)) {
            return null;
        }

        if ($data[0] instanceof RawSQL) {
            return $data[0]->sql;
        }

        // Bulk insert: data is array of Data arrays
        if (is_array($data[0])) {
            $columns = implode(', ', array_map(fn (Data $d) => $d->column, $data[0]));
            $rows = array_map(
                fn (array $row) => '(' . implode(', ', array_map(fn (Data $d) => $d->value, $row)) . ')',
                $data,
            );

            return "({$columns}) VALUES " . implode(', ', $rows);
        }

        // Single insert
        $columns = implode(', ', array_map(fn (Data $d) => $d->column, $data));
        $values = implode(', ', array_map(fn (Data $d) => $d->value, $data));

        return "({$columns}) VALUES ({$values})";
    }

    private function compileUpdateTable(): string
    {
        $table = $this->query['table'][0];

        return 'UPDATE ' . ($table instanceof RawSQL ? $table->sql : $table->name);
    }

    private function compileSet(): ?string
    {
        $data = $this->query['data'];
        if (empty($data)) {
            return null;
        }

        if ($data[0] instanceof RawSQL) {
            return "SET {$data[0]->sql}";
        }

        return 'SET ' . implode(', ', array_map(fn (Data $d) => "{$d->column} = {$d->value}", $data));
    }

    private function compileDeleteFrom(): string
    {
        $table = $this->query['table'][0];

        return 'DELETE FROM ' . ($table instanceof RawSQL ? $table->sql : $table->name);
    }

    private function compileTruncate(): string
    {
        $table = $this->query['table'][0];

        return 'TRUNCATE TABLE ' . ($table instanceof RawSQL ? $table->sql : $table->name);
    }

    private function compileUpsert(): ?string
    {
        if (empty($this->query['upsertConflict'])) {
            return null;
        }

        return 'ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(
            fn ($col) => "{$col} = VALUES({$col})",
            $this->query['upsertUpdate'],
        ));
    }

    private function compileUnion(): ?string
    {
        if (empty($this->query['union'])) {
            return null;
        }

        return implode(' ', array_map(
            fn ($union) => ($union->all ? 'UNION ALL ' : 'UNION ') . $union->builder->toSQL(),
            $this->query['union'],
        ));
    }

    public function compile(): string
    {
        $sql = match ($this->query['type'] ?? 'SELECT') {
            'SELECT' => [
                $this->compileSelect(),
                $this->compileFrom(),
                $this->compileJoin(),
                $this->compileWhere(),
                $this->compileGroupBy(),
                $this->compileHaving(),
                $this->compileOrderBy(),
                $this->compileLimit(),
                $this->compileOffset(),
                $this->compileUnion(),
            ],
            'INSERT' => [
                $this->compileInto(),
                $this->compileInsertData(),
                $this->compileUpsert(),
            ],
            'UPDATE' => [
                $this->compileUpdateTable(),
                $this->compileSet(),
                $this->compileWhere(),
            ],
            'DELETE' => [
                $this->compileDeleteFrom(),
                $this->compileWhere(),
            ],
            'TRUNCATE' => [
                $this->compileTruncate(),
            ],
            default => [],
        };

        return trim(implode(' ', array_filter($sql)));
    }

    public function getOperator(?string $operator): string
    {
        return $operator ? "{$operator} " : '';
    }
}
