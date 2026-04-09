<?php

namespace Queryable\Clauses;

class Table
{
    public string $name;
    public ?string $alias;

    public function __construct(string $name, ?string $alias = null)
    {
        $this->name = trim($name);
        $this->alias = $alias ? trim($alias) : null;
    }
}
