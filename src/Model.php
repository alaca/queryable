<?php

namespace Queryable;

use Closure;
use Queryable\Schema\Table;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use Throwable;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected string $version = '1.0.0';

    private array $extras = [];
    private static array $schemas = [];

    private function __construct()
    {
    }

    protected function onBeforeSave(): void
    {
    }

    protected function onSave(): void
    {
    }

    protected function meta(): array
    {
        return [];
    }

    protected function relations(): array
    {
        return [];
    }

    protected static function baseQuery(ModelQueryBuilder $builder): ModelQueryBuilder
    {
        return $builder;
    }

    private static function newBuilder(): ModelQueryBuilder
    {
        $instance = new static();
        global $wpdb;
        $prefix = $wpdb->prefix ?? '';

        $meta = $instance->meta();
        $relations = $instance->relations();
        $schema = [];

        if (!empty($meta)) {
            $meta['table'] = $prefix . $meta['table'];
            $schema['meta'] = $meta;
        }

        if (!empty($relations)) {
            foreach ($relations as $name => $rel) {
                $rel['table'] = $prefix . $rel['table'];
                $schema['relations'][$name] = $rel;
            }
        }

        $builder = new QueryBuilder($schema);
        $builder->table($prefix . $instance->table);

        return static::baseQuery(new ModelQueryBuilder($builder, fn (array $row) => static::fromRow($row)));
    }

    /**
     * @return ModelQueryBuilder<static>
     */
    public static function query(): ModelQueryBuilder
    {
        return static::newBuilder();
    }

    public static function make(): static
    {
        return new static();
    }

    protected static function fromRow(array $row): static
    {
        $instance = new static();
        $ref = new ReflectionClass(static::class);

        foreach ($row as $key => $value) {
            if ($ref->hasProperty($key) && $ref->getProperty($key)->isPublic()) {
                $instance->$key = self::castValue($value, $ref->getProperty($key));
            } else {
                $instance->extras[$key] = $value;
            }
        }

        return $instance;
    }

    private static function castValue(mixed $value, ReflectionProperty $prop): mixed
    {
        $type = $prop->getType();

        if ($value === null) {
            // nullable prop
            if ($type?->allowsNull()) {
                return null;
            }

            // keep default value
            if ($prop->hasDefaultValue()) {
                return $prop->getDefaultValue();
            }
        }

        // why are you defining properties without a type??
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        // cast
        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            'array' => is_array($value) ? $value : (array) $value,
            default => $value,
        };
    }

    public function __get(string $name): mixed
    {
        return $this->extras[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->extras);
    }

    public function save(): QueryResult
    {
        $this->onBeforeSave();

        // filter out unset properties
        $data = array_filter($this->toArray(), fn ($v) => $v !== null);
        $pk = $this->primaryKey;
        $builder = static::newBuilder();

        // handle meta properties
        $meta = $this->meta();
        if (!empty($meta['aliases'])) {
            $builder->withMeta(...array_keys($meta['aliases']));
        }

        if (!empty($data[$pk])) {
            $id = $data[$pk];
            unset($data[$pk]);

            $result = $builder->where($pk, $id)->update($data);
            $this->onSave();

            return $result;
        }

        unset($data[$pk]);
        $result = $builder->insert($data);
        $this->$pk = $result->insertId;
        $this->onSave();

        return $result;
    }

    public function toArray(): array
    {
        // get only public props
        $public = Closure::bind(fn ($obj) => get_object_vars($obj), null, null)($this);

        return array_merge($public, $this->extras);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return static::newBuilder()->$method(...$args);
    }

    public static function schema(Closure $callback): void
    {
        static::$schemas[static::class] = $callback;
    }

    public static function migrate(bool $force = false): void
    {
        global $wpdb;

        $model = new static();
        $optionKey = 'queryable_' . $model->table . '_version';

        if (!$force && get_option($optionKey) === $model->version) {
            return;
        }

        $callback = static::$schemas[static::class] ?? null;

        if (!$callback) {
            throw new RuntimeException('No schema defined for ' . static::class . '. Call ' . static::class . '::schema() first.');
        }

        $prefix = $wpdb->prefix ?? '';
        $fullName = $prefix . $model->table;
        $charset = $wpdb->charset ?? 'utf8mb4';
        $collate = $wpdb->collate ?? 'utf8mb4_unicode_ci';

        $meta = $model->meta();
        $tableBuilder = new Table($charset, $collate, $meta);
        $callback($tableBuilder);

        $sqls = [$tableBuilder->compile($fullName) . ';'];

        if (!empty($meta)) {
            $sqls[] = $tableBuilder->compileMetaTable($fullName, $prefix) . ';';
        }

        dbDelta(implode("\n", $sqls));

        update_option($optionKey, $model->version);
    }

    public static function transaction(callable $callback): mixed
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $result = $callback();
            $wpdb->query('COMMIT');

            return $result;
        } catch (Throwable $up) {
            $wpdb->query('ROLLBACK');
            throw $up; // yep
        }
    }

    public static function getVersion(): string
    {
        return (new static())->version;
    }
}
