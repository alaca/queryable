<?php

namespace Queryable;

use Closure;
use Queryable\Schema\Table;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected string $version = '1.0.0';

    private array $extras = [];
    private static array $schemas = [];

    /** @var array<class-string, array{json: array<string>}> */
    private static array $columnMetaCache = [];

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
        $jsonCols = static::columnMeta()['json'];
        foreach ($jsonCols as $col) {
            if (isset($row[$col]) && is_string($row[$col]) && $row[$col] !== '') {
                $decoded = json_decode($row[$col], true);
                if (is_array($decoded)) {
                    $row[$col] = $decoded;
                }
            }
        }

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

    /** @return array{json: array<string>} */
    private static function columnMeta(): array
    {
        $class = static::class;

        if (isset(self::$columnMetaCache[$class])) {
            return self::$columnMetaCache[$class];
        }

        $callback = static::$schemas[$class] ?? null;

        if (!$callback) {
            return self::$columnMetaCache[$class] = ['json' => []];
        }

        $t = new Table();
        $callback($t);

        $jsonCols = [];
        foreach ($t->getColumns() as $col) {
            $def = $col->getDefinition();
            if (stripos((string) $def['type'], 'JSON') !== false) {
                $jsonCols[] = $def['name'];
            }
        }

        return self::$columnMetaCache[$class] = ['json' => $jsonCols];
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

        $jsonCols = static::columnMeta()['json'];
        foreach ($jsonCols as $col) {
            if (isset($data[$col]) && is_array($data[$col])) {
                $data[$col] = json_encode($data[$col], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

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
        return DB::transaction($callback);
    }

    public static function getVersion(): string
    {
        return (new static())->version;
    }
}
