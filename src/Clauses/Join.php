<?php

namespace Queryable\Clauses;

class Join
{
    public string $type;
    public string $table;
    public ?string $alias;

    public function __construct(string $type, string $table, ?string $alias = null)
    {
        $this->type = $type;
        $this->table = trim($table);
        $this->alias = $alias ? trim($alias) : null;
    }
}
