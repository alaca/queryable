<?php

namespace Queryable\Tests\Concerns;

use Queryable\DB;

class SelectTest extends QueryBuilderTestCase
{
    public function testSelect(): void
    {
        $this->assertEquals(
            'SELECT id, name, age FROM users',
            DB::table('users')->select('id', 'name', 'age')->toSQL(),
        );
    }

    public function testSelectAll(): void
    {
        $this->assertEquals('SELECT * FROM users', DB::table('users')->toSQL());
    }

    public function testSelectDistinct(): void
    {
        $this->assertEquals(
            'SELECT DISTINCT name FROM users',
            DB::table('users')->distinct()->select('name')->toSQL(),
        );
    }

    public function testSelectRaw(): void
    {
        $this->assertEquals(
            'SELECT COUNT(*) as total FROM users',
            DB::table('users')->selectRaw('COUNT(*) as total')->toSQL(),
        );
    }

    public function testSelectAlias(): void
    {
        $this->assertEquals(
            'SELECT id AS user_id FROM users',
            DB::table('users')->select(['id' => 'user_id'])->toSQL(),
        );
    }

    public function testSelectFromAlias(): void
    {
        $this->assertEquals(
            'SELECT * FROM users AS u',
            DB::table('users', 'u')->toSQL(),
        );
    }

    public function testSubqueryFrom(): void
    {
        $sql = DB::table(function ($qb) {
            $qb->table('orders')->select('user_id')->selectRaw('SUM(total) as total')->groupBy('user_id');
        }, 'totals')->selectRaw('user_id, total')->toSQL();

        $this->assertEquals(
            'SELECT user_id, total FROM (SELECT user_id, SUM(total) as total FROM orders GROUP BY user_id) AS totals',
            $sql,
        );
    }
}
