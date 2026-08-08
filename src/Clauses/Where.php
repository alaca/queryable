<?php

namespace Queryable\Clauses;

class Where
{
    /**
     * Every operator a where clause accepts. Lives on the clause rather than on
     * the trait that validates against it: trait constants need PHP 8.2, and
     * this package supports 8.0.
     */
    public const OPERATORS = [
        '=', '!=', '<>', '<=>', '<', '<=', '>', '>=',
        'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN',
        'IS NULL', 'IS NOT NULL', 'EXISTS', 'NOT EXISTS',
        'REGEXP', 'NOT REGEXP', 'RLIKE',
    ];

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
