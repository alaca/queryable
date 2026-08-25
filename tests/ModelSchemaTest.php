<?php

declare(strict_types=1);

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Model;
use Queryable\Schema\Table;

class SchemaAccessModel extends Model
{
    protected string $table = 'schema_access';
}

class NoSchemaModel extends Model
{
    protected string $table = 'no_schema';
}

class ModelSchemaTest extends TestCase
{
    public function test_the_schema_closure_is_readable_by_class_name(): void
    {
        $callback = fn (Table $t) => $t->id();

        SchemaAccessModel::schema($callback);

        self::assertSame($callback, Model::schemaFor(SchemaAccessModel::class));
    }

    public function test_a_model_that_never_declared_a_schema_reports_null(): void
    {
        self::assertNull(Model::schemaFor(NoSchemaModel::class));
    }
}
