<?php

namespace Queryable\Tests\Concerns;

use Queryable\DB;

class HavingTest extends QueryBuilderTestCase
{
    public function testHavingCount(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING COUNT(id) > 5',
            DB::table('orders')->groupBy('user_id')->havingCount('id', '>', 5)->toSQL(),
        );
    }

    public function testHavingSumOrAvg(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING SUM(total) > 1000 OR AVG(total) > 200',
            DB::table('orders')->groupBy('user_id')->havingSum('total', '>', 1000)->orHavingAvg('total', '>', 200)->toSQL(),
        );
    }

    public function testHavingMin(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING MIN(total) > 10',
            DB::table('orders')->groupBy('user_id')->havingMin('total', '>', 10)->toSQL(),
        );
    }

    public function testHavingMax(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING MAX(total) < 1000',
            DB::table('orders')->groupBy('user_id')->havingMax('total', '<', 1000)->toSQL(),
        );
    }

    public function testHaving(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING total > 100',
            DB::table('orders')->groupBy('user_id')->having('total', '>', 100)->toSQL(),
        );
    }

    public function testOrHaving(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING total > 100 OR total < 10',
            DB::table('orders')->groupBy('user_id')->having('total', '>', 100)->orHaving('total', '<', 10)->toSQL(),
        );
    }

    public function testOrHavingCount(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING COUNT(id) > 5 OR COUNT(id) < 1',
            DB::table('orders')->groupBy('user_id')->havingCount('id', '>', 5)->orHavingCount('id', '<', 1)->toSQL(),
        );
    }

    public function testOrHavingMin(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING MIN(total) > 10 OR MIN(total) < 1',
            DB::table('orders')->groupBy('user_id')->havingMin('total', '>', 10)->orHavingMin('total', '<', 1)->toSQL(),
        );
    }

    public function testOrHavingMax(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING MAX(total) > 100 OR MAX(total) < 10',
            DB::table('orders')->groupBy('user_id')->havingMax('total', '>', 100)->orHavingMax('total', '<', 10)->toSQL(),
        );
    }

    public function testHavingRaw(): void
    {
        $this->assertEquals(
            'SELECT * FROM orders GROUP BY user_id HAVING COUNT(id) > 5',
            DB::table('orders')->groupBy('user_id')->havingRaw('COUNT(id) > 5')->toSQL(),
        );
    }
}
