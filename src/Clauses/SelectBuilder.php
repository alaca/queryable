<?php

namespace Queryable\Clauses;

use Closure;

class SelectBuilder
{
    public string $column;
    public Closure $callback;

    public function __construct(string $column, Closure $callback)
    {
        $this->column = trim($column);
        $this->callback = $callback;
    }
}
