<?php

namespace Queryable\Tests;

use Queryable\DB;
use Queryable\Model;
use Queryable\Schema\Table;

if (!function_exists('tests_add_filter')) {
    return;
}

class Campaign extends Model
{
    protected string $table = 'campaigns';
    protected string $version = '1.0.0';

    public int $id;
    public string $name;
    public string $slug;
    public float $price;
    public int $stock;
    public ?string $created_at = null;

    // Meta properties
    public ?string $budget = null;
    public ?string $channel = null;
    public ?array $tags = null;

    protected function meta(): array
    {
        return [
            'table' => 'campaign_meta',
            'foreignKey' => 'campaign_id',
            'primaryKey' => 'id',
            'aliases' => [
                'budget' => '_campaign_budget',
                'channel' => '_campaign_channel',
                'tags' => [
                    'key' => '_campaign_tags',
                    'multiple' => true,
                ],
            ],
        ];
    }

    protected function relations(): array
    {
        return [
            'entries' => [
                'table' => 'campaign_entries',
                'foreignKey' => 'campaign_id',
                'primaryKey' => 'id',
                'type' => 'hasMany',
            ],
        ];
    }
}

Campaign::schema(function (Table $table) {
    $table->id();
    $table->string('name');
    $table->string('slug', 100)->unique();
    $table->decimal('price', 8, 2)->default(0);
    $table->integer('stock')->default(0);
    $table->datetime('created_at')->nullable();
});

class CampaignEntry extends Model
{
    protected string $table = 'campaign_entries';

    public int $id;
    public int $campaign_id;
    public string $email;
}

CampaignEntry::schema(function (Table $table) {
    $table->id();
    $table->bigInteger('campaign_id')->unsigned();
    $table->string('email');
});

class CampaignV2 extends Model
{
    protected string $table = 'campaigns';
    protected string $version = '1.1.0';

    public int $id;
    public string $name;
    public string $slug;
    public float $price;
    public int $stock;
    public ?string $description = null;
    public ?string $created_at = null;
}

CampaignV2::schema(function (Table $table) {
    $table->id();
    $table->string('name');
    $table->string('slug', 100)->unique();
    $table->decimal('price', 8, 2)->default(0);
    $table->integer('stock')->default(0);
    $table->text('description')->nullable();
    $table->datetime('created_at')->nullable();
});

class TestModelWithJson extends Model
{
    protected string $table = 'test_models_with_json';

    public int $id;
    public string $label = '';
    public ?array $payload = null;
}

TestModelWithJson::schema(function (Table $table) {
    $table->id();
    $table->string('label', 100)->default('');
    $table->json('payload')->nullable();
});

class TestModelWithJsonStringProp extends Model
{
    protected string $table = 'test_models_str_payload';

    public int $id;
    public string $label = '';
    public ?string $payload = null;
}

TestModelWithJsonStringProp::schema(function (Table $table) {
    $table->id();
    $table->string('label', 100)->default('');
    $table->json('payload')->nullable();
});

class TestModelNoJson extends Model
{
    protected string $table = 'test_models_no_json';

    public int $id;
    public string $name;
}

TestModelNoJson::schema(function (Table $table) {
    $table->id();
    $table->string('name', 100);
});

class TestCountingSchema extends Model
{
    protected string $table = 'test_counting_schemas';

    public int $id;
    public ?array $payload = null;

    public static int $callbackInvocations = 0;
}

TestCountingSchema::schema(function (Table $table) {
    TestCountingSchema::$callbackInvocations++;
    $table->id();
    $table->json('payload')->nullable();
});


