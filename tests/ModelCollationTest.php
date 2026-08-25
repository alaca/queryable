<?php

declare(strict_types=1);

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Model;
use Queryable\Schema\Table;

class WpdbDouble
{
    public string $prefix = 'wp_';
    public string $charset = 'latin1';
    public string $collate = 'latin1_swedish_ci';
}

class DefaultCollationModel extends Model
{
    protected string $table = 'default_collation';
}

class WpdbCollationModel extends Model
{
    protected string $table = 'wpdb_collation';
    protected array $collation = [];
}

class ModelCollationTest extends TestCase
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

    public function test_the_model_collation_beats_the_wpdb_one(): void
    {
        DefaultCollationModel::schema(fn (Table $t) => $t->id());
        DefaultCollationModel::migrate();

        self::assertStringContainsString(
            'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci',
            $GLOBALS['queryable_dbdelta'][0]
        );
    }

    public function test_an_empty_collation_falls_back_to_wpdb(): void
    {
        WpdbCollationModel::schema(fn (Table $t) => $t->id());
        WpdbCollationModel::migrate();

        self::assertStringContainsString(
            'DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci',
            $GLOBALS['queryable_dbdelta'][0]
        );
    }
}
