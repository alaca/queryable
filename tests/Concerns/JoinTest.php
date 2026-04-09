<?php

namespace Queryable\Tests\Concerns;

use Queryable\DB;

class JoinTest extends QueryBuilderTestCase
{
    public function testLeftJoin(): void
    {
        $this->assertEquals(
            'SELECT * FROM users LEFT JOIN orders ON users.id = orders.user_id',
            DB::table('users')->leftJoin('orders', 'users.id', 'orders.user_id')->toSQL(),
        );
    }

    public function testLeftJoinAlias(): void
    {
        $this->assertEquals(
            'SELECT * FROM users LEFT JOIN orders AS o ON users.id = o.user_id',
            DB::table('users')->leftJoin('orders', 'users.id', 'o.user_id', 'o')->toSQL(),
        );
    }

    public function testRightJoin(): void
    {
        $this->assertEquals(
            'SELECT * FROM users RIGHT JOIN orders ON users.id = orders.user_id',
            DB::table('users')->rightJoin('orders', 'users.id', 'orders.user_id')->toSQL(),
        );
    }

    public function testInnerJoin(): void
    {
        $this->assertEquals(
            'SELECT * FROM users INNER JOIN orders ON users.id = orders.user_id',
            DB::table('users')->innerJoin('orders', 'users.id', 'orders.user_id')->toSQL(),
        );
    }

    public function testCrossJoin(): void
    {
        $this->assertEquals(
            'SELECT * FROM users CROSS JOIN roles',
            DB::table('users')->crossJoin('roles')->toSQL(),
        );
    }

    public function testJoinRaw(): void
    {
        $this->assertEquals(
            'SELECT * FROM users LEFT JOIN orders o ON users.id = o.user_id',
            DB::table('users')->joinRaw('LEFT JOIN orders o ON users.id = o.user_id')->toSQL(),
        );
    }

    public function testAdvancedJoin(): void
    {
        $sql = DB::table('users')
            ->join(function ($qb) {
                $qb->leftJoin('orders')
                    ->on('users.id', 'orders.user_id')
                    ->and('orders.status', 'active');
            })
            ->toSQL();

        $this->assertStringContainsString('LEFT JOIN orders', $sql);
        $this->assertStringContainsString('ON users.id = orders.user_id', $sql);
        $this->assertStringContainsString("AND orders.status = 'active'", $sql);
    }
}
