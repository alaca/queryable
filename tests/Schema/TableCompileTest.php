<?php

declare(strict_types=1);

namespace Queryable\Tests\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Queryable\Schema\Table;

final class TableCompileTest extends TestCase
{
    public function test_composite_primary_key_and_row_format(): void
    {
        $t = new Table('utf8mb4', 'utf8mb4_unicode_520_ci');
        $t->bigInteger('entry_id')->unsigned();
        $t->char('field_key', 16)->charset('ascii', 'ascii_bin');
        $t->smallInteger('row_index')->unsigned()->default(0);
        $t->smallInteger('value_index')->unsigned()->default(0);
        $t->primary(['entry_id', 'field_key', 'row_index', 'value_index']);
        $t->rowFormat('DYNAMIC');

        $sql = $t->compile('wp_fieldset_entry_values');

        self::assertStringContainsString(
            'PRIMARY KEY (`entry_id`, `field_key`, `row_index`, `value_index`)',
            $sql
        );
        self::assertStringContainsString(
            '`field_key` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            $sql
        );
        self::assertStringContainsString('`row_index` smallint(5) unsigned NOT NULL DEFAULT 0', $sql);
        self::assertStringContainsString('ROW_FORMAT=DYNAMIC', $sql);
        self::assertStringContainsString('COLLATE=utf8mb4_unicode_520_ci', $sql);
    }

    public function test_binary_column_compiles(): void
    {
        $t = new Table();
        $t->binary('content_hash', 32);

        self::assertStringContainsString('`content_hash` binary(32) NOT NULL', $t->compile('wp_x'));
    }

    public function test_composite_primary_rejects_an_unknown_column(): void
    {
        $t = new Table();
        $t->bigInteger('entry_id');

        $this->expectException(InvalidArgumentException::class);

        $t->primary(['entry_id', 'nope']);
    }

    public function test_a_composite_primary_key_replaces_the_column_level_one(): void
    {
        $t = new Table();
        $t->id();
        $t->char('field_key', 16);
        $t->primary(['id', 'field_key']);

        $sql = $t->compile('wp_x');

        self::assertSame(1, substr_count($sql, 'PRIMARY KEY'));
        self::assertStringContainsString('PRIMARY KEY (`id`, `field_key`)', $sql);
    }

    public function test_a_composite_key_that_omits_an_auto_increment_column_throws(): void
    {
        $t = new Table();
        $t->id();
        $t->char('field_key', 16);
        $t->primary(['field_key']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('id');

        $t->compile('wp_x');
    }

    public function test_the_auto_increment_guard_fires_when_the_key_is_declared_first(): void
    {
        $t = new Table();
        $t->char('field_key', 16);
        $t->primary(['field_key']);
        $t->id();

        $this->expectException(InvalidArgumentException::class);

        $t->compile('wp_x');
    }

    public function test_explicit_lengths_survive_canonicalisation(): void
    {
        $t = new Table();
        $t->char('a', 16);
        $t->binary('b', 32);
        $t->smallInteger('c')->unsigned();
        $t->smallInteger('d');

        $sql = $t->compile('wp_x');

        self::assertStringContainsString('`a` char(16) NOT NULL', $sql);
        self::assertStringContainsString('`b` binary(32) NOT NULL', $sql);
        self::assertStringContainsString('`c` smallint(5) unsigned NOT NULL', $sql);
        self::assertStringContainsString('`d` smallint(6) NOT NULL', $sql);
    }

    public function test_charset_sits_between_the_type_and_the_null_constraint(): void
    {
        $t = new Table();
        $t->string('status', 20)->charset('ascii', 'ascii_bin')->default('draft');
        $t->string('projection_state', 16)->charset('ascii');

        $sql = $t->compile('wp_x');

        self::assertStringContainsString(
            "`status` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft'",
            $sql
        );
        self::assertStringContainsString('`projection_state` varchar(16) CHARACTER SET ascii NOT NULL', $sql);
    }

    public function test_collation_can_be_set_after_construction(): void
    {
        $t = new Table();
        $t->id();
        $t->collation('utf8mb4', 'utf8mb4_unicode_520_ci');

        $sql = $t->compile('wp_x');

        self::assertStringContainsString('DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci', $sql);
    }

    public function test_model_collation_overrides_wpdb(): void
    {
        $t = new Table('utf8mb4', 'utf8mb4_unicode_520_ci');
        $t->id();

        self::assertStringContainsString('COLLATE=utf8mb4_unicode_520_ci', $t->compile('wp_x'));
    }

    public function test_row_format_is_absent_unless_declared(): void
    {
        $t = new Table();
        $t->id();

        self::assertStringNotContainsString('ROW_FORMAT', $t->compile('wp_x'));
    }
}
