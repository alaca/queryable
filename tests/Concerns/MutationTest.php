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
            "INSERT INTO users (name, age) VALUES ('John', 30)",
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
            "INSERT INTO users (name, age) VALUES ('John', 30), ('Jane', 25)",
            $qb->toSQL(),
        );
    }

    public function testUpdateSQL(): void
    {
        $qb = DB::table('users')->where('id', 1);
        $qb->update(['name' => 'Bob']);

        $this->assertEquals(
            "UPDATE users SET name = 'Bob' WHERE id = 1",
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
            "INSERT INTO users (name, email) VALUES ('Alice', 'a@test.com') ON DUPLICATE KEY UPDATE name = VALUES(name)",
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
}
