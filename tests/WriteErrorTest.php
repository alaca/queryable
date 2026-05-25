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
}
