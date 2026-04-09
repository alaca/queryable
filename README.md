# Queryable

A fluent SQL query builder for WordPress. Built on `$wpdb`, with meta table support, relations, migrations, and a typed model layer

## Installation

```bash
composer require alaca/queryable
```


## Two ways to use

### 1. Query Builder (DB Facade)

Use `DB::table()` for direct queries

```php
use Queryable\DB;

// Simple queries
$posts = DB::table('posts')->select('ID', 'post_title')->where('post_status', 'publish')->getAll();
$user  = DB::table('users')->find('ID', 1);

// If you want to work with meta and relations using DB class, you will have to configure the schema in DB::init()
DB::init([
    'schema' => [
        'posts' => [
            'meta' => [
                'table' => 'postmeta', // table name is auto prefixed
                'foreignKey' => 'post_id',
                'primaryKey' => 'ID',
                'aliases' => [
                    'price' => '_product_price',
                    'color' => '_product_color',
                ],
            ],
            'relations' => [
                'comments' => [
                    'table' => 'comments',
                    'foreignKey' => 'comment_post_ID',
                    'primaryKey' => 'ID',
                    'type' => 'hasMany',
                ],
            ],
        ],
    ],
]);

$products = DB::table('posts')
    ->select('ID', 'post_title')
    ->withMeta('price', 'color')
    ->where('post_type', 'product')
    ->orderBy('price', 'DESC')
    ->getAll();
```

### 2. Model (Recommended)

Define a model class per table with typed public properties

```php
use Queryable\Model;
use Queryable\Schema\Table;

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
                    'multiple' => true
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

// Schema is defined separately
Campaign::schema(function (Table $table) {
    $table->id();
    $table->string('name');
    $table->string('slug', 100)->unique();
    $table->decimal('price', 8, 2)->default(0);
    $table->integer('stock')->default(0);
    $table->datetime('created_at')->nullable();
});

// Query results are Campaign instances
$campaign = Campaign::query()->find('slug', 'summer');
$campaign instanceof Campaign; // true
$campaign->name;
$campaign->price; // float, auto-cast from DB  - should be unsigned int in real app! 

Campaign::query()->select('id', 'name')->withMeta('budget')->where('status', 'active')->getAll();
Campaign::query()->insert(['name' => 'Summer Sale', 'slug' => 'summer-sale']);
Campaign::query()->where('id', 1950)->update(['name' => 'Updated']);
Campaign::query()->where('id', 1950)->delete();
```

## Table of Contents

