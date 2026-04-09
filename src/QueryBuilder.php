<?php

namespace Queryable;

use Closure;
use Queryable\Clauses\{RawSQL, Table};
use Queryable\Concerns\{
    ExecutesQueries,
    HasHaving,
    HasJoin,
    HasMeta,
    HasMutations,
    HasOrdering,
    HasRelations,
    HasSelect,
    HasWhere,
};
use RuntimeException;

/**
 * Fluent SQL query builder
 */
class QueryBuilder
{
    use HasSelect;
    use HasWhere;
    use HasJoin;
    use HasHaving;
    use HasMeta;
    use HasMutations;
    use HasOrdering;
    use HasRelations;
    use ExecutesQueries;

    private QueryCompiler $compiler;
    private array $schema = [];

    protected array $query = [
        'type' => null,
        'select' => [],
        'data' => [],
        'upsertConflict' => [],
        'upsertUpdate' => [],
        'table' => [],
        'where' => [],
        'join' => [],
        'having' => [],
        'groupBy' => [],
        'orderBy' => [],
        'union' => [],
        'distinct' => false,
        'limit' => null,
        'offset' => null,
    ];

    public function __construct(array $schema = [])
    {
        $this->compiler = new QueryCompiler($this->query);
        $this->schema = $schema;
    }

    /**
     * Set the target table. Pass a Closure for subquery in FROM.
     */
    public function table(string|Closure $table, ?string $alias = null): static
    {
        if ($table instanceof Closure) {
            $subBuilder = new QueryBuilder();
            $table($subBuilder);
            $this->query['table'][] = new RawSQL("({$subBuilder->toSQL()}) AS {$alias}");
        } else {
            $this->query['table'][] = new Table($table, $alias);
        }

        return $this;
    }

    /**
     * Apply clauses only when condition is true
     */
    public function when(mixed $condition, Closure $callback): static
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Deep copy. The cloned builder is independent
     */
    public function clone(): static
    {
        $cloned = new static($this->schema);

        foreach ($this->query as $key => $value) {
            if (is_array($value)) {
                $cloned->query[$key] = [];
                foreach ($value as $item) {
                    $cloned->query[$key][] = is_object($item) ? clone $item : $item;
                }
            } else {
                $cloned->query[$key] = $value;
            }
        }

        $cloned->compiler = new QueryCompiler($cloned->query);
        $cloned->eagerLoad = $this->eagerLoad;

        return $cloned;
    }


    public function toSQL(): string
    {
        return $this->compiler->compile();
    }

    /**
     * Locks the query to one type (SELECT, INSERT, UPDATE, DELETE, TRUNCATE)
     * Prevents calling select() then insert() on the same builder
     */
    protected function setQueryType(string $type): void
    {
        if ($this->query['type'] && $this->query['type'] !== $type) {
            throw new RuntimeException('Query type already set');
        }
        $this->query['type'] = $type;
    }
}
