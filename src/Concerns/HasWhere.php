<?php

namespace Queryable\Concerns;

use Closure;
use Queryable\Clauses\{RawSQL, Where};
use Queryable\QueryBuilder;

trait HasWhere
{
    private function setWhere(string|Closure $column, mixed $value, string $comparisonOperator, string $logicalOperator): static
    {
        if ($column instanceof Closure) {
            $builder = new QueryBuilder();
            $column($builder);
            $op = !empty($this->query['where']) ? $this->compiler->getOperator($logicalOperator) : '';
            $this->query['where'][] = "{$op}({$builder->compiler->compileWhere(true)})";
        } elseif ($value instanceof Closure) {
            $builder = new QueryBuilder();
            $value($builder);
            $op = !empty($this->query['where']) ? $this->compiler->getOperator($logicalOperator) : '';
            $this->query['where'][] = "{$op}{$column} {$comparisonOperator} ({$builder->toSQL()})";
        } else {
            $this->query['where'][] = new Where(
                $this->resolveColumn($column),
                $value,
                $comparisonOperator,
                !empty($this->query['where']) ? $logicalOperator : null,
            );
        }

        return $this;
    }

    public function where(string|Closure $column, mixed $value = null, string $operator = '='): static
    {
        return $this->setWhere($column, $value, $operator, 'AND');
    }

    public function orWhere(string|Closure $column, mixed $value = null, string $operator = '='): static
    {
        return $this->setWhere($column, $value, $operator, 'OR');
    }

    public function whereLike(string $column, string $value): static
    {
        return $this->where($column, $value, 'LIKE');
    }

    public function orWhereLike(string $column, string $value): static
    {
        return $this->orWhere($column, $value, 'LIKE');
    }

    public function whereNotLike(string $column, string $value): static
    {
        return $this->where($column, $value, 'NOT LIKE');
    }

    public function orWhereNotLike(string $column, string $value): static
    {
        return $this->orWhere($column, $value, 'NOT LIKE');
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        return $this->setWhere($column, [$min, $max], 'BETWEEN', 'AND');
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): static
    {
        return $this->setWhere($column, [$min, $max], 'NOT BETWEEN', 'AND');
    }

    public function orWhereBetween(string $column, mixed $min, mixed $max): static
    {
        return $this->setWhere($column, [$min, $max], 'BETWEEN', 'OR');
    }

    public function orWhereNotBetween(string $column, mixed $min, mixed $max): static
    {
        return $this->setWhere($column, [$min, $max], 'NOT BETWEEN', 'OR');
    }

    public function whereIn(string $column, array|Closure $value): static
    {
        return $this->setWhere($column, $value, 'IN', 'AND');
    }


    public function orWhereIn(string $column, array|Closure $value): static
    {
        return $this->setWhere($column, $value, 'IN', 'OR');
    }

    public function whereNotIn(string $column, array|Closure $value): static
    {
        return $this->setWhere($column, $value, 'NOT IN', 'AND');
    }

    public function orWhereNotIn(string $column, array|Closure $value): static
    {
        return $this->setWhere($column, $value, 'NOT IN', 'OR');
    }

    public function whereIsNull(string $column): static
    {
        return $this->where($column, null, 'IS NULL');
    }

    public function orWhereIsNull(string $column): static
    {
        return $this->orWhere($column, null, 'IS NULL');
    }

    public function whereIsNotNull(string $column): static
    {
        return $this->where($column, null, 'IS NOT NULL');
    }

    public function orWhereIsNotNull(string $column): static
    {
        return $this->orWhere($column, null, 'IS NOT NULL');
    }

    private function setWhereExists(Closure $callback, string $type, string $logicalOperator): static
    {
        $builder = new QueryBuilder();
        $callback($builder);
        $op = !empty($this->query['where']) ? $this->compiler->getOperator($logicalOperator) : '';
        $this->query['where'][] = "{$op}{$type} ({$builder->toSQL()})";

        return $this;
    }

    public function whereExists(Closure $callback): static
    {
        return $this->setWhereExists($callback, 'EXISTS', 'AND');
    }

    public function whereNotExists(Closure $callback): static
    {
        return $this->setWhereExists($callback, 'NOT EXISTS', 'AND');
    }

    public function orWhereExists(Closure $callback): static
    {
        return $this->setWhereExists($callback, 'EXISTS', 'OR');
    }

    public function orWhereNotExists(Closure $callback): static
    {
        return $this->setWhereExists($callback, 'NOT EXISTS', 'OR');
    }

    public function whereColumn(string $column1, string $column2, string $operator = '=', string $logical = 'AND'): static
    {
        $op = !empty($this->query['where']) ? $this->compiler->getOperator($logical) : '';
        $this->query['where'][] = "{$op}{$column1} {$operator} {$column2}";

        return $this;
    }

    public function orWhereColumn(string $column1, string $column2, string $operator = '='): static
    {
        return $this->whereColumn($column1, $column2, $operator, 'OR');
    }

    public function whereRaw(string $sql, mixed ...$args): static
    {
        $this->query['where'][] = new RawSQL($sql, $args);

        return $this;
    }
}
