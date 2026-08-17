<?php

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Model;

class MakeTestModel extends Model
{
    protected string $table = 'make_test_models';

    public int $id = 0;
    public string $name = '';
}

/**
 * Model::make() used to be declared with no parameters, so
 * Model::make(['a' => 1]) silently discarded the array (PHP does not error on
 * extra positional arguments) and returned an empty model. No $wpdb needed:
 * make() only constructs and assigns, so this runs in the plain unit suite.
 */
class ModelMakeTest extends TestCase
{
    public function test_make_with_no_arguments_still_works(): void
    {
        $model = MakeTestModel::make();

        $this->assertInstanceOf(MakeTestModel::class, $model);
        $this->assertSame(0, $model->id);
        $this->assertSame('', $model->name);
    }

    public function test_make_sets_a_declared_property_directly(): void
    {
        $model = MakeTestModel::make(['name' => 'x']);

        $this->assertSame('x', $model->name);
    }

    public function test_make_routes_undeclared_keys_through_the_extras_bag(): void
    {
        $model = MakeTestModel::make(['name' => 'x', 'not_a_property' => 'y']);

        $this->assertSame('x', $model->name);
        $this->assertSame('y', $model['not_a_property']);
    }
}
