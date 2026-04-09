<?php

namespace Queryable;

use Closure;

/**
 * @method $this select(string ...$columns)
 * @method $this selectRaw(string $sql, mixed ...$args)
 * @method $this distinct()
 * @method $this where(string|Closure $column, mixed $value = null, string $operator = '=')
 * @method $this orWhere(string|Closure $column, mixed $value = null, string $operator = '=')
 * @method $this whereLike(string $column, string $value)
 * @method $this whereNotLike(string $column, string $value)
 * @method $this whereIn(string $column, array|Closure $value)
 * @method $this whereNotIn(string $column, array|Closure $value)
 * @method $this whereBetween(string $column, mixed $min, mixed $max)
 * @method $this whereNotBetween(string $column, mixed $min, mixed $max)
 * @method $this whereIsNull(string $column)
 * @method $this whereIsNotNull(string $column)
 * @method $this whereExists(Closure $callback)
 * @method $this whereNotExists(Closure $callback)
 * @method $this whereRaw(string $sql, mixed ...$args)
 * @method $this whereColumn(string $col1, string $col2, string $op = '=')
 * @method $this leftJoin(string $table, string $pk, string $fk, ?string $alias = null)
 * @method $this rightJoin(string $table, string $pk, string $fk, ?string $alias = null)
 * @method $this innerJoin(string $table, string $pk, string $fk, ?string $alias = null)
 * @method $this crossJoin(string $table, ?string $alias = null)
 * @method $this joinRaw(string $sql, mixed ...$args)
 * @method $this orderBy(string $column, string $direction = 'ASC')
 * @method $this orderByRaw(string $sql, mixed ...$args)
 * @method $this groupBy(string ...$columns)
 * @method $this groupByRaw(string $sql, mixed ...$args)
 * @method $this limit(int $limit)
 * @method $this offset(int $offset)
 * @method $this having(string $column, string $op, mixed $value, ?string $fn = null)
 * @method $this havingCount(string $column, string $op, mixed $value)
 * @method $this havingSum(string $column, string $op, mixed $value)
 * @method $this havingAvg(string $column, string $op, mixed $value)
 * @method $this havingMin(string $column, string $op, mixed $value)
 * @method $this havingMax(string $column, string $op, mixed $value)
 * @method $this havingRaw(string $sql, mixed ...$args)
 * @method $this withMeta(string ...$keys)
 * @method $this with(string ...$relations)
 * @method $this when(mixed $condition, Closure $callback)
 * @method $this union(QueryBuilder ...$builders)
 * @method $this unionAll(QueryBuilder ...$builders)
 * @method bool exists()
 * @method array pluck(string $column)
 * @method int count(string $column = 'id')
 * @method float sum(string $column)
 * @method float avg(string $column)
 * @method float min(string $column)
 * @method float max(string $column)
 * @method QueryResult insert(array $data)
 * @method QueryResult update(array $data)
 * @method QueryResult delete()
 * @method QueryResult truncate()
 * @method QueryResult increment(string $column, int $amount = 1)
 * @method QueryResult decrement(string $column, int $amount = 1)
 * @method QueryResult upsert(array $data, array $conflictCols, array $updateCols)
 * @method string toSQL()
 */
class ModelQueryBuilder
{
    private QueryBuilder $builder;
    private string $modelClass;

    public function __construct(QueryBuilder $builder, string $modelClass)
    {
        $this->builder = $builder;
        $this->modelClass = $modelClass;
    }

    public function __call(string $method, array $args): mixed
    {
        $result = $this->builder->$method(...$args);

        // keep the chain
        if ($result === $this->builder) {
            return $this;
        }

        $class = $this->modelClass;

        return match ($method) {
            'get', 'find' => $result ? $class::fromRow($result) : null,
            'getAll' => array_map(fn ($row) => $class::fromRow($row), $result),
            default => $result,
        };
    }
}

