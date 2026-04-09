<?php

namespace Queryable\Clauses;

class Where
{
    public string $column;
    public mixed $value;
    public string $comparisonOperator;
    public ?string $logicalOperator;

    public function __construct(string $column, mixed $value, string $comparisonOperator, ?string $logicalOperator)
    {
        $this->column = $column;
        $this->value = $value;
        $this->comparisonOperator = $comparisonOperator;
        $this->logicalOperator = $logicalOperator;
    }
}
