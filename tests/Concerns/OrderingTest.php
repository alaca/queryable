<?php

namespace Queryable\Tests\Concerns;

use Queryable\DB;

class OrderingTest extends QueryBuilderTestCase
{
    public function testOrderBy(): void
    {
        $this->assertEquals(
            'SELECT * FROM users ORDER BY name ASC, age DESC',
            DB::table('users')->orderBy('name')->orderBy('age', 'DESC')->toSQL(),
        );
    }

    public function testOrderByRaw(): void
    {
        $this->assertEquals(
            'SELECT * FROM users ORDER BY RAND()',
            DB::table('users')->orderByRaw('RAND()')->toSQL(),
        );
    }

    public function testGroupBy(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY status',
            DB::table('orders')->groupBy('status')->toSQL(),
        );
    }

    public function testGroupByMultiple(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY status, user_id',
            DB::table('orders')->groupBy('status', 'user_id')->toSQL(),
        );
    }

    public function testGroupByRaw(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY YEAR(created_at)',
            DB::table('orders')->groupByRaw('YEAR(created_at)')->toSQL(),
        );
    }

    public function testLimitOffset(): void
    {
        $this->assertEquals(
            'SELECT * FROM users LIMIT 10 OFFSET 20',
            DB::table('users')->limit(10)->offset(20)->toSQL(),
        );
    }

    public function testUnion(): void
    {
        $builder1 = DB::table('users')->select('id');

        $this->assertEquals(
            'SELECT id FROM orders UNION SELECT id FROM users',
            DB::table('orders')->select('id')->union($builder1)->toSQL(),
        );
    }

    public function testUnionAll(): void
    {
        $builder1 = DB::table('users')->select('id');

        $this->assertEquals(
            'SELECT id FROM orders UNION ALL SELECT id FROM users',
            DB::table('orders')->select('id')->unionAll($builder1)->toSQL(),
        );
    }
}
