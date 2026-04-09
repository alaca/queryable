<?php

namespace Queryable\Clauses;

class Having
{
    public string $column;
    public string $comparisonOperator;
    public string|int|float $value;
    public ?string $logicalOperator;
    public ?string $mathFunction;

    public function __construct(string $column, string $comparisonOperator, string|int|float $value, ?string $logicalOperator, ?string $mathFunction = null)
    {
        $this->column = $column;
        $this->comparisonOperator = $comparisonOperator;
        $this->value = $value;
        $this->logicalOperator = $logicalOperator;
        $this->mathFunction = $mathFunction;
    }
}
