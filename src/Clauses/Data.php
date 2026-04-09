<?php

namespace Queryable\Clauses;

class Data
{
    public string $column;
    public string|int|float $value;

    public function __construct(string $column, mixed $value)
    {
        $this->column = trim($column);
        $this->value = RawSQL::escape($value);
    }
}
