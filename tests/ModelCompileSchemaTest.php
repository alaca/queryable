<?php

declare(strict_types=1);

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Model;
use Queryable\Schema\Table;
use Queryable\Tests\Support\WpdbDouble;
use RuntimeException;

class CompileSchemaModel extends Model
{
    protected string $table = 'compile_schema';
}

class CompileNoSchemaModel extends Model
{
    protected string $table = 'compile_no_schema';
}

class ModelCompileSchemaTest extends TestCase
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

    public function test_it_compiles_the_table_under_the_wpdb_prefix(): void
    {
        CompileSchemaModel::schema(fn (Table $t) => $t->id());

        self::assertStringStartsWith(
            'CREATE TABLE wp_compile_schema (',
            CompileSchemaModel::compileSchema()
        );
    }

    public function test_it_applies_the_model_collation(): void
    {
        CompileSchemaModel::schema(fn (Table $t) => $t->id());

        self::assertStringContainsString(
            'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci',
            CompileSchemaModel::compileSchema()
        );
    }

    public function test_it_does_not_touch_the_database(): void
    {
        CompileSchemaModel::schema(fn (Table $t) => $t->id());
        CompileSchemaModel::compileSchema();

        self::assertSame([], $GLOBALS['queryable_dbdelta']);
        self::assertSame([], $GLOBALS['queryable_options']);
    }

    /**
     * The reason this method exists: a golden test that compiles through a
     * different path than migration proves nothing about what migration runs.
     */
    public function test_migrate_executes_exactly_what_compile_schema_returns(): void
    {
        CompileSchemaModel::schema(static function (Table $t): void {
            $t->id();
            $t->char('field_key', 16)->charset('ascii', 'ascii_bin');
            $t->index(['field_key'], 'idx_key_v1');
            $t->rowFormat('DYNAMIC');
        });

        $expected = CompileSchemaModel::compileSchema();

        $GLOBALS['wpdb']->tables = ['wp_compile_schema'];
        CompileSchemaModel::migrate();

        self::assertSame($expected . ';', $GLOBALS['queryable_dbdelta'][0]);
    }

    public function test_a_model_without_a_schema_refuses_to_compile(): void
    {
        $this->expectException(RuntimeException::class);

        CompileNoSchemaModel::compileSchema();
    }
}
