<?php

namespace Queryable\Tests\Concerns;

use InvalidArgumentException;
use Queryable\DB;
use Queryable\Model;

class ErgonomicsTest extends QueryBuilderTestCase
{
    public function test_where_rejects_a_misordered_operator(): void
    {
        // Laravel-style (col, operator, value) puts the value in the operator
        // slot; that must fail loudly instead of silently matching nothing.
        $this->expectException(InvalidArgumentException::class);
        DB::table('donations')->where('paid_at', '>=', '2026-01-01')->toSQL();
    }

    public function test_where_accepts_the_documented_order(): void
    {
        $sql = DB::table('donations')->where('paid_at', '2026-01-01', '>=')->toSQL();
        $this->assertSame("SELECT * FROM donations WHERE paid_at >= '2026-01-01'", $sql);
    }

    public function test_where_null_aliases(): void
    {
        $this->assertStringContainsString(
            'deleted_at IS NULL',
            DB::table('posts')->whereNull('deleted_at')->toSQL(),
        );
        $this->assertStringContainsString(
            'deleted_at IS NOT NULL',
            DB::table('posts')->whereNotNull('deleted_at')->toSQL(),
        );
    }

    public function test_model_is_constructable_and_array_readable(): void
    {
        $model = new class extends Model {
            protected string $table = 'ergo';
            public int $id = 0;
            public ?string $name = null;
        };

        $model->name = 'Ada';
        $model->offsetSet('alias_total', 42); // selectRaw-style extra

        // Reads via array access mirror property/extra access and never fatal.
        $this->assertSame('Ada', $model['name']);
        $this->assertSame(42, $model['alias_total']);
        $this->assertNull($model['missing']);
        $this->assertTrue(isset($model['name']));
        $this->assertFalse(isset($model['missing']));
    }
}
