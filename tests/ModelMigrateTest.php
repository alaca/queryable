<?php

declare(strict_types=1);

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Model;
use Queryable\Schema\Table;
use Queryable\Tests\Support\WpdbDouble;

class CreatedTableModel extends Model
{
    protected string $table = 'created_table';
}

class FailedCreateModel extends Model
{
    protected string $table = 'failed_create';
}

class ModelMigrateTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/stubs/wp-functions.php';
    }

    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new WpdbDouble();
        $GLOBALS['queryable_options'] = [];
        $GLOBALS['queryable_dbdelta'] = [];
    }

    public function test_a_created_table_records_the_version(): void
    {
        $GLOBALS['wpdb']->tables = ['wp_created_table'];

        CreatedTableModel::schema(fn (Table $t) => $t->id());
        CreatedTableModel::migrate();

        self::assertSame('1.0.0', $GLOBALS['queryable_options']['queryable_created_table_version'] ?? null);
    }

    public function test_a_failed_create_does_not_latch_as_migrated(): void
    {
        FailedCreateModel::schema(fn (Table $t) => $t->id());
        FailedCreateModel::migrate();

        self::assertSame([], $GLOBALS['queryable_options']);
    }

    public function test_a_failed_create_is_retried_on_the_next_run(): void
    {
        FailedCreateModel::schema(fn (Table $t) => $t->id());
        FailedCreateModel::migrate();
        FailedCreateModel::migrate();

        self::assertCount(2, $GLOBALS['queryable_dbdelta']);
    }
}