- [Base Query](#base-query)
- [Scopes](#scopes)
- [Select](#select)
- [Save](#save)
- [Lifecycle Hooks](#lifecycle-hooks)
- [Insert](#insert)
- [Update](#update)
- [Delete](#delete)
- [Where Clauses](#where-clauses)
- [Joins](#joins)
- [Ordering, Grouping, Limit & Offset](#ordering-grouping-limit--offset)
- [Having](#having)
- [Union](#union)
- [Aggregates](#aggregates)
- [Find & Exists](#find--exists)
- [Pluck](#pluck)
- [Meta Tables](#meta-tables)
- [Relations](#relations)
- [Migrations](#migrations)
- [Transactions](#transactions)
- [Raw Queries](#raw-queries)
- [Conditional Clauses](#conditional-clauses)
- [Clone](#clone)
- [SQL Output](#sql-output)

## Base Query

Apply default conditions to every query a model makes. Useful for models that represent a subset of a shared table

```php
class Product extends Model
{
    protected string $table = 'posts';
    protected string $primaryKey = 'ID';

    public int $ID;
    public string $post_title;
    public string $post_status;

    protected static function baseQuery(ModelQueryBuilder $builder): ModelQueryBuilder
    {
        return $builder->where('post_type', 'product');
    }
}

// Every query automatically includes WHERE post_type = 'product'
Product::query()->where('post_status', 'publish')->getAll();
Product::query()->find('ID', 1911);
Product::query()->count('ID');
```

## Scopes

Define reusable query filters as static methods on your model

```php
class Campaign extends Model
{
    protected string $table = 'campaigns';

    public static function active(): ModelQueryBuilder
    {
        return static::query()->where('status', 'active');
    }

    public static function p2p(): ModelQueryBuilder
    {
        return static::query()->where('campaign_type', 'p2p');
    }
}

Campaign::active()->getAll();
Campaign::active()->orderBy('name')->limit(10)->getAll();
Campaign::p2p()->getAll();
```


## Save

Create or update a model instance

```php
// Create new
$campaign = Campaign::make();
$campaign->name = 'Summer';
$campaign->slug = 'summer';
$campaign->price = 29.99;
$campaign->save();

// Update existing
$campaign = Campaign::query()->find('id', 1950);
$campaign->name = 'Updated';
$campaign->save();
```

The primary key defaults to `id`. Override `$primaryKey` in your model if needed:

```php
class Post extends Model
{
    protected string $table = 'posts';
    protected string $primaryKey = 'ID';
}
```

## Lifecycle Hooks

Override `onBeforeSave()` and `onSave()` to run logic before and after `save()`

```php
class Campaign extends Model
{
    protected string $table = 'campaigns';

    protected function onBeforeSave(): void
    {
        if (empty($this->slug)) {
            $this->slug = sanitize_title($this->name);
        }
    }

    protected function onSave(): void
    {
        do_action('campaign_saved', $this);
    }
}
```

`onBeforeSave()` runs before the database write. Any property changes made there are included in the save

`onSave()` runs after the database write

## Insert

```php
// Single row
Campaign::query()->insert(['name' => 'Summer', 'slug' => 'summer']);
// Returns: QueryResult { affectedRows: 1, insertId: 1 }

// bulk insert
Campaign::query()->insert([
    ['name' => 'Summer', 'slug' => 'summer'],
    ['name' => 'Winter', 'slug' => 'winter'],
]);

// insert with meta (meta fields are separated automatically)
Campaign::query()->withMeta('budget', 'channel')->insert([
    'name'    => 'Summer',
    'slug'    => 'summer',
    'budget'  => '1950', // campaign_meta table
    'channel' => 'email', // campaign_meta table
]);

// upsert (insert or update on duplicate key)
Campaign::query()->upsert(
    ['name' => 'Summer', 'slug' => 'summer'],
    ['slug'], // conflict columns
    ['name'], // columns to update
);
```

## Update

```php
Campaign::query()->where('id', 1)->update(['name' => 'Updated']);

// update meta
Campaign::query()->withMeta('budget')->where('id', 1)->update([
    'name'   => 'Updated',
    'budget' => '1911',// updates meta row
]);

// Increment / Decrement
Campaign::query()->where('id', 1)->increment('stock');
Campaign::query()->where('id', 1)->increment('stock', 5);
Campaign::query()->where('id', 1)->decrement('stock', 3);

// raw update
Campaign::query()->where('id', 1)->updateRaw("stock = stock + 1");
```

## Delete

```php
Campaign::query()->where('id', 1)->delete();
// meta rows are deleted automatically if the model has meta config

// truncate (clears both the table and meta table)
Campaign::query()->truncate();
```

## Select

```php
// all columns
Campaign::query()->getAll();

// select specific columns
Campaign::query()->select('id', 'name', 'slug')->getAll();

// single row (LIMIT 1, returns null if not found)
Campaign::query()->select('id', 'name')->get();

// distinct
Campaign::query()->distinct()->select('status')->getAll();

// select with alias
Campaign::query()->select(['id' => 'campaign_id'])->getAll();

// raaw select
Campaign::query()->selectRaw('COUNT(*) as total')->get();

// Subquery in FROM
DB::table(function ($qb) {
    $qb->table('orders')->select('user_id')->groupBy('user_id');
}, 
'order_totals')->selectRaw('user_id, total')->getAll();
```

## Where Clauses

```php
// basic
Campaign::query()->where('status', 'active')->getAll();
Campaign::query()->where('status', 'active')->orWhere('status', 'pending')->getAll();

// LIKE
Campaign::query()->whereLike('name', 'summer')->getAll(); // LIKE '%summer%'
Campaign::query()->whereLike('name', 'summer%')->getAll(); // LIKE 'summer%'

// IN / NOT IN
Campaign::query()->whereIn('id', [1, 2, 3])->getAll();
Campaign::query()->whereNotIn('status', ['draft', 'trash'])->getAll();

// BETWEEN
Campaign::query()->whereBetween('price', 10, 100)->getAll();

// NULL
Campaign::query()->whereIsNull('deleted_at')->getAll();
Campaign::query()->whereIsNotNull('email')->getAll();

// column comparison (no value escaping), useful for JOINS
Campaign::query()->whereColumn('users.id', 'orders.user_id')->getAll();

// nested groups
Campaign::query()->where('status', 'active')
    ->orWhere(function ($qb) {
        $qb->where('role', 'admin')->where('verified', 1);
    })
    ->getAll();
// WHERE status = 'active' OR (role = 'admin' AND verified = 1)

// Subquery
Campaign::query()->whereIn('id', function ($qb) {
    $qb->table('orders')->select('campaign_id')->where('total', 100, '>');
})->getAll();

// EXISTS
Campaign::query()->whereExists(function ($qb) {
    $qb->table('orders')->select('id')->whereRaw('orders.campaign_id = campaigns.id');
})->getAll();

// Raw
Campaign::query()->whereRaw('created_at > NOW() - INTERVAL 30 DAY')->getAll();
```

All where methods have `or` variants: `orWhere`, `orWhereLike`, `orWhereIn`, `orWhereBetween`, `orWhereIsNull`, `orWhereExists`, `orWhereColumn`.

## Joins

```php
Campaign::query()->leftJoin('entries', 'campaigns.id', 'entries.campaign_id')->getAll();
Campaign::query()->innerJoin('entries', 'campaigns.id', 'entries.campaign_id')->getAll();
Campaign::query()->rightJoin('entries', 'campaigns.id', 'entries.campaign_id')->getAll();
Campaign::query()->crossJoin('statuses')->getAll();

// with alias
Campaign::query()->leftJoin('entries', 'campaigns.id', 'e.campaign_id', 'e')->getAll();

// raw join
Campaign::query()->joinRaw('LEFT JOIN entries e ON campaigns.id = e.campaign_id')->getAll();
```

## Ordering, Grouping, Limit & Offset

```php
Campaign::query()->orderBy('name')->getAll();
Campaign::query()->orderBy('name', 'DESC')->getAll();
Campaign::query()->orderByRaw('RAND()')->getAll();

Campaign::query()->groupBy('status')->getAll();
Campaign::query()->groupBy('status', 'channel')->getAll();
Campaign::query()->groupByRaw('YEAR(created_at)')->getAll();

Campaign::query()->limit(10)->offset(20)->getAll();

// order and group by meta keys
Campaign::query()->withMeta('budget')->orderBy('budget', 'DESC')->getAll();
Campaign::query()->withMeta('channel')->groupBy('channel')->getAll();
```

## Having

```php
Campaign::query()->groupBy('status')->havingCount('id', '>', 5)->getAll();
Campaign::query()->groupBy('status')->havingSum('stock', '>', 100)->getAll();
Campaign::query()->groupBy('status')->havingAvg('price', '>', 50)->getAll();
Campaign::query()->groupBy('status')->havingMin('price', '>', 10)->getAll();
Campaign::query()->groupBy('status')->havingMax('price', '<', 1000)->getAll();
Campaign::query()->groupBy('status')->havingRaw('COUNT(id) > 5')->getAll();

// combine with OR
Campaign::query()->groupBy('status')
    ->havingSum('stock', '>', 100)
    ->orHavingAvg('price', '>', 200)
    ->getAll();
```

## Union

```php
$drafts = Campaign::query()->where('status', 'draft')->select('id', 'name');
Campaign::query()->where('status', 'active')->select('id', 'name')->union($drafts)->getAll();

// Union ALL
Campaign::query()->select('id')->unionAll($drafts)->getAll();
```

## Aggregates

```php
Campaign::query()->count('id'); // int
Campaign::query()->sum('price'); // float
Campaign::query()->avg('price'); // float
Campaign::query()->min('price'); // float
Campaign::query()->max('price'); // float

// with conditions
Campaign::query()->where('status', 'active')->count('id');
```

## Find & Exists

```php
// find by column. returns model instance or null
Campaign::query()->find('id', 1950);
Campaign::query()->find('slug', 'summer');

// check if rows exist. returns bool
Campaign::query()->where('slug', 'summer')->exists();
```

## Pluck

Returns a flat array of a single column values:

```php
Campaign::query()->pluck('name');
// ['Save the dolphins', 'All alcoholic beverages 20% off', 'Yeah, no']
```

## Meta Tables

Meta tables follow the WordPress pattern: a separate table with `meta_key` and `meta_value` columns linked by a foreign key

### Configuration

Define meta config in your models `meta()` method or in `DB::init()`:

```php
protected function meta(): array
{
    return [
        'table' => 'campaign_meta', // meta table name (auto-prefixed)
        'foreignKey' => 'campaign_id',  // FK in meta table
        'primaryKey' => 'id', // PK in main table
        'aliases' => [  // meta keys aliases
            'budget' => '_campaign_budget',
            'channel' => '_campaign_channel',
            'tags' => [
                'key' => '_campaign_tags', 
                'multiple' => true
            ],
        ],
    ];
}
```

### Aliases

Aliases let you use friendly names instead of ugly meta key names:

```php
// without aliases, you have to write
->withMeta('_campaign_budget')

// with aliases
->withMeta('budget')
```

### Single vs Multiple values

Some meta keys can have multiple rows with the same key. Mark these with `'multiple' => true`:

```php
'aliases' => [
    'budget' => '_campaign_budget', // single value
    'tags'   => [
        'key' => '_campaign_tags', 
        'multiple' => true // multiple values
    ], 
],
```

**Single values**
```php
$campaign = Campaign::query()->select('id')->withMeta('budget')->get();
$campaign->budget; // 5000
```

**Multiple values** are parsed into arrays:
```php
$campaign = Campaign::query()->select('id')->withMeta('tags')->groupBy('id')->get();
$campaign->tags; // ['promo', 'seasonal', 'email']
```

### Meta in Queries

`withMeta()` makes meta keys available in select, where, orderBy, groupBy

```php
// get specific keys
Campaign::query()->select('id', 'name')
    ->withMeta('budget', 'channel')
    ->where('budget', 1000, '>') // WHERE meta_budget.meta_value > 1000
    ->orderBy('budget', 'DESC') // ORDER BY meta_budget.meta_value DESC
    ->groupBy('channel') // GROUP BY meta_channel.meta_value
    ->getAll();

// get campaign with all meta keys
Campaign::query()->select('id', 'name')->withMeta()->getAll();
```

### Meta in Mutations

```php
// Insert: meta fields are auto-separated from table columns
Campaign::query()->withMeta('budget', 'tags')->insert([
    'name' => 'Summer',
    'slug' => 'summer',
    'budget' => '1911', // 1 row in campaign_meta
    'tags' => ['promo', 'seasonal'], // 2 rows in campaign_meta
]);

// Update: single meta upserted, multiple meta replaced
Campaign::query()->withMeta('budget', 'tags')->where('id', 1)->update([
    'budget' => '1950', // updates existing meta row
    'tags' => ['promo', 'holiday'], // deletes old rows, inserts new
]);

// Delete: meta rows are deleted before campaign
Campaign::query()->withMeta('budget')->where('id', 1)->delete();
```

## Relations

Relations define how tables are connected. The parent table's rows are fetched first, then related rows are loaded in a separate query and attached

### Configuration

```php
protected function relations(): array
{
    return [
        'entries' => [
            'table' => 'campaign_entries',
            'foreignKey' => 'campaign_id',
            'primaryKey' => 'id',
            'type' => 'hasMany', // hasMany | hasOne | belongsTo
        ],
    ];
}
```

### Usage

```php
// eager load relations with with()
$campaign = Campaign::query()->select('id', 'name')->with('entries')->get();

// hasMany returns an array
$campaign->entries;
// [
//     ['id' => 1, 'email' => 'a@test.com'],
//     ['id' => 2, 'email' => 'b@test.com'],
// ]

// hasOne returns single row or null
$campaign->profile; // ['id' => 1, 'bio' => '...'] or null
```

Relations work with both `get()` and `getAll()`.

## Migrations

Each model defines its table structure via `schema()` and a `$version` string. Calling `migrate()` runs `dbDelta()`

### Defining Schema

Schema is defined separately from the model class using a static callback

```php
class Campaign extends Model
{
    protected string $table = 'campaigns';
    protected string $version = '1.0.0';

    public int $id;
    public string $name;
    // ...
}

Campaign::schema(function (Table $table) {
    $table->id();
    $table->string('name');
    $table->string('slug', 100)->unique();
    $table->decimal('price', 8, 2)->default(0);
    $table->integer('stock')->default(0);
    $table->boolean('active')->default(true);
    $table->text('description')->nullable();
    $table->datetime('created_at')->nullable();
});
```

### Running Migrations

When you change the schema, bump the version

```php
protected string $version = '1.1.0'; // added 'priority' column
```

Call `migrate()` on plugin activation:

```php
register_activation_hook(__FILE__, function () {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    Campaign::migrate();
    CampaignEntry::migrate();
});
```

Force migration regardless of version

```php
Campaign::migrate(true);
```

### Available Column Types

| Method | MySQL Type |
|---|---|
| `$table->id()` | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` |
| `$table->string('name')` | `VARCHAR(255) NOT NULL` |
| `$table->string('code', 50)` | `VARCHAR(50) NOT NULL` |
| `$table->text('body')` | `TEXT NOT NULL` |
| `$table->longText('content')` | `LONGTEXT NOT NULL` |
| `$table->integer('count')` | `INT NOT NULL` |
| `$table->bigInteger('views')` | `BIGINT NOT NULL` |
| `$table->tinyInteger('priority')` | `TINYINT NOT NULL` |
| `$table->float('rating')` | `FLOAT NOT NULL` |
| `$table->decimal('price', 8, 2)` | `DECIMAL(8,2) NOT NULL` |
| `$table->boolean('active')` | `TINYINT(1) NOT NULL` |
| `$table->date('birth_date')` | `DATE NOT NULL` |
| `$table->datetime('published_at')` | `DATETIME NOT NULL` |
| `$table->timestamp('verified_at')` | `TIMESTAMP NOT NULL` |
| `$table->json('settings')` | `JSON NOT NULL` |
| `$table->enum('status', ['a', 'b'])` | `ENUM('a','b') NOT NULL` |

### Column Modifiers

```php
$table->string('email')->unique();
$table->integer('stock')->default(0)->unsigned();
$table->datetime('deleted_at')->nullable();
$table->boolean('featured')->default(false);
$table->bigInteger('user_id')->unsigned()->references('users', 'ID')->onDelete('CASCADE');
```

Available modifiers: `->nullable()`, `->unique()`, `->primary()`, `->unsigned()`, `->default($value)`, `->references($table, $column)`, `->onDelete($action)`

### Meta Table Auto-Creation

If the model has a `meta()` config, `migrate()` automatically creates the meta table

```php
protected function meta(): array
{
    return [
        'table' => 'campaign_meta',
        'foreignKey' => 'campaign_id',
        'primaryKey' => 'id',
    ];
}

// Campaign::migrate() creates both tables
```

## Transactions

```php
// using a model
Campaign::transaction(function () {
    Campaign::query()->insert(['name' => 'A', 'slug' => 'a']);
    Campaign::query()->insert(['name' => 'B', 'slug' => 'b']);
});

// using DB facade
DB::transaction(function () {
    DB::table('campaigns')->insert(['name' => 'A', 'slug' => 'a']);
    DB::table('campaigns')->insert(['name' => 'B', 'slug' => 'b']);
});

// auto-commits on success, rollbacks if an exception is thrown
```

## Raw Queries

```php
DB::raw('SELECT * FROM wp_posts WHERE post_status = %s', ['publish']);
```

Parameters use `$wpdb->prepare()` for escaping

## Conditional Clauses

Conditionally add clauses

```php
$status = $_GET['status'] ?? null;

Campaign::query()->select('id', 'name')
    ->when($status, fn ($qb) => $qb->where('status', $status))
    ->getAll();
```

## Clone

Create an independent copy to build query variants

```php
$base = Campaign::query()->select('id', 'name');
$active = $base->clone()->where('status', 'active');
$drafts = $base->clone()->where('status', 'draft');
```

## SQL Output

Get the generated SQL without executing:

```php
Campaign::query()->select('id', 'name')->where('status', 'active')->toSQL();
// SELECT id, name FROM wp_campaigns WHERE status = 'active'
```
