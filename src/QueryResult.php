<?php

namespace Queryable;

class QueryResult
{
    public int $affectedRows;
    public int $insertId;

    public function __construct(int $affectedRows = 0, int $insertId = 0)
    {
        $this->affectedRows = $affectedRows;
        $this->insertId = $insertId;
    }
}
