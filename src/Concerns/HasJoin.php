<?php

namespace Queryable\Concerns;

use Closure;
use Queryable\Clauses\RawSQL;
use Queryable\JoinQueryBuilder;

/**
 * JOIN methods
 */
trait HasJoin
{
    public function join(Closure $callback): static
    {
        $this->query['join'][] = $callback;

        return $this;
    }

    public function leftJoin(string $table, string $primaryKey, string $foreignKey, ?string $alias = null): static
    {
        return $this->join(fn (JoinQueryBuilder $b) => $b->leftJoin($table, $alias)->on($primaryKey, $foreignKey));
    }

    public function rightJoin(string $table, string $primaryKey, string $foreignKey, ?string $alias = null): static
    {
        return $this->join(fn (JoinQueryBuilder $b) => $b->rightJoin($table, $alias)->on($primaryKey, $foreignKey));
    }

    public function innerJoin(string $table, string $primaryKey, string $foreignKey, ?string $alias = null): static
    {
        return $this->join(fn (JoinQueryBuilder $b) => $b->innerJoin($table, $alias)->on($primaryKey, $foreignKey));
    }

    public function crossJoin(string $table, ?string $alias = null): static
    {
        return $this->join(fn (JoinQueryBuilder $b) => $b->crossJoin($table, $alias));
    }

    public function joinRaw(string $sql, mixed ...$args): static
    {
        $this->query['join'][] = new RawSQL($sql, $args);

        return $this;
    }
}
