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
        $this->assertStringContainsString('id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('PRIMARY KEY (id)', $sql);
        $this->assertStringContainsString('name VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString('email VARCHAR(255) NOT NULL UNIQUE', $sql);
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

        $this->assertStringContainsString('description TEXT NOT NULL', $sql);
        $this->assertStringContainsString('content LONGTEXT NOT NULL', $sql);
        $this->assertStringContainsString('quantity INT NOT NULL', $sql);
        $this->assertStringContainsString('views BIGINT NOT NULL', $sql);
        $this->assertStringContainsString('priority TINYINT NOT NULL', $sql);
        $this->assertStringContainsString('rating FLOAT NOT NULL', $sql);
        $this->assertStringContainsString('price DECIMAL(8,2) NOT NULL', $sql);
        $this->assertStringContainsString('active TINYINT(1) NOT NULL', $sql);
        $this->assertStringContainsString('birth_date DATE NOT NULL', $sql);
        $this->assertStringContainsString('published_at DATETIME NOT NULL', $sql);
        $this->assertStringContainsString('settings JSON NOT NULL', $sql);
        $this->assertStringContainsString("status ENUM('draft','published','archived') NOT NULL", $sql);
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

        $this->assertStringContainsString('stock INT NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString("status VARCHAR(255) NOT NULL DEFAULT 'draft'", $sql);
        $this->assertStringContainsString('deleted_at DATETIME', $sql);
        $this->assertStringNotContainsString('deleted_at DATETIME NOT NULL', $sql);
        $this->assertStringContainsString('featured TINYINT(1) NOT NULL DEFAULT 0', $sql);
    }

    public function testDatetimeColumns(): void
    {
        $table = new Table();
        $table->id();
        $table->datetime('created_at')->nullable();
        $table->datetime('updated_at')->nullable();

        $sql = $table->compile('wp_posts');

        $this->assertStringContainsString('created_at DATETIME', $sql);
        $this->assertStringContainsString('updated_at DATETIME', $sql);
    }

    public function testForeignKey(): void
    {
        $table = new Table();
        $table->id();
        $table->bigInteger('user_id')->unsigned()->references('wp_users', 'ID')->onDelete('CASCADE');

        $sql = $table->compile('wp_orders');

        $this->assertStringContainsString('user_id BIGINT UNSIGNED NOT NULL', $sql);
        $this->assertStringContainsString('FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE', $sql);
    }

    public function testUnsigned(): void
    {
        $table = new Table();
        $table->id();
        $table->integer('quantity')->unsigned();

        $sql = $table->compile('wp_items');

        $this->assertStringContainsString('quantity INT UNSIGNED NOT NULL', $sql);
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
        $this->assertStringContainsString('meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $metaSql);
        $this->assertStringContainsString('PRIMARY KEY (meta_id)', $metaSql);
        $this->assertStringContainsString('product_id BIGINT UNSIGNED NOT NULL', $metaSql);
        $this->assertStringContainsString('meta_key VARCHAR(255) NOT NULL', $metaSql);
        $this->assertStringContainsString('meta_value LONGTEXT', $metaSql);
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
        $this->assertStringContainsString('campaign_id BIGINT UNSIGNED NOT NULL', $metaSql);
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
        $this->assertStringContainsString('KEY idx_country (country)', $sql);
    }

    public function test_column_level_index_with_explicit_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('email_hash', 64)->index('uk_lookup');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY uk_lookup (email_hash)', $sql);
    }

    public function test_table_level_single_index(): void
    {
        $t = new Table();
        $t->id();
        $t->string('status', 20);
        $t->index('status');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_status (status)', $sql);
    }

    public function test_table_level_composite_index(): void
    {
        $t = new Table();
        $t->id();
        $t->string('status', 20);
        $t->datetime('paid_at');
        $t->index(['status', 'paid_at']);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_status_paid_at (status,paid_at)', $sql);
    }

    public function test_table_level_composite_index_with_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('status', 20);
        $t->datetime('paid_at');
        $t->index(['status', 'paid_at'], 'idx_pay_status');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('KEY idx_pay_status (status,paid_at)', $sql);
    }

    public function test_composite_unique_default_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('gateway', 32);
        $t->string('external_id', 128);
        $t->unique(['gateway', 'external_id']);
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('UNIQUE KEY uk_gateway_external_id (gateway,external_id)', $sql);
    }

    public function test_composite_unique_with_name(): void
    {
        $t = new Table();
        $t->id();
        $t->string('gateway', 32);
        $t->string('external_id', 128);
        $t->unique(['gateway', 'external_id'], 'uk_gw_ext');
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('UNIQUE KEY uk_gw_ext (gateway,external_id)', $sql);
    }

    public function test_column_level_unique_still_works_as_constraint(): void
    {
        $t = new Table();
        $t->id();
        $t->string('slug', 200)->unique();
        $sql = $t->compile('test_table');
        $this->assertStringContainsString('slug VARCHAR(200) NOT NULL UNIQUE', $sql);
        $this->assertStringNotContainsString('UNIQUE KEY', $sql);
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
        $this->assertStringContainsString('KEY idx_country (country)', $sql);
        $this->assertStringContainsString('KEY idx_paid_at (paid_at)', $sql);
        $this->assertStringContainsString('KEY idx_status_paid_at (status,paid_at)', $sql);
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
        $this->assertSame('JSON', $payloadDef['type']);
        $this->assertTrue($payloadDef['nullable']);
    }
}
