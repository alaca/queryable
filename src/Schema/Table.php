<?php

namespace Queryable\Schema;

/**
 * Defines table columns for migrations
 */
class Table
{
    private array $columns = [];
    private array $metaConfig = [];
    private array $relations = [];
    private string $charset;
    private string $collate;

    public function __construct(string $charset = 'utf8mb4', string $collate = 'utf8mb4_unicode_ci', array $metaConfig = [])
    {
        $this->charset = $charset;
        $this->collate = $collate;
        $this->metaConfig = $metaConfig;
    }

    public function id(string $name = 'id'): Column
    {
        $col = new Column($name, 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        $col->primary();
        $this->columns[] = $col;

        return $col;
    }

    public function string(string $name, int $length = 255): Column
    {
        $col = new Column($name, "VARCHAR({$length})");
        $this->columns[] = $col;

        return $col;
    }

    public function text(string $name): Column
    {
        $col = new Column($name, 'TEXT');
        $this->columns[] = $col;

        return $col;
    }

    public function longText(string $name): Column
    {
        $col = new Column($name, 'LONGTEXT');
        $this->columns[] = $col;

        return $col;
    }

    public function integer(string $name): Column
    {
        $col = new Column($name, 'INT');
        $this->columns[] = $col;

        return $col;
    }

    public function bigInteger(string $name): Column
    {
        $col = new Column($name, 'BIGINT');
        $this->columns[] = $col;

        return $col;
    }

    public function tinyInteger(string $name): Column
    {
        $col = new Column($name, 'TINYINT');
        $this->columns[] = $col;

        return $col;
    }

    public function float(string $name): Column
    {
        $col = new Column($name, 'FLOAT');
        $this->columns[] = $col;

        return $col;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): Column
    {
        $col = new Column($name, "DECIMAL({$precision},{$scale})");
        $this->columns[] = $col;

        return $col;
    }

    public function boolean(string $name): Column
    {
        $col = new Column($name, 'TINYINT(1)');
        $this->columns[] = $col;

        return $col;
    }

    public function date(string $name): Column
    {
        $col = new Column($name, 'DATE');
        $this->columns[] = $col;

        return $col;
    }

    public function datetime(string $name): Column
    {
        $col = new Column($name, 'DATETIME');
        $this->columns[] = $col;

        return $col;
    }

    public function timestamp(string $name): Column
    {
        $col = new Column($name, 'TIMESTAMP');
        $this->columns[] = $col;

        return $col;
    }

    public function json(string $name): Column
    {
        $col = new Column($name, 'JSON');
        $this->columns[] = $col;

        return $col;
    }

    public function enum(string $name, array $values): Column
    {
        $escaped = implode("','", $values);
        $col = new Column($name, "ENUM('{$escaped}')");
        $this->columns[] = $col;

        return $col;
    }

    public function hasMetaConfig(): bool
    {
        return !empty($this->metaConfig);
    }

    public function hasMany(string $relatedTable, string $foreignKey): void
    {
        $this->relations[] = [
            'table' => $relatedTable,
            'foreignKey' => $foreignKey,
            'type' => 'hasMany',
        ];
    }

    public function hasOne(string $relatedTable, string $foreignKey): void
    {
        $this->relations[] = [
            'table' => $relatedTable,
            'foreignKey' => $foreignKey,
            'type' => 'hasOne',
        ];
    }

    public function belongsTo(string $relatedTable, string $foreignKey): void
    {
        $this->relations[] = [
            'table' => $relatedTable,
            'foreignKey' => $foreignKey,
            'type' => 'belongsTo',
        ];
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function compile(string $tableName): string
    {
        $defs = [];
        $constraints = [];

        foreach ($this->columns as $col) {
            $def = $col->getDefinition();
            $type = $def['type'];

            $line = "{$def['name']} {$type}";

            if ($def['unsigned']) {
                $line .= ' UNSIGNED';
            }

            if (!$def['nullable'] && !str_contains($type, 'NOT NULL')) {
                $line .= ' NOT NULL';
            }

            if (str_contains($type, 'AUTO_INCREMENT')) {
                // Let PRIMARY KEY be added as a separate constraint for dbDelta compatibility
            } elseif ($def['hasDefault']) {
                if ($def['default'] === null) {
                    $line .= ' DEFAULT NULL';
                } elseif (is_string($def['default'])) {
                    $line .= " DEFAULT '{$def['default']}'";
                } elseif (is_bool($def['default'])) {
                    $line .= ' DEFAULT ' . ($def['default'] ? '1' : '0');
                } else {
                    $line .= " DEFAULT {$def['default']}";
                }
            }

            if ($def['unique']) {
                $line .= ' UNIQUE';
            }

            $defs[] = $line;

            if ($def['primary']) {
                $constraints[] = "PRIMARY KEY ({$def['name']})";
            }

            if ($def['references']) {
                $fk = "FOREIGN KEY ({$def['name']}) REFERENCES {$def['references']['table']}({$def['references']['column']})";
                if ($def['onDelete']) {
                    $fk .= " ON DELETE {$def['onDelete']}";
                }
                $constraints[] = $fk;
            }
        }

        $all = array_merge($defs, $constraints);

        // dbDelta() requires each column on its own line
        return "CREATE TABLE {$tableName} (\n" . implode(",\n", $all) . "\n) DEFAULT CHARACTER SET {$this->charset} COLLATE {$this->collate}";
    }

    public function compileMetaTable(string $tableName, string $prefix = ''): string
    {
        if (!empty($this->metaConfig['table'])) {
            $metaTable = $prefix . $this->metaConfig['table'];
        } else {
            $metaTable = "{$tableName}_meta";
        }

        if (!empty($this->metaConfig['foreignKey'])) {
            $singularId = $this->metaConfig['foreignKey'];
        } else {
            $base = preg_replace('/^[a-z]+_/', '', $tableName);
            $singularId = rtrim($base, 's') . '_id';
        }

        return "CREATE TABLE {$metaTable} (\n"
            . "meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "{$singularId} BIGINT UNSIGNED NOT NULL,\n"
            . "meta_key VARCHAR(255) NOT NULL,\n"
            . "meta_value LONGTEXT,\n"
            . "PRIMARY KEY (meta_id),\n"
            . "KEY {$singularId} ({$singularId}),\n"
            . "KEY meta_key (meta_key)\n"
            . ") DEFAULT CHARACTER SET {$this->charset} COLLATE {$this->collate}";
    }
}
