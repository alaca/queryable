<?php

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Schema\Table;

class TableTest extends TestCase
{
    public function testBasicTable(): void
    {
        $table = new Table();
        $table->id();
        $table->string('name');
        $table->string('email')->unique();

        $sql = $table->compile('wp_products');

        $this->assertStringContainsString('CREATE TABLE wp_products', $sql);
        $this->assertStringContainsString('`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('PRIMARY KEY  (`id`)', $sql);
        $this->assertStringContainsString('`name` varchar(255) NOT NULL', $sql);
        $this->assertStringContainsString('`email` varchar(255) NOT NULL', $sql);
        $this->assertStringContainsString('UNIQUE KEY uk_email (`email`)', $sql);
    }

    public function testColumnTypes(): void
    {
        $table = new Table();
        $table->id();
        $table->text('description');
        $table->longText('content');
        $table->integer('quantity');
        $table->bigInteger('views');
        $table->tinyInteger('priority');
        $table->float('rating');
        $table->decimal('price', 8, 2);
        $table->boolean('active');
        $table->date('birth_date');
        $table->datetime('published_at');
        $table->json('settings');
        $table->enum('status', ['draft', 'published', 'archived']);

        $sql = $table->compile('wp_items');

        $this->assertStringContainsString('`description` text NOT NULL', $sql);
        $this->assertStringContainsString('`content` longtext NOT NULL', $sql);
        $this->assertStringContainsString('`quantity` int(11) NOT NULL', $sql);
        $this->assertStringContainsString('`views` bigint(20) NOT NULL', $sql);
        $this->assertStringContainsString('`priority` tinyint(4) NOT NULL', $sql);
        $this->assertStringContainsString('`rating` float NOT NULL', $sql);
        $this->assertStringContainsString('`price` decimal(8,2) NOT NULL', $sql);
        $this->assertStringContainsString('`active` tinyint(1) NOT NULL', $sql);
        $this->assertStringContainsString('`birth_date` date NOT NULL', $sql);
        $this->assertStringContainsString('`published_at` datetime NOT NULL', $sql);
        // Stored as longtext, not json: MariaDB has no JSON type and reports
        // such a column back as longtext, so emitting json means dbDelta never
        // agrees the table is already correct.
        $this->assertStringContainsString('`settings` longtext NOT NULL', $sql);
        $this->assertStringContainsString("`status` enum('draft','published','archived') NOT NULL", $sql);
    }

    public function testNullableAndDefault(): void
    {
        $table = new Table();
        $table->id();
        $table->integer('stock')->default(0);
        $table->string('status')->default('draft');
        $table->datetime('deleted_at')->nullable();
        $table->boolean('featured')->default(false);

        $sql = $table->compile('wp_products');

        $this->assertStringContainsString('`stock` int(11) NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString("`status` varchar(255) NOT NULL DEFAULT 'draft'", $sql);
        $this->assertStringContainsString('`deleted_at` datetime', $sql);
        $this->assertStringNotContainsString('`deleted_at` datetime NOT NULL', $sql);
        $this->assertStringContainsString('`featured` tinyint(1) NOT NULL DEFAULT 0', $sql);
    }

    public function testDatetimeColumns(): void
    {
        $table = new Table();
        $table->id();
        $table->datetime('created_at')->nullable();
        $table->datetime('updated_at')->nullable();

        $sql = $table->compile('wp_posts');

        $this->assertStringContainsString('`created_at` datetime', $sql);
        $this->assertStringContainsString('`updated_at` datetime', $sql);
    }

    public function testForeignKey(): void
    {
        $table = new Table();
        $table->id();
        $table->bigInteger('user_id')->unsigned()->references('wp_users', 'ID')->onDelete('CASCADE');

        $sql = $table->compile('wp_orders');

        $this->assertStringContainsString('`user_id` bigint(20) unsigned NOT NULL', $sql);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES wp_users(ID) ON DELETE CASCADE', $sql);
    }

    public function testUnsigned(): void
    {
        $table = new Table();
        $table->id();
        $table->integer('quantity')->unsigned();

        $sql = $table->compile('wp_items');

        $this->assertStringContainsString('`quantity` int(10) unsigned NOT NULL', $sql);
    }

    public function testMetaTableDefault(): void
    {
        $table = new Table('utf8mb4', 'utf8mb4_unicode_ci', [
            'table' => 'products_meta',
            'foreignKey' => 'product_id',
            'primaryKey' => 'id',
        ]);
        $table->id();
        $table->string('name');

        $this->assertTrue($table->hasMetaConfig());

        $metaSql = $table->compileMetaTable('wp_products', 'wp_');

        $this->assertStringContainsString('CREATE TABLE wp_products_meta', $metaSql);
        $this->assertStringContainsString('meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT', $metaSql);
        $this->assertStringContainsString('PRIMARY KEY  (meta_id)', $metaSql);
        $this->assertStringContainsString('product_id bigint(20) unsigned NOT NULL', $metaSql);
        $this->assertStringContainsString('meta_key varchar(255) NOT NULL', $metaSql);
        $this->assertStringContainsString('meta_value longtext', $metaSql);
    }

    public function testMetaTableFromConfig(): void
    {
        $table = new Table('utf8mb4', 'utf8mb4_unicode_ci', [
            'table' => 'campaign_meta',
            'foreignKey' => 'campaign_id',
            'primaryKey' => 'id',
        ]);
        $table->id();
        $table->string('name');

        $metaSql = $table->compileMetaTable('wp_campaigns', 'wp_');

        $this->assertStringContainsString('CREATE TABLE wp_campaign_meta', $metaSql);
        $this->assertStringContainsString('campaign_id bigint(20) unsigned NOT NULL', $metaSql);
    }

    public function testNoMeta(): void
    {
        $table = new Table();
        $table->id();
        $table->string('name');

        $this->assertFalse($table->hasMetaConfig());
    }

    public function testRelations(): void
    {
        $table = new Table();
        $table->id();
        $table->string('name');
        $table->hasMany('reviews', 'product_id');
        $table->hasOne('detail', 'product_id');
        $table->belongsTo('categories', 'category_id');

        $relations = $table->getRelations();

        $this->assertCount(3, $relations);
        $this->assertEquals('hasMany', $relations[0]['type']);
        $this->assertEquals('reviews', $relations[0]['table']);
        $this->assertEquals('hasOne', $relations[1]['type']);
        $this->assertEquals('belongsTo', $relations[2]['type']);
    }

    public function testCharsetAndCollate(): void
    {
        $table = new Table('utf8', 'utf8_general_ci');
        $table->id();

        $sql = $table->compile('wp_test');

        $this->assertStringContainsString('DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci', $sql);
    }

    public function testDefaultCharset(): void
    {
        $table = new Table();
        $table->id();

        $sql = $table->compile('wp_test');

        $this->assertStringContainsString('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $sql);
    }

    public function test_column_level_index_with_default_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('country', 2)->index();
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_country (`country`)', $sql);
    }

    public function test_column_level_index_with_explicit_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('email_hash', 64)->index('uk_lookup');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY uk_lookup (`email_hash`)', $sql);
    }

    public function test_table_level_single_index(): void
    {
        $t = new Table();
        $t->id();
        $t->string('status', 20);
        $t->index('status');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_status (`status`)', $sql);
    }

    public function test_table_level_composite_index(): void
    {
        $t = new Table();
        $t->id();
        $t->string('status', 20);
        $t->datetime('paid_at');
        $t->index(['status', 'paid_at']);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_status_paid_at (`status`,`paid_at`)', $sql);
    }

    public function test_table_level_composite_index_with_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('status', 20);
        $t->datetime('paid_at');
        $t->index(['status', 'paid_at'], 'idx_pay_status');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_pay_status (`status`,`paid_at`)', $sql);
    }

