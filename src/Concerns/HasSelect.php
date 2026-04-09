<?php

namespace Queryable\Concerns;

use Closure;
use Queryable\Clauses\{RawSQL, Select, SelectBuilder};

trait HasSelect
{
    public function select(string|array ...$columns): static
    {
        $this->setQueryType('SELECT');

        foreach ($columns as $column) {
            if (is_array($column)) {
                foreach ($column as $name => $value) {
                    if ($value instanceof Closure) {
                        $this->query['select'][] = new SelectBuilder($name, $value);
                    } else {
                        $this->query['select'][] = new Select($name, $value);
                    }
                }
            } else {
                $this->query['select'][] = new Select($column);
            }
        }

        return $this;
    }

    public function selectRaw(string $sql, mixed ...$args): static
    {
        $this->setQueryType('SELECT');
        $this->query['select'][] = new RawSQL($sql, $args);

        return $this;
    }

    public function distinct(): static
    {
        $this->query['distinct'] = true;

        return $this;
    }
}
