<?php

namespace Queryable;

use RuntimeException;

/**
 * Thrown when a query fails at the database. WordPress' $wpdb swallows SQL
 * errors (returns false, writes to the log) which lets a failed write look like
 * a success to callers; Queryable surfaces them as exceptions instead.
 */
class QueryException extends RuntimeException
{
    public string $sql;

    public function __construct(string $error, string $sql = '')
    {
        $this->sql = $sql;
        parent::__construct($error);
    }
}
