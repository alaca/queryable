<?php

namespace Queryable\Tests;

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
    }

    public function tear_down(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaign_meta");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaign_entries");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}campaigns");

        parent::tear_down();
    }

    public function testGetReturnsModelInstance(): void
    {
        Campaign::insert(['name' => 'Summer', 'slug' => 'summer']);

        $campaign = Campaign::get();

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals('Summer', $campaign->name);
        $this->assertEquals('summer', $campaign->slug);
    }

    public function testGetAllReturnsModelInstances(): void
    {
        Campaign::insert([
            ['name' => 'Summer', 'slug' => 'summer'],
            ['name' => 'Winter', 'slug' => 'winter'],
        ]);

        $campaigns = Campaign::getAll();

        $this->assertCount(2, $campaigns);
        $this->assertInstanceOf(Campaign::class, $campaigns[0]);
        $this->assertInstanceOf(Campaign::class, $campaigns[1]);
        $this->assertEquals('Summer', $campaigns[0]->name);
        $this->assertEquals('Winter', $campaigns[1]->name);
    }

    public function testFindReturnsModelInstance(): void
    {
        Campaign::insert(['name' => 'Summer', 'slug' => 'summer']);

        $campaign = Campaign::find('slug', 'summer');

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertEquals('Summer', $campaign->name);
    }

    public function testFindReturnsNull(): void
    {
        $this->assertNull(Campaign::find('slug', 'nonexistent'));
    }

    public function testToArray(): void
    {
        Campaign::insert(['name' => 'Summer', 'slug' => 'summer']);

        $campaign = Campaign::find('slug', 'summer');
        $array = $campaign->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Summer', $array['name']);
    }

    public function testInsert(): void
    {
        $result = Campaign::insert(['name' => 'Summer', 'slug' => 'summer']);

        $this->assertEquals(1, $result->affectedRows);
        $this->assertEquals(1, $result->insertId);
    }

    public function testBulkInsert(): void
    {
        Campaign::insert([
            ['name' => 'Summer', 'slug' => 'summer'],
            ['name' => 'Winter', 'slug' => 'winter'],
        ]);

        $this->assertEquals(2, Campaign::count('id'));
    }

    public function testSelectColumns(): void
    {
        Campaign::insert(['name' => 'Summer', 'slug' => 'summer']);

        $campaign = Campaign::select('name', 'slug')->get();

        $this->assertEquals('Summer', $campaign->name);
        $this->assertEquals('summer', $campaign->slug);
    }

    public function testWhere(): void
    {
        Campaign::insert([
            ['name' => 'Active', 'slug' => 'active'],
            ['name' => 'Draft', 'slug' => 'draft'],
        ]);

        $campaigns = Campaign::where('slug', 'active')->getAll();

        $this->assertCount(1, $campaigns);
        $this->assertEquals('Active', $campaigns[0]->name);
    }

    public function testWhereChain(): void
    {
        Campaign::insert([
            ['name' => 'A', 'slug' => 'a'],
            ['name' => 'B', 'slug' => 'b'],
            ['name' => 'C', 'slug' => 'c'],
        ]);

        $campaigns = Campaign::where('name', 'A')->orWhere('name', 'C')->getAll();

        $this->assertCount(2, $campaigns);
    }

    public function testUpdate(): void
    {
        Campaign::insert(['name' => 'Old', 'slug' => 'test']);
        Campaign::where('slug', 'test')->update(['name' => 'New']);

        $campaign = Campaign::find('slug', 'test');
        $this->assertEquals('New', $campaign->name);
    }

    public function testDelete(): void
    {
        Campaign::insert(['name' => 'Test', 'slug' => 'test']);
        Campaign::where('slug', 'test')->delete();

        $this->assertEquals(0, Campaign::count('id'));
    }

    public function testCount(): void
    {
        Campaign::insert([
            ['name' => 'A', 'slug' => 'a'],
            ['name' => 'B', 'slug' => 'b'],
            ['name' => 'C', 'slug' => 'c'],
        ]);

        $this->assertEquals(3, Campaign::count('id'));
    }

    public function testSumAvgMinMax(): void
    {
        Campaign::insert([
            ['name' => 'A', 'slug' => 'a', 'price' => 10.00, 'stock' => 5],
            ['name' => 'B', 'slug' => 'b', 'price' => 20.00, 'stock' => 15],
            ['name' => 'C', 'slug' => 'c', 'price' => 30.00, 'stock' => 25],
        ]);

        $this->assertEquals(60.0, Campaign::sum('price'));
        $this->assertEquals(20.0, Campaign::avg('price'));
        $this->assertEquals(10.0, Campaign::min('price'));
        $this->assertEquals(30.0, Campaign::max('price'));
    }

    public function testExists(): void
    {
        Campaign::insert(['name' => 'Test', 'slug' => 'test']);

        $this->assertTrue(Campaign::where('slug', 'test')->exists());
        $this->assertFalse(Campaign::where('slug', 'nope')->exists());
    }

    public function testPluck(): void
    {
        Campaign::insert([
            ['name' => 'Alpha', 'slug' => 'alpha'],
            ['name' => 'Beta', 'slug' => 'beta'],
        ]);

        $this->assertEquals(['Alpha', 'Beta'], Campaign::pluck('name'));
    }

    public function testOrderBy(): void
    {
        Campaign::insert([
            ['name' => 'B', 'slug' => 'b'],
            ['name' => 'A', 'slug' => 'a'],
            ['name' => 'C', 'slug' => 'c'],
        ]);

        $campaigns = Campaign::select('name')->orderBy('name')->getAll();

        $this->assertEquals('A', $campaigns[0]->name);
        $this->assertEquals('C', $campaigns[2]->name);
    }

    public function testLimitOffset(): void
    {
        Campaign::insert([
            ['name' => 'A', 'slug' => 'a'],
            ['name' => 'B', 'slug' => 'b'],
            ['name' => 'C', 'slug' => 'c'],
        ]);

        $campaigns = Campaign::select('name')->orderBy('name')->limit(2)->offset(1)->getAll();

        $this->assertCount(2, $campaigns);
        $this->assertEquals('B', $campaigns[0]->name);
    }

    public function testInsertWithMeta(): void
    {
        Campaign::withMeta('budget', 'channel')->insert([
            'name' => 'Summer',
            'slug' => 'summer',
            'budget' => '5000',
            'channel' => 'email',
        ]);

        $campaign = Campaign::select('id', 'name')->withMeta('budget', 'channel')->find('slug', 'summer');

        $this->assertEquals('5000', $campaign->budget);
        $this->assertEquals('email', $campaign->channel);
    }

    public function testUpdateMeta(): void
    {
        Campaign::withMeta('budget')->insert([
            'name' => 'Test',
            'slug' => 'test',
            'budget' => '5000',
        ]);

        Campaign::withMeta('budget')->where('slug', 'test')->update([
            'budget' => '7500',
        ]);

        $campaign = Campaign::select('id')->withMeta('budget')->find('slug', 'test');
        $this->assertEquals('7500', $campaign->budget);
    }

    public function testOrderByMeta(): void
    {
        Campaign::withMeta('budget')->insert(['name' => 'Cheap', 'slug' => 'cheap', 'budget' => '100']);
        Campaign::withMeta('budget')->insert(['name' => 'Expensive', 'slug' => 'expensive', 'budget' => '9999']);

        $campaigns = Campaign::select('name')->withMeta('budget')->orderBy('budget', 'DESC')->getAll();

        $this->assertEquals('Expensive', $campaigns[0]->name);
        $this->assertEquals('Cheap', $campaigns[1]->name);
    }

    public function testWhereByMeta(): void
    {
        Campaign::withMeta('budget')->insert(['name' => 'Low', 'slug' => 'low', 'budget' => '100']);
        Campaign::withMeta('budget')->insert(['name' => 'High', 'slug' => 'high', 'budget' => '9999']);

        $campaigns = Campaign::select('name')->withMeta('budget')->where('budget', 1000, '>')->getAll();

        $this->assertCount(1, $campaigns);
        $this->assertEquals('High', $campaigns[0]->name);
    }

    public function testInsertMultipleMeta(): void
    {
        Campaign::withMeta('tags')->insert([
            'name' => 'Summer',
            'slug' => 'summer',
            'tags' => ['promo', 'seasonal', 'email'],
        ]);

        $campaign = Campaign::select('id', 'name')
            ->withMeta('tags')
            ->groupBy('id', 'name')
            ->find('slug', 'summer');

        $this->assertIsArray($campaign->tags);
        $this->assertCount(3, $campaign->tags);
        $this->assertContains('promo', $campaign->tags);
    }

    public function testUpdateMultipleMetaReplaces(): void
    {
        Campaign::withMeta('tags')->insert([
            'name' => 'Test',
            'slug' => 'test',
            'tags' => ['old1', 'old2'],
        ]);

        Campaign::withMeta('tags')->where('slug', 'test')->update([
            'tags' => ['new1', 'new2', 'new3'],
        ]);

        $campaign = Campaign::select('id')
            ->withMeta('tags')
            ->groupBy('id')
            ->find('slug', 'test');

        $this->assertCount(3, $campaign->tags);
        $this->assertContains('new1', $campaign->tags);
        $this->assertNotContains('old1', $campaign->tags);
    }

    public function testSingleAndMultipleMetaTogether(): void
    {
        Campaign::withMeta('budget', 'tags')->insert([
            'name' => 'Mixed',
            'slug' => 'mixed',
            'budget' => '5000',
            'tags' => ['promo', 'sale'],
        ]);

        $campaign = Campaign::select('id', 'name')
            ->withMeta('budget', 'tags')
            ->groupBy('id', 'name')
            ->find('slug', 'mixed');

        $this->assertEquals('5000', $campaign->budget);
        $this->assertIsArray($campaign->tags);
        $this->assertCount(2, $campaign->tags);
    }

    public function testWithHasMany(): void
    {
        Campaign::insert(['name' => 'Test', 'slug' => 'test']);

        CampaignEntry::insert(['campaign_id' => 1, 'email' => 'a@test.com']);
        CampaignEntry::insert(['campaign_id' => 1, 'email' => 'b@test.com']);

        $campaign = Campaign::select('id', 'name')->with('entries')->get();

        $this->assertCount(2, $campaign->entries);
        $this->assertEquals('a@test.com', $campaign->entries[0]['email']);
    }

    public function testWithHasManyEmpty(): void
    {
        Campaign::insert(['name' => 'Empty', 'slug' => 'empty']);

        $campaign = Campaign::select('id', 'name')->with('entries')->get();

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

        $found = Campaign::find('id', $campaign->id);
        $this->assertInstanceOf(Campaign::class, $found);
        $this->assertEquals('Summer', $found->name);
        $this->assertEquals('summer', $found->slug);
    }

    public function testSaveUpdatesExistingRecord(): void
    {
        Campaign::insert(['name' => 'Old', 'slug' => 'test']);

        $campaign = Campaign::find('slug', 'test');
        $campaign->name = 'New';
        $campaign->save();

        $updated = Campaign::find('slug', 'test');
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

        $found = Campaign::select('id', 'name')->withMeta('budget', 'channel')->find('id', $campaign->id);
        $this->assertEquals('5000', $found->budget);
        $this->assertEquals('email', $found->channel);
    }

    public function testSaveUpdatesMeta(): void
    {
        Campaign::withMeta('budget')->insert([
            'name' => 'Test',
            'slug' => 'test',
            'budget' => '5000',
        ]);

        $campaign = Campaign::select('id', 'name', 'slug')->withMeta('budget')->find('slug', 'test');
        $campaign->budget = '9000';
        $campaign->save();

        $found = Campaign::select('id')->withMeta('budget')->find('slug', 'test');
        $this->assertEquals('9000', $found->budget);
    }

    public function testToSQL(): void
    {
        global $wpdb;

        $sql = Campaign::select('id', 'name')->where('slug', 'test')->toSQL();

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
        Campaign::insert(['name' => 'Test', 'slug' => 'test']);

        Campaign::migrate(true);

        $campaign = Campaign::find('slug', 'test');
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
        Campaign::insert(['name' => 'Summer', 'slug' => 'summer']);
        Campaign::withMeta('budget')->insert(['name' => 'Winter', 'slug' => 'winter', 'budget' => '5000']);

        CampaignV2::migrate(true);

        $this->assertEquals(2, Campaign::count('id'));
        $campaign = Campaign::find('slug', 'summer');
        $this->assertEquals('Summer', $campaign->name);

        $meta = Campaign::select('id')->withMeta('budget')->find('slug', 'winter');
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
}
