<?php

namespace Queryable\Clauses;

class Select
{
    public string $column;
    public ?string $alias;

    public function __construct(string $column, ?string $alias = null)
    {
        $this->column = trim($column);
        $this->alias = $alias ? trim($alias) : null;
    }
}
