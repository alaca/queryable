<?php

namespace Queryable;

use Queryable\Clauses\{Join, JoinCondition, RawSQL};

class JoinQueryBuilder
{
    private array $joins = [];

    public function on(string $column1, string $column2, string $comparisonOperator = '=', bool $quoteValue = false): static
    {
        $this->joins[] = new JoinCondition('ON', $column1, $column2, $comparisonOperator, $quoteValue);

        return $this;
    }

    public function and(string $column1, string $column2, string $comparisonOperator = '=', bool $quoteValue = true): static
    {
        $this->joins[] = new JoinCondition('AND', $column1, $column2, $comparisonOperator, $quoteValue);

        return $this;
    }

    public function or(string $column1, string $column2, string $comparisonOperator = '=', bool $quoteValue = true): static
    {
        $this->joins[] = new JoinCondition('OR', $column1, $column2, $comparisonOperator, $quoteValue);

        return $this;
    }

    public function leftJoin(string $table, ?string $alias = null): static
    {
        $this->joins[] = new Join('LEFT', $table, $alias);

        return $this;
    }

    public function rightJoin(string $table, ?string $alias = null): static
    {
        $this->joins[] = new Join('RIGHT', $table, $alias);

        return $this;
    }

    public function innerJoin(string $table, ?string $alias = null): static
    {
        $this->joins[] = new Join('INNER', $table, $alias);

        return $this;
    }

    public function crossJoin(string $table, ?string $alias = null): static
    {
        $this->joins[] = new Join('CROSS', $table, $alias);

        return $this;
    }

    public function joinRaw(string $sql, mixed ...$args): static
    {
        $this->joins[] = new RawSQL($sql, $args);

        return $this;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }
}
