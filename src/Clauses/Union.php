<?php

namespace Queryable\Clauses;

use Queryable\QueryBuilder;

class Union
{
    public QueryBuilder $builder;
    public bool $all;

    public function __construct(QueryBuilder $builder, bool $all = false)
    {
        $this->builder = $builder;
        $this->all = $all;
    }
}