    public function test_composite_unique_default_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('gateway', 32);
        $t->string('external_id', 128);
        $t->unique(['gateway', 'external_id']);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('UNIQUE KEY uk_gateway_external_id (`gateway`,`external_id`)', $sql);
    }

    public function test_composite_unique_with_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('gateway', 32);
        $t->string('external_id', 128);
        $t->unique(['gateway', 'external_id'], 'uk_gw_ext');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('UNIQUE KEY uk_gw_ext (`gateway`,`external_id`)', $sql);
    }

    public function test_column_level_unique_compiles_to_a_separate_unique_key(): void
    {
        $t = new Table();
        $t->id();
        $t->string('slug', 200)->unique();
        $sql = $t->compile('test_table');
        // Inline UNIQUE is invisible to dbDelta (re-adds the index every
        // migrate); it must become a named UNIQUE KEY line like composite uniques.
        $this->assertStringContainsString('`slug` varchar(200) NOT NULL', $sql);
        $this->assertStringNotContainsString('NOT NULL UNIQUE', $sql);
        $this->assertStringContainsString('UNIQUE KEY uk_slug (`slug`)', $sql);
    }

    public function test_index_name_truncation_for_very_long_columns(): void
    {
        $t = new Table();
        $t->id();
        $t->string('some_extremely_long_column_name_that_exceeds_limits_a', 50);
        $t->string('some_extremely_long_column_name_that_exceeds_limits_b', 50);
        $t->index([
            'some_extremely_long_column_name_that_exceeds_limits_a',
            'some_extremely_long_column_name_that_exceeds_limits_b',
        ]);
        $sql = $t->compile('test_table');
        preg_match('/KEY (\w+) \(/', $sql, $m);
        $this->assertNotEmpty($m[1]);
        $this->assertLessThanOrEqual(64, strlen($m[1]));
    }

    public function test_multiple_indexes_on_same_table(): void
    {
        $t = new Table();
        $t->id();
        $t->string('country', 2)->nullable()->index();
        $t->datetime('paid_at')->index();
        $t->string('status', 20);
        $t->index(['status', 'paid_at']);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_country (`country`)', $sql);
        $this->assertStringContainsString('KEY idx_paid_at (`paid_at`)', $sql);
        $this->assertStringContainsString('KEY idx_status_paid_at (`status`,`paid_at`)', $sql);
    }

    public function test_reserved_word_column_name_is_quoted(): void
    {
        $t = new Table();
        $t->id();
        $t->string('trigger', 50);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('`trigger` varchar(50) NOT NULL', $sql);
    }

    public function test_another_reserved_word_column_name_is_quoted(): void
    {
        $t = new Table();
        $t->id();
        $t->string('cursor', 50);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('`cursor` varchar(50) NOT NULL', $sql);
    }

    public function test_composite_index_over_reserved_word_columns_quotes_each_column(): void
    {
        $t = new Table();
        $t->id();
        $t->string('trigger', 20);
        $t->string('cursor', 20);
        $t->index(['trigger', 'cursor']);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_trigger_cursor (`trigger`,`cursor`)', $sql);
    }

    public function test_column_name_with_literal_backtick_is_escaped_by_doubling(): void
    {
        $t = new Table();
        $t->id();
        $t->string('weird`name', 20);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('`weird``name` varchar(20) NOT NULL', $sql);
    }

    public function test_primary_key_column_is_quoted(): void
    {
        $t = new Table();
        $t->id();
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('PRIMARY KEY  (`id`)', $sql);
    }

    public function test_foreign_key_local_column_is_quoted(): void
    {
        $t = new Table();
        $t->id();
        $t->bigInteger('order')->unsigned()->references('wp_orders', 'ID');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('FOREIGN KEY (`order`) REFERENCES wp_orders(ID)', $sql);
    }

    public function test_getColumns_exposes_column_collection(): void
    {
        $t = new Table();
        $t->id();
        $t->string('name', 100);
        $t->json('payload')->nullable();

        $cols = $t->getColumns();

        $this->assertCount(3, $cols);
        $names = array_map(fn ($c) => $c->getDefinition()['name'], $cols);
        $this->assertSame(['id', 'name', 'payload'], $names);

        $payloadDef = $cols[2]->getDefinition();
        // What it is stored as and what the model does with it are two separate
        // questions, because MariaDB cannot store a JSON type at all.
        $this->assertSame('LONGTEXT', $payloadDef['type']);
        $this->assertTrue($payloadDef['json'], 'still encoded and decoded as JSON');
        $this->assertTrue($payloadDef['nullable']);
    }
}
