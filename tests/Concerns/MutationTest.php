<?php

namespace Queryable\Tests\Concerns;

use Queryable\DB;

class MutationTest extends QueryBuilderTestCase
{
    public function testInsertSQL(): void
    {
        $qb = DB::table('users');
        $qb->insert(['name' => 'John', 'age' => 30]);

        $this->assertEquals(
            "INSERT INTO users (`name`, `age`) VALUES ('John', 30)",
            $qb->toSQL(),
        );
    }

    public function testBulkInsertSQL(): void
    {
        $qb = DB::table('users');
        $qb->insert([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ]);

        $this->assertEquals(
            "INSERT INTO users (`name`, `age`) VALUES ('John', 30), ('Jane', 25)",
            $qb->toSQL(),
        );
    }

    public function testUpdateSQL(): void
    {
        $qb = DB::table('users')->where('id', 1);
        $qb->update(['name' => 'Bob']);

        $this->assertEquals(
            "UPDATE users SET `name` = 'Bob' WHERE id = 1",
            $qb->toSQL(),
        );
    }

    public function testDeleteSQL(): void
    {
        $qb = DB::table('users')->where('id', 1);
        $qb->delete();

        $this->assertEquals('DELETE FROM users WHERE id = 1', $qb->toSQL());
    }

    public function testTruncateSQL(): void
    {
        $qb = DB::table('users');
        $qb->truncate();

        $this->assertEquals('TRUNCATE TABLE users', $qb->toSQL());
    }

    public function testUpsertSQL(): void
    {
        $qb = DB::table('users');
        $qb->upsert(
            ['name' => 'Alice', 'email' => 'a@test.com'],
            ['email'],
            ['name'],
        );

        $this->assertEquals(
            "INSERT INTO users (`name`, `email`) VALUES ('Alice', 'a@test.com') ON DUPLICATE KEY UPDATE name = VALUES(name)",
            $qb->toSQL(),
        );
    }

    public function testIncrementSQL(): void
    {
        $qb = DB::table('users')->where('id', 1);
        $qb->increment('age', 5);

        $this->assertEquals(
            'UPDATE users SET age = age + 5 WHERE id = 1',
            $qb->toSQL(),
        );
    }

    public function testDecrementSQL(): void
    {
        $qb = DB::table('users')->where('id', 1);
        $qb->decrement('stock', 3);

        $this->assertEquals(
            'UPDATE users SET stock = stock - 3 WHERE id = 1',
            $qb->toSQL(),
        );
    }

    public function testInsertRawSQL(): void
    {
        $qb = DB::table('users');
        $qb->insertRaw("(name, age) VALUES ('John', 30)");

        $this->assertEquals(
            "INSERT INTO users (name, age) VALUES ('John', 30)",
            $qb->toSQL(),
        );
    }

    public function testUpdateRawSQL(): void
    {
        $qb = DB::table('users')->where('id', 1);
        $qb->updateRaw("name = 'Bob', age = age + 1");

        $this->assertEquals(
            "UPDATE users SET name = 'Bob', age = age + 1 WHERE id = 1",
            $qb->toSQL(),
        );
    }

    public function testUpdateWithLimit(): void
    {
        $qb = DB::table('users')->where('active', 0)->limit(100);
        $qb->update(['archived' => 1]);

        $this->assertEquals(
            'UPDATE users SET `archived` = 1 WHERE active = 0 LIMIT 100',
            $qb->toSQL(),
        );
    }

    public function testUpdateWithOrderByAndLimit(): void
    {
        $qb = DB::table('users')->where('active', 0)->orderBy('id')->limit(100);
        $qb->update(['archived' => 1]);

        $this->assertEquals(
            'UPDATE users SET `archived` = 1 WHERE active = 0 ORDER BY id ASC LIMIT 100',
            $qb->toSQL(),
        );
    }

    public function testDeleteWithLimit(): void
    {
        $qb = DB::table('audit_log')->where('created_at', '2020-01-01', '<')->limit(1000);
        $qb->delete();

        $this->assertEquals(
            "DELETE FROM audit_log WHERE created_at < '2020-01-01' LIMIT 1000",
            $qb->toSQL(),
        );
    }

    public function testDeleteWithOrderByAndLimit(): void
    {
        $qb = DB::table('audit_log')->where('created_at', '2020-01-01', '<')->orderBy('id')->limit(1000);
        $qb->delete();

        $this->assertEquals(
            "DELETE FROM audit_log WHERE created_at < '2020-01-01' ORDER BY id ASC LIMIT 1000",
            $qb->toSQL(),
        );
    }

    public function test_insert_column_list_quotes_a_reserved_word_column(): void
    {
        $qb = DB::table('users');
        $qb->insert(['cursor' => 'abc123']);

        $this->assertEquals(
            "INSERT INTO users (`cursor`) VALUES ('abc123')",
            $qb->toSQL(),
        );
    }

    public function test_update_set_clause_quotes_a_reserved_word_column(): void
    {
        $qb = DB::table('users')->where('id', 1);
        $qb->update(['trigger' => 'armed']);

        $this->assertEquals(
            "UPDATE users SET `trigger` = 'armed' WHERE id = 1",
            $qb->toSQL(),
        );
    }

    /**
     * Scope guard: only the INSERT column list and UPDATE SET clause are
     * quoted. SELECT/WHERE identifiers must stay untouched, so callers can
     * still pass qualified names (t.col) and expressions through them.
     */
    public function test_select_and_where_identifiers_stay_unquoted(): void
    {
        $qb = DB::table('users')->select('trigger')->where('cursor', 'abc');

        $this->assertEquals(
            "SELECT trigger FROM users WHERE cursor = 'abc'",
            $qb->toSQL(),
        );
    }
}
