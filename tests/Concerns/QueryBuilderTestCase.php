<?php

namespace Queryable\Tests\Concerns;

use PHPUnit\Framework\TestCase;
use Queryable\DB;

abstract class QueryBuilderTestCase extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = null;
        DB::reset();
    }
}
