<?php

namespace Queryable;

use ArrayAccess;
use Closure;
use Queryable\Schema\Table;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

abstract class Model implements ArrayAccess
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected string $version = '1.0.0';

    /** [charset, collate]. Left empty, the table falls back to whatever $wpdb reports. */
    protected array $collation = ['utf8mb4', 'utf8mb4_unicode_520_ci'];

    private array $extras = [];
    private static array $schemas = [];

    /** @var array<class-string, array{json: array<string>}> */
    private static array $columnMetaCache = [];

    public function __construct()
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

    public static function make(array $attributes = []): static
    {
        $instance = new static();

        foreach ($attributes as $key => $value) {
            $instance->offsetSet($key, $value);
        }

        return $instance;
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

    /** @return array{json: array<string>, nullable: array<string>, autoIncrement: ?string} */
    private static function columnMeta(): array
    {
        $class = static::class;

        if (isset(self::$columnMetaCache[$class])) {
            return self::$columnMetaCache[$class];
        }

        $callback = static::$schemas[$class] ?? null;

        if (!$callback) {
            return self::$columnMetaCache[$class] = ['json' => [], 'nullable' => [], 'autoIncrement' => null];
        }

        $t = new Table();
        $callback($t);

        $jsonCols = [];
        $nullable = [];
        $autoIncrement = null;
        foreach ($t->getColumns() as $col) {
            $def = $col->getDefinition();
            // The flag, not the type: a JSON column is stored as LONGTEXT so
            // that dbDelta converges on MariaDB, which has no JSON type.
            if (! empty($def['json'])) {
                $jsonCols[] = $def['name'];
            }
            if (!empty($def['nullable'])) {
                $nullable[] = $def['name'];
            }
            if (!empty($def['autoIncrement'])) {
                $autoIncrement = $def['name'];
            }
        }

        return self::$columnMetaCache[$class] = [
            'json' => $jsonCols,
            'nullable' => $nullable,
            'autoIncrement' => $autoIncrement,
        ];
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

    /**
     * ArrayAccess so a hydrated model can also be read with ['key'] like the
     * assoc-array rows DB::table() returns. Mixing the two shapes used to be
     * fatal ("Cannot use object of type X as array"); reads now work either way.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset) || array_key_exists($offset, $this->extras);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (isset($this->$offset)) {
            return $this->$offset;
        }

        return $this->extras[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (property_exists($this, (string) $offset)) {
            $this->$offset = $value;
        } else {
            $this->extras[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->extras[$offset]);
    }

    /**
     * Reads the primary key to choose between an insert and an update, so it
     * only works where the database generates that key. A key the caller
     * supplies is always present and always selects the update, which creates
     * nothing while reporting success; a key that is absent from the schema is
     * never present and always selects the insert, which collides on the
     * second call. Both are silent, so neither is offered: insert() writes
     * those tables, and an update goes through the query builder.
     */
    public function save(): QueryResult
    {
        $pk = $this->primaryKey;

        if (isset(self::$schemas[static::class]) && static::columnMeta()['autoIncrement'] !== $pk) {
            throw new RuntimeException(
                static::class . '::$' . $pk . ' is not an AUTO_INCREMENT column, so save() cannot tell an insert '
                . 'from an update. Use insert(), or ' . static::class . '::query() for an update.'
            );
        }

        $this->onBeforeSave();

        $raw = $this->toArray();
        $isUpdate = !empty($raw[$pk]);
        $data = $this->writableData($raw, $isUpdate);

        $builder = static::newBuilder();

        // handle meta properties
        $meta = $this->meta();
        if (!empty($meta['aliases'])) {
            $builder->withMeta(...array_keys($meta['aliases']));
        }

        if ($isUpdate) {
            $id = $raw[$pk];
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

    /**
     * Writes every column the model holds without consulting the primary key,
     * which is the only correct write for a table whose key is supplied rather
     * than generated. A create-or-update on such a table has to be stated in
     * SQL, because nothing here can make a read and a write atomic.
     */
    public function insert(): QueryResult
    {
        $this->onBeforeSave();

        $result = static::newBuilder()->insert($this->writableData($this->toArray(), false));

        $this->onSave();

        return $result;
    }

    /**
     * A null is dropped rather than written, so an unset property does not
     * overwrite a column that has a default. An update keeps a null that
     * belongs to a nullable column, which is the only way to clear one.
     */
    private function writableData(array $raw, bool $keepNulls): array
    {
        $colMeta = static::columnMeta();

        $data = [];
        foreach ($raw as $col => $value) {
            if ($value === null) {
                if ($keepNulls && in_array($col, $colMeta['nullable'], true)) {
                    $data[$col] = null;
                }
                continue;
            }
            $data[$col] = $value;
        }

        foreach ($colMeta['json'] as $col) {
            if (isset($data[$col]) && is_array($data[$col])) {
                $data[$col] = json_encode($data[$col], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $data;
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

    /**
     * Lets a consumer compile a model's DDL without migrating it, which is how a
     * golden-DDL test locks a schema down. The alternative is reflecting into
     * this class's privates.
     */
    public static function schemaFor(string $class): ?Closure
    {
        return self::$schemas[$class] ?? null;
    }

    /**
     * The DDL migrate would run, without running it. A golden-DDL test that
     * compiled through its own path would lock down bytes migration never
     * executes, so both go through here.
     */
    public static function compileSchema(): string
    {
        return static::schemaBuilder()->compile(static::qualifiedTable());
    }

    public static function qualifiedTable(): string
    {
        global $wpdb;

        return ($wpdb->prefix ?? '') . (new static())->table;
    }

    private static function schemaBuilder(): Table
    {
        global $wpdb;

        $callback = self::$schemas[static::class] ?? null;

        if (!$callback) {
            throw new RuntimeException('No schema defined for ' . static::class . '. Call ' . static::class . '::schema() first.');
        }

        $model = new static();
        $charset = $model->collation[0] ?? $wpdb->charset ?? 'utf8mb4';
        $collate = $model->collation[1] ?? $wpdb->collate ?? 'utf8mb4_unicode_ci';

        $builder = new Table($charset, $collate, $model->meta());
        $callback($builder);

        return $builder;
    }

    public static function migrate(bool $force = false): void
    {
        global $wpdb;

        $model = new static();
        $optionKey = 'queryable_' . $model->table . '_version';

        if (!$force && get_option($optionKey) === $model->version) {
            return;
        }

        $prefix = $wpdb->prefix ?? '';
        $fullName = static::qualifiedTable();

        $sqls = [static::compileSchema() . ';'];

        $meta = $model->meta();

        if (!empty($meta)) {
            $sqls[] = static::schemaBuilder()->compileMetaTable($fullName, $prefix) . ';';
        }

        dbDelta(implode("\n", $sqls));

        // dbDelta() reports nothing usable when a CREATE is rejected, so recording
        // the version blind would latch a failed migration as a completed one and
        // never retry it without force.
        if (!self::tableExists($fullName)) {
            return;
        }

        update_option($optionKey, $model->version);
    }

    /**
     * SHOW TABLES rather than information_schema: it is the one probe that
     * ignores the temporary tables a test harness may have substituted, so it
     * answers the question migration gating actually asks.
     */
    public static function tableExists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
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
