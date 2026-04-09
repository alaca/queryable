<?php

namespace Queryable\Clauses;

class JoinCondition
{
    public string $logicalOperator;
    public string $column1;
    public string $column2;
    public string $comparisonOperator;
    public bool $quote;

    public function __construct(string $logicalOperator, string $column1, string $column2, string $comparisonOperator = '=', bool $quote = false)
    {
        $this->logicalOperator = $logicalOperator;
        $this->column1 = trim($column1);
        $this->column2 = $column2;
        $this->comparisonOperator = $comparisonOperator;
        $this->quote = $quote;
    }
}
