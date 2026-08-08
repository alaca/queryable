<?php

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\DB;
use Queryable\QueryException;

/**
 * A failed write must surface as an exception, not a silently-false return:
 * $wpdb swallows SQL errors, which previously let a failed INSERT/UPDATE look
 * like success to the caller (e.g. an HTTP 200 with nothing persisted).
 */
class WriteErrorTest extends TestCase
{
    public function test_failed_insert_throws(): void
    {
        global $wpdb;
        if (! isset($wpdb)) {
            $this->markTestSkipped('Needs the WP integration env ($wpdb).');
        }

        $this->expectException(QueryException::class);
        DB::table('queryable_no_such_table_xyz')->insert(['a' => 1]);
    }

    public function test_failed_update_throws(): void
    {
        global $wpdb;
        if (! isset($wpdb)) {
            $this->markTestSkipped('Needs the WP integration env ($wpdb).');
        }

        $this->expectException(QueryException::class);
        DB::table('queryable_no_such_table_xyz')->where('id', 1)->update(['a' => 1]);
    }

    public function test_successful_select_does_not_throw(): void
    {
        global $wpdb;
        if (! isset($wpdb)) {
            $this->markTestSkipped('Needs the WP integration env ($wpdb).');
        }

        // A valid query against a real table returns rows (or none) without error.
        $rows = DB::table('users')->getAll();
        $this->assertIsArray($rows);
    }

    public function test_failed_raw_write_throws(): void
    {
        global $wpdb;
        if (! isset($wpdb)) {
            $this->markTestSkipped('Needs the WP integration env ($wpdb).');
        }

        $this->expectException(QueryException::class);
        DB::raw('INSERT INTO queryable_no_such_table_xyz (a) VALUES (1)');
    }

    /**
     * The read path too: a broken raw SELECT returned an empty rows array,
     * which the caller cannot tell from a table with nothing in it.
     */
    public function test_failed_raw_select_throws(): void
    {
        global $wpdb;
        if (! isset($wpdb)) {
            $this->markTestSkipped('Needs the WP integration env ($wpdb).');
        }

        $this->expectException(QueryException::class);
        DB::raw('SELECT * FROM queryable_no_such_table_xyz');
    }
}
