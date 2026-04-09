<?php

namespace Queryable\Clauses;

class OrderBy
{
    public string $column;
    public string $direction;

    public function __construct(string $column, string $direction = 'ASC')
    {
        $this->column = trim($column);
        $this->direction = $direction;
    }
}
