<?php

declare(strict_types=1);

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Model;
use Queryable\QueryException;
use Queryable\Schema\Table;

if (!function_exists('tests_add_filter')) {
    return;
}

class Allocator extends Model
{
    protected string $table = 'queryable_allocator';
    protected string $primaryKey = 'form_id';

    public int $form_id;
    public int $next_serial = 1;
}

Allocator::schema(static function (Table $t): void {
    $t->bigInteger('form_id')->unsigned()->primary();
    $t->bigInteger('next_serial')->unsigned()->default(1);
});

class Projection extends Model
{
    protected string $table = 'queryable_projection';

    public int $entry_id;
    public string $field_key;
    public ?string $value_key = null;
}

Projection::schema(static function (Table $t): void {
    $t->bigInteger('entry_id')->unsigned();
    $t->char('field_key', 16);
    $t->string('value_key', 100)->nullable();
    $t->primary(['entry_id', 'field_key']);
});

class Unschemad extends Model
{
    protected string $table = 'queryable_unschemad';

    public int $id;
    public string $name = '';
}

/**
 * insert() is the write path for a table whose primary key is not generated,
 * which save() refuses. These assert rows actually land, because the failure
 * being replaced reported success and wrote nothing.
 */
class ModelInsertTest extends TestCase
{
    private array $created = [];

    protected function setUp(): void
    {
        global $wpdb;

        if (! isset($wpdb)) {
            $this->markTestSkipped('Needs the WP integration env ($wpdb).');
        }

        $this->createTable(
            $wpdb->prefix . 'queryable_allocator',
            '`form_id` bigint(20) unsigned NOT NULL, `next_serial` bigint(20) unsigned NOT NULL DEFAULT 1, PRIMARY KEY (`form_id`)'
        );
        $this->createTable(
            $wpdb->prefix . 'queryable_projection',
            '`entry_id` bigint(20) unsigned NOT NULL, `field_key` char(16) NOT NULL, `value_key` varchar(100) NULL, PRIMARY KEY (`entry_id`, `field_key`)'
        );
        $this->createTable(
            $wpdb->prefix . 'queryable_unschemad',
            '`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(100) NOT NULL DEFAULT \'\', PRIMARY KEY (`id`)'
        );
    }

    protected function tearDown(): void
    {
        global $wpdb;

        foreach ($this->created as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }

        $this->created = [];
    }

    private function createTable(string $table, string $body): void
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        $wpdb->query("CREATE TABLE `{$table}` ({$body}) ENGINE=InnoDB");

        $this->created[] = $table;
    }

    public function test_insert_creates_a_row_on_a_supplied_key_table(): void
    {
        global $wpdb;

        $allocator = new Allocator();
        $allocator->form_id = 42;
        $allocator->next_serial = 1;
        $allocator->insert();

        self::assertSame(
            '1',
            $wpdb->get_var("SELECT next_serial FROM `{$wpdb->prefix}queryable_allocator` WHERE form_id = 42"),
            'a supplied primary key must reach the table, not select an update of a row that does not exist'
        );
    }

    public function test_insert_creates_a_row_on_a_composite_key_table(): void
    {
        global $wpdb;

        $value = new Projection();
        $value->entry_id = 9;
        $value->field_key = 'fld_00000000000a';
        $value->value_key = 'hello';
        $value->insert();

        self::assertSame(
            'hello',
            $wpdb->get_var("SELECT value_key FROM `{$wpdb->prefix}queryable_projection` WHERE entry_id = 9")
        );
    }

    /**
     * The primary key columns are data on these tables, so insert() must write
     * them rather than strip them the way the generated-key path does.
     */
    public function test_insert_writes_the_primary_key_columns_themselves(): void
    {
        global $wpdb;

        $value = new Projection();
        $value->entry_id = 11;
        $value->field_key = 'fld_00000000000b';
        $value->insert();

        self::assertSame(
            'fld_00000000000b',
            $wpdb->get_var("SELECT field_key FROM `{$wpdb->prefix}queryable_projection` WHERE entry_id = 11")
        );
    }

    public function test_a_repeated_composite_key_fails_loudly(): void
    {
        $first = new Projection();
        $first->entry_id = 13;
        $first->field_key = 'fld_00000000000c';
        $first->insert();

        $second = new Projection();
        $second->entry_id = 13;
        $second->field_key = 'fld_00000000000c';

        $this->expectException(QueryException::class);

        $second->insert();
    }

    /**
     * The save() refusal is an assertion about a declared schema. A model that
     * registers none declares nothing either way, so it keeps the original
     * behaviour rather than being refused on an answer it never gave.
     */
    public function test_a_model_with_no_registered_schema_still_saves(): void
    {
        global $wpdb;

        $model = new Unschemad();
        $model->name = 'kept';
        $model->save();

        self::assertSame(
            'kept',
            $wpdb->get_var("SELECT name FROM `{$wpdb->prefix}queryable_unschemad` LIMIT 1")
        );
        self::assertGreaterThan(0, $model->id, 'the generated key must still be read back onto the model');
    }
}