class ModelTest extends \WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        // Use real tables instead of temporary tables
        remove_filter('query', [$this, '_create_temporary_tables']);
        remove_filter('query', [$this, '_drop_temporary_tables']);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        Campaign::migrate(true);
        CampaignEntry::migrate(true);
        TestModelWithJson::migrate(true);
        TestModelWithJsonStringProp::migrate(true);
        TestModelNoJson::migrate(true);
        TestCountingSchema::migrate(true);
    }

    public function tear_down(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaign_meta");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaign_entries");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaigns");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}test_models_with_json");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}test_models_str_payload");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}test_models_no_json");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}test_counting_schemas");

        parent::tear_down();
    }

    public function testGetReturnsModelInstance(): void
    {
        Campaign::query()->insert(['name' => 'Summer', 'slug' => 'summer']);

        $campaign = Campaign::query()->get();

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals('Summer', $campaign->name);
        $this->assertEquals('summer', $campaign->slug);
    }

    public function testGetAllReturnsModelInstances(): void
    {
        Campaign::query()->insert([
            ['name' => 'Summer', 'slug' => 'summer'],
            ['name' => 'Winter', 'slug' => 'winter'],
        ]);

        $campaigns = Campaign::query()->getAll();

        $this->assertCount(2, $campaigns);
        $this->assertInstanceOf(Campaign::class, $campaigns[0]);
        $this->assertInstanceOf(Campaign::class, $campaigns[1]);
        $this->assertEquals('Summer', $campaigns[0]->name);
        $this->assertEquals('Winter', $campaigns[1]->name);
    }

    public function testFindReturnsModelInstance(): void
    {
        Campaign::query()->insert(['name' => 'Summer', 'slug' => 'summer']);

        $campaign = Campaign::query()->find('slug', 'summer');

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals('Summer', $campaign->name);
    }

    public function testFindReturnsNull(): void
    {
        $this->assertNull(Campaign::query()->find('slug', 'nonexistent'));
    }

    public function testToArray(): void
    {
        Campaign::query()->insert(['name' => 'Summer', 'slug' => 'summer']);

        $campaign = Campaign::query()->find('slug', 'summer');
        $array = $campaign->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Summer', $array['name']);
    }

    public function testInsert(): void
    {
        $result = Campaign::query()->insert(['name' => 'Summer', 'slug' => 'summer']);

        $this->assertEquals(1, $result->affectedRows);
        $this->assertEquals(1, $result->insertId);
    }

    public function testBulkInsert(): void
    {
        Campaign::query()->insert([
            ['name' => 'Summer', 'slug' => 'summer'],
            ['name' => 'Winter', 'slug' => 'winter'],
        ]);

        $this->assertEquals(2, Campaign::query()->count('id'));
    }

    public function testSelectColumns(): void
    {
        Campaign::query()->insert(['name' => 'Summer', 'slug' => 'summer']);

        $campaign = Campaign::query()->select('name', 'slug')->get();

        $this->assertEquals('Summer', $campaign->name);
        $this->assertEquals('summer', $campaign->slug);
    }

    public function testWhere(): void
    {
        Campaign::query()->insert([
            ['name' => 'Active', 'slug' => 'active'],
            ['name' => 'Draft', 'slug' => 'draft'],
        ]);

        $campaigns = Campaign::query()->where('slug', 'active')->getAll();

        $this->assertCount(1, $campaigns);
        $this->assertEquals('Active', $campaigns[0]->name);
    }

    public function testWhereChain(): void
    {
        Campaign::query()->insert([
            ['name' => 'A', 'slug' => 'a'],
            ['name' => 'B', 'slug' => 'b'],
            ['name' => 'C', 'slug' => 'c'],
        ]);

        $campaigns = Campaign::query()->where('name', 'A')->orWhere('name', 'C')->getAll();

        $this->assertCount(2, $campaigns);
    }

    public function testUpdate(): void
    {
        Campaign::query()->insert(['name' => 'Old', 'slug' => 'test']);
        Campaign::query()->where('slug', 'test')->update(['name' => 'New']);

        $campaign = Campaign::query()->find('slug', 'test');
        $this->assertEquals('New', $campaign->name);
    }

    public function testDelete(): void
    {
        Campaign::query()->insert(['name' => 'Test', 'slug' => 'test']);
        Campaign::query()->where('slug', 'test')->delete();

        $this->assertEquals(0, Campaign::query()->count('id'));
    }

    public function testCount(): void
    {
        Campaign::query()->insert([
            ['name' => 'A', 'slug' => 'a'],
            ['name' => 'B', 'slug' => 'b'],
            ['name' => 'C', 'slug' => 'c'],
        ]);

        $this->assertEquals(3, Campaign::query()->count('id'));
    }

    public function testSumAvgMinMax(): void
    {
        Campaign::query()->insert([
            ['name' => 'A', 'slug' => 'a', 'price' => 10.00, 'stock' => 5],
            ['name' => 'B', 'slug' => 'b', 'price' => 20.00, 'stock' => 15],
            ['name' => 'C', 'slug' => 'c', 'price' => 30.00, 'stock' => 25],
        ]);

        $this->assertEquals(60.0, Campaign::query()->sum('price'));
        $this->assertEquals(20.0, Campaign::query()->avg('price'));
        $this->assertEquals(10.0, Campaign::query()->min('price'));
        $this->assertEquals(30.0, Campaign::query()->max('price'));
    }

    public function testExists(): void
    {
        Campaign::query()->insert(['name' => 'Test', 'slug' => 'test']);

        $this->assertTrue(Campaign::query()->where('slug', 'test')->exists());
        $this->assertFalse(Campaign::query()->where('slug', 'nope')->exists());
    }

    public function testPluck(): void
    {
        Campaign::query()->insert([
            ['name' => 'Alpha', 'slug' => 'alpha'],
            ['name' => 'Beta', 'slug' => 'beta'],
        ]);

        $this->assertEquals(['Alpha', 'Beta'], Campaign::query()->pluck('name'));
    }

    public function testOrderBy(): void
    {
        Campaign::query()->insert([
            ['name' => 'B', 'slug' => 'b'],
            ['name' => 'A', 'slug' => 'a'],
            ['name' => 'C', 'slug' => 'c'],
        ]);

        $campaigns = Campaign::query()->select('name')->orderBy('name')->getAll();

        $this->assertEquals('A', $campaigns[0]->name);
        $this->assertEquals('C', $campaigns[2]->name);
    }

    public function testLimitOffset(): void
    {
        Campaign::query()->insert([
            ['name' => 'A', 'slug' => 'a'],
            ['name' => 'B', 'slug' => 'b'],
            ['name' => 'C', 'slug' => 'c'],
        ]);

        $campaigns = Campaign::query()->select('name')->orderBy('name')->limit(2)->offset(1)->getAll();

        $this->assertCount(2, $campaigns);
        $this->assertEquals('B', $campaigns[0]->name);
    }

    public function testInsertWithMeta(): void
    {
        Campaign::query()->withMeta('budget', 'channel')->insert([
            'name' => 'Summer',
            'slug' => 'summer',
            'budget' => '5000',
            'channel' => 'email',
        ]);

        $campaign = Campaign::query()->select('id', 'name')->withMeta('budget', 'channel')->find('slug', 'summer');

        $this->assertEquals('5000', $campaign->budget);
        $this->assertEquals('email', $campaign->channel);
    }

    public function testWithMetaWithoutExplicitSelect(): void
    {
        Campaign::query()->withMeta('budget')->insert([
            'name' => 'Summer',
            'slug' => 'summer',
            'budget' => '5000',
        ]);

        $campaign = Campaign::query()->withMeta('budget')->find('slug', 'summer');

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals('Summer', $campaign->name);
        $this->assertEquals('summer', $campaign->slug);
        $this->assertEquals('5000', $campaign->budget);
        $this->assertNotEmpty($campaign->id);
    }

    public function testUpdateMeta(): void
    {
        Campaign::query()->withMeta('budget')->insert([
            'name' => 'Test',
            'slug' => 'test',
            'budget' => '5000',
        ]);

        Campaign::query()->withMeta('budget')->where('slug', 'test')->update([
            'budget' => '7500',
        ]);

        $campaign = Campaign::query()->select('id')->withMeta('budget')->find('slug', 'test');
        $this->assertEquals('7500', $campaign->budget);
    }

    public function testOrderByMeta(): void
    {
        Campaign::query()->withMeta('budget')->insert(['name' => 'Cheap', 'slug' => 'cheap', 'budget' => '100']);
        Campaign::query()->withMeta('budget')->insert(['name' => 'Expensive', 'slug' => 'expensive', 'budget' => '9999']);

        $campaigns = Campaign::query()->select('name')->withMeta('budget')->orderBy('budget', 'DESC')->getAll();

        $this->assertEquals('Expensive', $campaigns[0]->name);
        $this->assertEquals('Cheap', $campaigns[1]->name);
    }

    public function testWhereByMeta(): void
    {
        Campaign::query()->withMeta('budget')->insert(['name' => 'Low', 'slug' => 'low', 'budget' => '100']);
        Campaign::query()->withMeta('budget')->insert(['name' => 'High', 'slug' => 'high', 'budget' => '9999']);

        $campaigns = Campaign::query()->select('name')->withMeta('budget')->where('budget', 1000, '>')->getAll();

        $this->assertCount(1, $campaigns);
        $this->assertEquals('High', $campaigns[0]->name);
    }

    public function testInsertMultipleMeta(): void
    {
        Campaign::query()->withMeta('tags')->insert([
            'name' => 'Summer',
            'slug' => 'summer',
            'tags' => ['promo', 'seasonal', 'email'],
        ]);

        $campaign = Campaign::query()->select('id', 'name')
            ->withMeta('tags')
            ->groupBy('id', 'name')
            ->find('slug', 'summer');

        $this->assertIsArray($campaign->tags);
        $this->assertCount(3, $campaign->tags);
        $this->assertContains('promo', $campaign->tags);
    }

    public function testUpdateMultipleMetaReplaces(): void
    {
        Campaign::query()->withMeta('tags')->insert([
            'name' => 'Test',
            'slug' => 'test',
            'tags' => ['old1', 'old2'],
        ]);

        Campaign::query()->withMeta('tags')->where('slug', 'test')->update([
            'tags' => ['new1', 'new2', 'new3'],
        ]);

        $campaign = Campaign::query()->select('id')
            ->withMeta('tags')
            ->groupBy('id')
            ->find('slug', 'test');

        $this->assertCount(3, $campaign->tags);
        $this->assertContains('new1', $campaign->tags);
        $this->assertNotContains('old1', $campaign->tags);
    }

    public function testSingleAndMultipleMetaTogether(): void
    {
        Campaign::query()->withMeta('budget', 'tags')->insert([
            'name' => 'Mixed',
            'slug' => 'mixed',
            'budget' => '5000',
            'tags' => ['promo', 'sale'],
        ]);

        $campaign = Campaign::query()->select('id', 'name')
            ->withMeta('budget', 'tags')
            ->groupBy('id', 'name')
            ->find('slug', 'mixed');

        $this->assertEquals('5000', $campaign->budget);
        $this->assertIsArray($campaign->tags);
        $this->assertCount(2, $campaign->tags);
    }

    public function testWithHasMany(): void
    {
        Campaign::query()->insert(['name' => 'Test', 'slug' => 'test']);

        CampaignEntry::query()->insert(['campaign_id' => 1, 'email' => 'a@test.com']);
        CampaignEntry::query()->insert(['campaign_id' => 1, 'email' => 'b@test.com']);

        $campaign = Campaign::query()->select('id', 'name')->with('entries')->get();

        $this->assertCount(2, $campaign->entries);
        $this->assertEquals('a@test.com', $campaign->entries[0]['email']);
    }

    public function testWithHasManyEmpty(): void
    {
        Campaign::query()->insert(['name' => 'Empty', 'slug' => 'empty']);

        $campaign = Campaign::query()->select('id', 'name')->with('entries')->get();

        $this->assertEmpty($campaign->entries);
    }

    public function testMakeReturnsInstance(): void
    {
        $campaign = Campaign::make();

        $this->assertInstanceOf(Campaign::class, $campaign);
    }

    public function testSaveInsertsNewRecord(): void
    {
        $campaign = Campaign::make();
        $campaign->name = 'Summer';
        $campaign->slug = 'summer';
        $campaign->save();

        $this->assertNotEmpty($campaign->id);

        $found = Campaign::query()->find('id', $campaign->id);
        $this->assertInstanceOf(Campaign::class, $found);
        $this->assertEquals('Summer', $found->name);
        $this->assertEquals('summer', $found->slug);
    }

    public function testSaveUpdatesExistingRecord(): void
    {
        Campaign::query()->insert(['name' => 'Old', 'slug' => 'test']);

        $campaign = Campaign::query()->find('slug', 'test');
        $campaign->name = 'New';
        $campaign->save();

        $updated = Campaign::query()->find('slug', 'test');
        $this->assertEquals('New', $updated->name);
    }

    public function testSaveSetsInsertId(): void
    {
        $campaign = Campaign::make();
        $campaign->name = 'First';
        $campaign->slug = 'first';
        $result = $campaign->save();

        $this->assertEquals($result->insertId, $campaign->id);
    }

    public function testSaveInsertsWithMeta(): void
    {
        $campaign = Campaign::make();
        $campaign->name = 'Meta Test';
        $campaign->slug = 'meta-test';
        $campaign->budget = '5000';
        $campaign->channel = 'email';
        $campaign->save();

        $found = Campaign::query()->select('id', 'name')->withMeta('budget', 'channel')->find('id', $campaign->id);
        $this->assertEquals('5000', $found->budget);
        $this->assertEquals('email', $found->channel);
    }

    public function testSaveUpdatesMeta(): void
    {
        Campaign::query()->withMeta('budget')->insert([
            'name' => 'Test',
            'slug' => 'test',
            'budget' => '5000',
        ]);

        $campaign = Campaign::query()->select('id', 'name', 'slug')->withMeta('budget')->find('slug', 'test');
        $campaign->budget = '9000';
        $campaign->save();

        $found = Campaign::query()->select('id')->withMeta('budget')->find('slug', 'test');
        $this->assertEquals('9000', $found->budget);
    }

    public function testToSQL(): void
    {
        global $wpdb;

        $sql = Campaign::query()->select('id', 'name')->where('slug', 'test')->toSQL();

        $this->assertEquals(
            "SELECT id, name FROM {$wpdb->prefix}campaigns WHERE slug = 'test'",
            $sql,
        );
    }

    public function testMigrateCreatesTables(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaign_meta");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaigns");

        Campaign::migrate(true);

        $tables = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$prefix}campaigns'",
        );
        $this->assertEquals(1, (int) $tables);

        $metaTables = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$prefix}campaign_meta'",
        );
        $this->assertEquals(1, (int) $metaTables);
    }

    public function testMigrateCreatesAllColumns(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}campaigns");

        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('slug', $columns);
        $this->assertContains('price', $columns);
        $this->assertContains('stock', $columns);
        $this->assertContains('created_at', $columns);
    }

    public function testMigrateIsIdempotent(): void
    {
        Campaign::query()->insert(['name' => 'Test', 'slug' => 'test']);

        Campaign::migrate(true);

        $campaign = Campaign::query()->find('slug', 'test');
        $this->assertEquals('Test', $campaign->name);
    }

    public function testMigrateAddsNewColumn(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $columnsBefore = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}campaigns");
        $this->assertNotContains('description', $columnsBefore);

        CampaignV2::migrate(true);

        $columnsAfter = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}campaigns");
        $this->assertContains('description', $columnsAfter);
    }

    public function testMigratePreservesData(): void
    {
        Campaign::query()->insert(['name' => 'Summer', 'slug' => 'summer']);
        Campaign::query()->withMeta('budget')->insert(['name' => 'Winter', 'slug' => 'winter', 'budget' => '5000']);

        CampaignV2::migrate(true);

        $this->assertEquals(2, Campaign::query()->count('id'));
        $campaign = Campaign::query()->find('slug', 'summer');
        $this->assertEquals('Summer', $campaign->name);

        $meta = Campaign::query()->select('id')->withMeta('budget')->find('slug', 'winter');
        $this->assertEquals('5000', $meta->budget);
    }

    public function testMigrateStoresVersion(): void
    {
        Campaign::migrate(true);

        $this->assertEquals(
            Campaign::getVersion(),
            get_option('queryable_campaigns_version'),
        );
    }

    public function testMigrateSkipsWhenVersionMatches(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        Campaign::migrate(true);

        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaigns");

        Campaign::migrate();

        $exists = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$prefix}campaigns'",
        );
        $this->assertEquals(0, (int) $exists);

        Campaign::migrate(true);

        $exists = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$prefix}campaigns'",
        );
        $this->assertEquals(1, (int) $exists);
    }

    public function test_array_roundtrips_through_json_column(): void
    {
        $model = TestModelWithJson::make();
        $model->payload = ['nested' => ['a' => 1, 'b' => 'two'], 'list' => [10, 20, 30]];
        $model->save();

        $reloaded = TestModelWithJson::query()->find('id', $model->id);

        $this->assertIsArray($reloaded->payload);
        // MySQL JSON sorts object keys on storage — compare with == (assertEquals)
        // so associative-array key order doesn't matter.
        $this->assertEquals(['nested' => ['a' => 1, 'b' => 'two'], 'list' => [10, 20, 30]], $reloaded->payload);
    }

    public function test_null_stays_null_in_both_directions(): void
    {
        $model = TestModelWithJson::make();
        $model->payload = null;
        $model->save();

        $reloaded = TestModelWithJson::query()->find('id', $model->id);

        $this->assertNull($reloaded->payload);
    }

    public function test_empty_array_roundtrips(): void
    {
        $model = TestModelWithJson::make();
        $model->payload = [];
        $model->save();

        $reloaded = TestModelWithJson::query()->find('id', $model->id);

        $this->assertSame([], $reloaded->payload);
    }

    public function test_unicode_in_json_payload_not_escaped(): void
    {
        $model = TestModelWithJson::make();
        $model->payload = ['name' => 'Sarah Müller', 'note' => '€10 = 5 meals 🍽'];
        $model->save();

        $reloaded = TestModelWithJson::query()->find('id', $model->id);

        $this->assertSame('Sarah Müller', $reloaded->payload['name']);
        $this->assertSame('€10 = 5 meals 🍽', $reloaded->payload['note']);
    }

    public function test_user_supplied_json_string_passes_through(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $model = TestModelWithJsonStringProp::make();
        $model->payload = '{"already":"encoded"}';
        $model->save();

        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT payload FROM {$prefix}test_models_str_payload WHERE id = %d",
            $model->id,
        ));

        // MySQL canonicalizes JSON on store (whitespace/ordering may differ),
        // so compare semantically. Double-encoding would have produced a string
        // value, not an object.
        $this->assertSame(['already' => 'encoded'], json_decode($raw, true));
    }

    public function test_model_without_json_columns_unaffected(): void
    {
        $model = TestModelNoJson::make();
        $model->name = 'Hello';
        $model->save();

        $reloaded = TestModelNoJson::query()->find('id', $model->id);

        $this->assertSame('Hello', $reloaded->name);
    }

    public function test_column_meta_cache_runs_schema_once_per_class(): void
    {
        $before = TestCountingSchema::$callbackInvocations;

        $m = TestCountingSchema::make();
        $m->payload = ['x' => 1];
        $m->save();

        TestCountingSchema::query()->find('id', $m->id);
        TestCountingSchema::query()->find('id', $m->id);

        $delta = TestCountingSchema::$callbackInvocations - $before;

        // At most 1 invocation across save + 2 finds: columnMeta() warms its
        // cache on the first call and reuses it thereafter. After the cache
        // is warm in a subsequent test run, delta may be 0.
        $this->assertLessThanOrEqual(1, $delta);
    }

    private function captureTxnQueries(): object
    {
        $holder = new \stdClass();
        $holder->queries = [];
        $holder->capture = function ($q) use ($holder) {
            if (preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', $q)) {
                $holder->queries[] = strtoupper(trim($q));
            }
            return $q;
        };
        add_filter('query', $holder->capture);

        return $holder;
    }

    public function test_nested_transactions_only_begin_once(): void
    {
        $cap = $this->captureTxnQueries();

        try {
            DB::transaction(function () {
                DB::transaction(function () {
                    // inner work — no DB writes needed; we're testing control flow
                });
                // outer work
            });
        } finally {
            remove_filter('query', $cap->capture);
        }

        $this->assertSame(['START TRANSACTION', 'COMMIT'], $cap->queries);
    }

    public function test_inner_throw_rolls_back_outer(): void
    {
        $cap = $this->captureTxnQueries();

        $caught = false;
        try {
            DB::transaction(function () {
                DB::transaction(function () {
                    throw new \RuntimeException('boom');
                });
            });
        } catch (\RuntimeException) {
            $caught = true;
        } finally {
            remove_filter('query', $cap->capture);
        }

        $this->assertTrue($caught);
        $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $cap->queries);
    }

    public function test_model_transaction_shares_depth_with_db_transaction(): void
    {
        $cap = $this->captureTxnQueries();

        try {
            DB::transaction(function () {
                Campaign::transaction(function () {
                    // Model::transaction nested inside DB::transaction must share
                    // the same depth counter.
                });
            });
        } finally {
            remove_filter('query', $cap->capture);
        }

        $this->assertSame(['START TRANSACTION', 'COMMIT'], $cap->queries);
    }

    public function test_single_transaction_commits(): void
    {
        $cap = $this->captureTxnQueries();

        try {
            DB::transaction(function () {
                // baseline: non-nested case still works
            });
        } finally {
            remove_filter('query', $cap->capture);
        }

        $this->assertSame(['START TRANSACTION', 'COMMIT'], $cap->queries);
    }

    public function test_single_transaction_rolls_back_on_throw(): void
    {
        $cap = $this->captureTxnQueries();

        $caught = false;
        try {
            DB::transaction(function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            $caught = true;
        } finally {
            remove_filter('query', $cap->capture);
        }

        $this->assertTrue($caught);
        $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $cap->queries);
    }
}
