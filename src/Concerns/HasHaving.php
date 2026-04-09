<?php

namespace Queryable\Concerns;

use Queryable\Clauses\{Having, RawSQL};

/**
 * HAVING clauses
 */
trait HasHaving
{
    public function having(string $column, string $operator, mixed $value, ?string $mathFunction = null): static
    {
        $this->query['having'][] = new Having($column, $operator, $value, !empty($this->query['having']) ? 'AND' : null, $mathFunction);

        return $this;
    }

    public function orHaving(string $column, string $operator, mixed $value, ?string $mathFunction = null): static
    {
        $this->query['having'][] = new Having($column, $operator, $value, !empty($this->query['having']) ? 'OR' : null, $mathFunction);

        return $this;
    }

    public function havingCount(string $column, string $operator, mixed $value): static
    {
        return $this->having($column, $operator, $value, 'COUNT');
    }

    public function orHavingCount(string $column, string $operator, mixed $value): static
    {
        return $this->orHaving($column, $operator, $value, 'COUNT');
    }

    public function havingSum(string $column, string $operator, mixed $value): static
    {
        return $this->having($column, $operator, $value, 'SUM');
    }

    public function orHavingSum(string $column, string $operator, mixed $value): static
    {
        return $this->orHaving($column, $operator, $value, 'SUM');
    }

    public function havingAvg(string $column, string $operator, mixed $value): static
    {
        return $this->having($column, $operator, $value, 'AVG');
    }

    public function orHavingAvg(string $column, string $operator, mixed $value): static
    {
        return $this->orHaving($column, $operator, $value, 'AVG');
    }

    public function havingMin(string $column, string $operator, mixed $value): static
    {
        return $this->having($column, $operator, $value, 'MIN');
    }

    public function orHavingMin(string $column, string $operator, mixed $value): static
    {
        return $this->orHaving($column, $operator, $value, 'MIN');
    }

    public function havingMax(string $column, string $operator, mixed $value): static
    {
        return $this->having($column, $operator, $value, 'MAX');
    }

    public function orHavingMax(string $column, string $operator, mixed $value): static
    {
        return $this->orHaving($column, $operator, $value, 'MAX');
    }

    public function havingRaw(string $sql, mixed ...$args): static
    {
        $this->query['having'][] = new RawSQL($sql, $args);

        return $this;
    }
}
