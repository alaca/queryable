<?php

namespace Queryable\Tests\Concerns;

use Queryable\DB;

class WhereTest extends QueryBuilderTestCase
{
    public function testWhere(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE id = 10',
            DB::table('users')->where('id', 10)->toSQL(),
        );
    }

    public function testWhereAnd(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE id = 10 AND status = 'active'",
            DB::table('users')->where('id', 10)->where('status', 'active')->toSQL(),
        );
    }

    public function testWhereOr(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE status = 'active' OR status = 'pending'",
            DB::table('users')->where('status', 'active')->orWhere('status', 'pending')->toSQL(),
        );
    }

    public function testWhereLike(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE name LIKE '%john%'",
            DB::table('users')->whereLike('name', 'john')->toSQL(),
        );
    }

    public function testWhereBetween(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE age BETWEEN 18 AND 65',
            DB::table('users')->whereBetween('age', 18, 65)->toSQL(),
        );
    }

    public function testWhereIn(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE id IN (1, 2, 3)',
            DB::table('users')->whereIn('id', [1, 2, 3])->toSQL(),
        );
    }

    public function testWhereNotIn(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE status NOT IN ('banned', 'suspended')",
            DB::table('users')->whereNotIn('status', ['banned', 'suspended'])->toSQL(),
        );
    }

    public function testWhereIsNull(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE email IS NULL',
            DB::table('users')->whereIsNull('email')->toSQL(),
        );
    }

    public function testWhereIsNotNull(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE email IS NOT NULL',
            DB::table('users')->whereIsNotNull('email')->toSQL(),
        );
    }

    public function testWhereNested(): void
    {
        $sql = DB::table('users')
            ->where('status', 'active')
            ->orWhere(function ($qb) {
                $qb->where('role', 'admin')->where('verified', 1);
            })
            ->toSQL();

        $this->assertEquals(
            "SELECT * FROM users WHERE status = 'active' OR (role = 'admin' AND verified = 1)",
            $sql,
        );
    }

    public function testWhereSubquery(): void
    {
        $sql = DB::table('users')
            ->whereIn('id', function ($qb) {
                $qb->table('orders')->select('user_id')->where('total', 100, '>');
            })
            ->toSQL();

        $this->assertEquals(
            'SELECT * FROM users WHERE id IN (SELECT user_id FROM orders WHERE total > 100)',
            $sql,
        );
    }

    public function testWhereExists(): void
    {
        $sql = DB::table('users')
            ->select('id', 'name')
            ->whereExists(function ($qb) {
                $qb->table('orders')->select('id')->whereRaw('orders.user_id = users.id');
            })
            ->toSQL();

        $this->assertEquals(
            'SELECT id, name FROM users WHERE EXISTS (SELECT id FROM orders WHERE orders.user_id = users.id)',
            $sql,
        );
    }

    public function testWhereColumn(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE users.id = orders.user_id',
            DB::table('users')->whereColumn('users.id', 'orders.user_id')->toSQL(),
        );
    }

    public function testWhereRaw(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE age > 18',
            DB::table('users')->whereRaw('age > 18')->toSQL(),
        );
    }

    public function testOrWhereLike(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE name LIKE '%john%' OR name LIKE '%jane%'",
            DB::table('users')->whereLike('name', 'john')->orWhereLike('name', 'jane')->toSQL(),
        );
    }

    public function testWhereNotLike(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE name NOT LIKE '%admin%'",
            DB::table('users')->whereNotLike('name', 'admin')->toSQL(),
        );
    }

    public function testOrWhereNotLike(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE name NOT LIKE '%a%' OR name NOT LIKE '%b%'",
            DB::table('users')->whereNotLike('name', 'a')->orWhereNotLike('name', 'b')->toSQL(),
        );
    }

    public function testWhereNotBetween(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE age NOT BETWEEN 18 AND 65',
            DB::table('users')->whereNotBetween('age', 18, 65)->toSQL(),
        );
    }

    public function testOrWhereBetween(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE age BETWEEN 18 AND 30 OR age BETWEEN 50 AND 65',
            DB::table('users')->whereBetween('age', 18, 30)->orWhereBetween('age', 50, 65)->toSQL(),
        );
    }

    public function testOrWhereNotBetween(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE age BETWEEN 18 AND 65 OR age NOT BETWEEN 80 AND 100',
            DB::table('users')->whereBetween('age', 18, 65)->orWhereNotBetween('age', 80, 100)->toSQL(),
        );
    }

    public function testOrWhereIn(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE id IN (1, 2) OR id IN (5, 6)',
            DB::table('users')->whereIn('id', [1, 2])->orWhereIn('id', [5, 6])->toSQL(),
        );
    }

    public function testOrWhereNotIn(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE status IN ('active') OR status NOT IN ('banned')",
            DB::table('users')->whereIn('status', ['active'])->orWhereNotIn('status', ['banned'])->toSQL(),
        );
    }

    public function testOrWhereIsNull(): void
    {
        $this->assertEquals(
            "SELECT * FROM users WHERE name = 'test' OR email IS NULL",
            DB::table('users')->where('name', 'test')->orWhereIsNull('email')->toSQL(),
        );
    }

    public function testOrWhereIsNotNull(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE email IS NULL OR name IS NOT NULL',
            DB::table('users')->whereIsNull('email')->orWhereIsNotNull('name')->toSQL(),
        );
    }

    public function testWhereNotExists(): void
    {
        $sql = DB::table('users')
            ->whereNotExists(fn ($qb) => $qb->table('orders')->select('id')->whereRaw('orders.user_id = users.id'))
            ->toSQL();

        $this->assertStringContainsString('NOT EXISTS', $sql);
    }

    public function testOrWhereExists(): void
    {
        $sql = DB::table('users')
            ->where('status', 'active')
            ->orWhereExists(fn ($qb) => $qb->table('orders')->select('id')->whereRaw('orders.user_id = users.id'))
            ->toSQL();

        $this->assertStringContainsString('OR EXISTS', $sql);
    }

    public function testOrWhereNotExists(): void
    {
        $sql = DB::table('users')
            ->where('status', 'active')
            ->orWhereNotExists(fn ($qb) => $qb->table('orders')->select('id')->whereRaw('orders.user_id = users.id'))
            ->toSQL();

        $this->assertStringContainsString('OR NOT EXISTS', $sql);
    }

    public function testOrWhereColumn(): void
    {
        $this->assertEquals(
            'SELECT * FROM users WHERE users.id = orders.user_id OR users.email = orders.email',
            DB::table('users')->whereColumn('users.id', 'orders.user_id')->orWhereColumn('users.email', 'orders.email')->toSQL(),
        );
    }
}
