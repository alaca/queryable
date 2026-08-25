<?php

declare(strict_types=1);

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Model;
use Queryable\Schema\Table;
use RuntimeException;

class CompositeKeyModel extends Model
{
    protected string $table = 'composite_key';

    public int $entry_id;
    public string $field_key;
    public int $row_index = 0;
}

CompositeKeyModel::schema(static function (Table $t): void {
    $t->bigInteger('entry_id')->unsigned();
    $t->char('field_key', 16);
    $t->smallInteger('row_index')->unsigned()->default(0);
    $t->primary(['entry_id', 'field_key', 'row_index']);
});

class SuppliedKeyModel extends Model
{
    protected string $table = 'supplied_key';
    protected string $primaryKey = 'form_id';

    public int $form_id;
    public int $next_serial = 1;
}

SuppliedKeyModel::schema(static function (Table $t): void {
    $t->bigInteger('form_id')->unsigned()->primary();
    $t->bigInteger('next_serial')->unsigned()->default(1);
});

/**
 * save() reads the primary key to choose an insert or an update, so it is only
 * ever correct where the database generates that key. Both other shapes fail
 * silently, which is why the refusal is loud.
 */
class ModelWriteKeyTest extends TestCase
{
    public function test_save_refuses_a_key_the_schema_does_not_generate(): void
    {
        $model = new CompositeKeyModel();
        $model->entry_id = 1;
        $model->field_key = 'fld_000000000001';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not an AUTO_INCREMENT column');

        $model->save();
    }

    public function test_save_refuses_a_key_the_caller_supplies(): void
    {
        $model = new SuppliedKeyModel();
        $model->form_id = 7;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not an AUTO_INCREMENT column');

        $model->save();
    }

    public function test_the_refusal_names_the_model_the_column_and_the_way_out(): void
    {
        $model = new SuppliedKeyModel();
        $model->form_id = 7;

        try {
            $model->save();
            self::fail('save() must refuse a supplied primary key');
        } catch (RuntimeException $e) {
            self::assertStringContainsString(SuppliedKeyModel::class . '::$form_id', $e->getMessage());
            self::assertStringContainsString('insert()', $e->getMessage());
        }
    }
}
