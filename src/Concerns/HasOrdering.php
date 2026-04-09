<?php

namespace Queryable\Concerns;

use Queryable\Clauses\{OrderBy, RawSQL, Union};
use Queryable\QueryBuilder;

/**
 * ORDER BY, GROUP BY, LIMIT, OFFSET, UNION
 */
trait HasOrdering
{
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->query['orderBy'][] = new OrderBy($this->resolveColumn($column), $direction);

        return $this;
    }

    public function orderByRaw(string $sql, mixed ...$args): static
    {
        $this->query['orderBy'][] = new RawSQL($sql, $args);

        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $col) {
            $this->query['groupBy'][] = $this->resolveColumn(trim($col));
        }

        return $this;
    }

    public function groupByRaw(string $sql, mixed ...$args): static
    {
        $this->query['groupBy'][] = (new RawSQL($sql, $args))->sql;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->query['limit'] = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->query['offset'] = $offset;

        return $this;
    }

    public function union(QueryBuilder ...$builders): static
    {
        foreach ($builders as $builder) {
            $this->query['union'][] = new Union($builder);
        }

        return $this;
    }

    public function unionAll(QueryBuilder ...$builders): static
    {
        foreach ($builders as $builder) {
            $this->query['union'][] = new Union($builder, true);
        }

        return $this;
    }
}
