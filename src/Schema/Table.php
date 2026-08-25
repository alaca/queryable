<?php

namespace Queryable\Schema;

use InvalidArgumentException;

/**
 * Defines table columns for migrations
 */
class Table
{
    private array $columns = [];
    private array $metaConfig = [];
    private array $relations = [];
    private array $indexes = [];
    private array $compositeUniques = [];
    private array $compositePrimary = [];
    private ?string $rowFormat = null;
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
        $col = new Column($name, 'BIGINT');
        $col->unsigned()->autoIncrement()->primary();
        $this->columns[] = $col;

        return $col;
    }

    public function string(string $name, int $length = 255): Column
    {
        $col = new Column($name, "VARCHAR({$length})");
        $this->columns[] = $col;

        return $col;
    }

    public function char(string $name, int $length = 255): Column
    {
        $col = new Column($name, "CHAR({$length})");
        $this->columns[] = $col;

        return $col;
    }

    public function binary(string $name, int $length = 16): Column
    {
        $col = new Column($name, "BINARY({$length})");
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

    public function smallInteger(string $name): Column
    {
        $col = new Column($name, 'SMALLINT');
        $this->columns[] = $col;

        return $col;
    }

    /**
     * A width is only ever a display hint, never a range, but tinyint(1) is the
     * one clients read as boolean, so a column that means true or false has to
     * be able to say so.
     */
    public function tinyInteger(string $name, ?int $length = null): Column
    {
        $col = new Column($name, $length === null ? 'TINYINT' : "TINYINT({$length})");
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

    /**
     * Stored as LONGTEXT, flagged as JSON.
     *
     * MariaDB has no JSON type: JSON there is a parser alias for LONGTEXT, so a
     * column declared json is reported back as longtext. dbDelta compares the
     * emitted type against the reported one literally, sees longtext against
     * json, and re-issues ALTER TABLE ... CHANGE COLUMN on every migration. That
     * never converges, and each one rewrites the whole table.
     *
     * Emitting LONGTEXT is what both engines report back. The flag, not the type
     * string, is what tells the model to encode and decode.
     */
    public function json(string $name): Column
    {
        $col = new Column($name, 'LONGTEXT', true);
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

    /** @return array<Column> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function index(string|array $columns, ?string $name = null): static
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $this->indexes[] = ['cols' => $cols, 'name' => $name];

        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): static
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $this->compositeUniques[] = ['cols' => $cols, 'name' => $name];

        return $this;
    }

    /**
     * A table may carry one PRIMARY KEY, so declaring a composite one suppresses
     * every column-level primary() (id() sets one implicitly). Two PRIMARY KEY
     * clauses in one CREATE TABLE is MySQL error 1068, and dbDelta cannot drop a
     * primary key afterwards, so the first install is the only chance to get it
     * right.
     */
    public function primary(array $columns): static
    {
        $known = array_map(fn (Column $c): string => $c->getDefinition()['name'], $this->columns);

        foreach ($columns as $col) {
            if (!in_array($col, $known, true)) {
                throw new InvalidArgumentException("Unknown column in primary key: {$col}");
            }
        }

        $this->compositePrimary = $columns;

        return $this;
    }

    /**
     * DYNAMIC is what raises the index key limit from 767 to 3072 bytes. InnoDB
     * defaults to it on MySQL 5.7+, but declaring it is what makes the limit
     * true on an install whose innodb_default_row_format was changed.
     */
    public function rowFormat(string $format): static
    {
        $this->rowFormat = strtoupper($format);

        return $this;
    }

    public function collation(string $charset, string $collate): static
    {
        $this->charset = $charset;
        $this->collate = $collate;

        return $this;
    }

    /**
     * Backtick-quote a column identifier so a reserved-word column name (e.g.
     * `trigger`, `cursor`) still compiles to valid DDL. Embedded backticks are
     * escaped by doubling, the standard MySQL escape.
     */
    private function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function generateIndexName(string $prefix, array $columns): string
    {
        $base = $prefix . '_' . implode('_', array_map(
            fn ($c) => preg_replace('/[^a-z0-9_]/', '', strtolower($c)),
            $columns
        ));

        if (strlen($base) <= 64) {
            return $base;
        }

        $suffix = substr(hash('crc32b', implode('|', $columns)), 0, 6);

        return substr($base, 0, 64 - 7) . '_' . $suffix;
    }

    /**
     * Normalise a column type to the shape MySQL reports (and dbDelta expects):
     * lowercase keyword + the default integer display width. Args (lengths,
     * decimal scale, enum values) are preserved verbatim so quoted values keep
     * their case.
     */
    private function canonicalType(string $type, bool $unsigned): string
    {
        $type = trim($type);
        if (!preg_match('/^([A-Za-z]+)\s*(\(.*\))?$/s', $type, $m)) {
            return strtolower($type);
        }
        $base = strtolower($m[1]);
        $args = $m[2] ?? '';

        if ($args === '') {
            $widths = [
                'bigint'    => '(20)',
                'int'       => $unsigned ? '(10)' : '(11)',
                'mediumint' => $unsigned ? '(8)'  : '(9)',
                'smallint'  => $unsigned ? '(5)'  : '(6)',
                'tinyint'   => $unsigned ? '(3)'  : '(4)',
            ];
            $args = $widths[$base] ?? '';
        }

        return $base . $args;
    }

    private function tableOptions(): string
    {
        $options = "DEFAULT CHARSET={$this->charset} COLLATE={$this->collate}";

        if ($this->rowFormat) {
            $options .= " ROW_FORMAT={$this->rowFormat}";
        }

        return $options;
    }

    public function compile(string $tableName): string
    {
        $defs = [];
        $constraints = [];

        foreach ($this->columns as $col) {
            $def = $col->getDefinition();

            // dbDelta() compares the desired column SQL against MySQL's reported
            // schema (lowercase, with integer display widths). Emit the same
            // canonical shape so a re-run converges to a no-op instead of an
            // endless ALTER ... CHANGE COLUMN.
            $line = $this->quoteIdentifier($def['name']) . ' ' . $this->canonicalType($def['type'], (bool) $def['unsigned']);

            if ($def['unsigned']) {
                $line .= ' unsigned';
            }

            // MySQL only accepts the character set between the type and the NULL
            // constraint; after NOT NULL it is a syntax error.
            if ($def['charset']) {
                $line .= ' CHARACTER SET ' . $def['charset'];

                if ($def['collate']) {
                    $line .= ' COLLATE ' . $def['collate'];
                }
            }

            if (!$def['nullable']) {
                $line .= ' NOT NULL';
            }

            if ($def['autoIncrement']) {
                $line .= ' AUTO_INCREMENT';
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

            $defs[] = $line;

            if ($def['primary']) {
                if (!$this->compositePrimary) {
                    $constraints[] = "PRIMARY KEY  ({$this->quoteIdentifier($def['name'])})";
                } elseif ($def['autoIncrement'] && !in_array($def['name'], $this->compositePrimary, true)) {
                    // Suppressing this column's primary key would leave the auto
                    // increment column in no key at all, which is MySQL error 1075.
                    throw new InvalidArgumentException(
                        "Auto increment column {$def['name']} must be part of the composite primary key"
                    );
                }
            }

            // Column-level unique() becomes a separate UNIQUE KEY line (like
            // composite uniques); inline UNIQUE is invisible to dbDelta and
            // re-adds the index every migrate ("Too many keys").
            if ($def['unique']) {
                $name = $this->generateIndexName('uk', [$def['name']]);
                $constraints[] = "UNIQUE KEY {$name} ({$this->quoteIdentifier($def['name'])})";
            }

            if ($def['references']) {
                $fk = "FOREIGN KEY ({$this->quoteIdentifier($def['name'])}) REFERENCES {$def['references']['table']}({$def['references']['column']})";
                if ($def['onDelete']) {
                    $fk .= " ON DELETE {$def['onDelete']}";
                }
                $constraints[] = $fk;
            }
        }

        if ($this->compositePrimary) {
            $colsList = implode(', ', array_map(fn ($c) => $this->quoteIdentifier($c), $this->compositePrimary));
            array_unshift($constraints, "PRIMARY KEY ({$colsList})");
        }

        foreach ($this->columns as $col) {
            $def = $col->getDefinition();
            if (!empty($def['index'])) {
                $name = $def['indexName'] ?? $this->generateIndexName('idx', [$def['name']]);
                $constraints[] = "KEY {$name} ({$this->quoteIdentifier($def['name'])})";
            }
        }

        foreach ($this->indexes as $idx) {
            $name = $idx['name'] ?? $this->generateIndexName('idx', $idx['cols']);
            $colsList = implode(',', array_map(fn ($c) => $this->quoteIdentifier($c), $idx['cols']));
            $constraints[] = "KEY {$name} ({$colsList})";
        }

        foreach ($this->compositeUniques as $idx) {
            $name = $idx['name'] ?? $this->generateIndexName('uk', $idx['cols']);
            $colsList = implode(',', array_map(fn ($c) => $this->quoteIdentifier($c), $idx['cols']));
            $constraints[] = "UNIQUE KEY {$name} ({$colsList})";
        }

        $all = array_merge($defs, $constraints);

        // dbDelta() requires each column on its own line
        return "CREATE TABLE {$tableName} (\n" . implode(",\n", $all) . "\n) " . $this->tableOptions();
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
            . "meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
            . "{$singularId} bigint(20) unsigned NOT NULL,\n"
            . "meta_key varchar(255) NOT NULL,\n"
            . "meta_value longtext,\n"
            . "PRIMARY KEY  (meta_id),\n"
            . "KEY {$singularId} ({$singularId}),\n"
            . "KEY meta_key (meta_key)\n"
            . ') ' . $this->tableOptions();
    }
}
