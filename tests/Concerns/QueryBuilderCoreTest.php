<?php

namespace Queryable\Tests\Concerns;

use Queryable\DB;

class QueryBuilderCoreTest extends QueryBuilderTestCase
{
    public function testWhen(): void
    {
        $sql = DB::table('users')
            ->select('id', 'name')
            ->when(true, fn ($qb) => $qb->where('age', 18, '>='))
            ->toSQL();

        $this->assertEquals('SELECT id, name FROM users WHERE age >= 18', $sql);
    }

    public function testWhenFalse(): void
    {
        $sql = DB::table('users')
            ->select('id', 'name')
            ->when(false, fn ($qb) => $qb->where('age', 18, '>='))
            ->toSQL();

        $this->assertEquals('SELECT id, name FROM users', $sql);
    }

    public function testClone(): void
    {
        $base = DB::table('users')->select('id', 'name');
        $filtered = $base->clone()->where('age', 18, '>=');

        $this->assertEquals('SELECT id, name FROM users', $base->toSQL());
        $this->assertEquals('SELECT id, name FROM users WHERE age >= 18', $filtered->toSQL());
    }

    public function testToSQL(): void
    {
        $this->assertEquals(
            "SELECT id, name FROM users WHERE status = 'active'",
            DB::table('users')->select('id', 'name')->where('status', 'active')->toSQL(),
        );
    }
}
