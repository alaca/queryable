<?php

namespace Queryable\Schema;

/**
 * Single column definition with modifiers
 */
class Column
{
    private array $definition;

    /**
     * $json marks a column the model encodes and decodes, which is not the same
     * question as what type it is stored as. MariaDB has no JSON type, so a JSON
     * column is physically LONGTEXT there and the type string cannot carry the
     * answer.
     */
    public function __construct(string $name, string $type, bool $json = false)
    {
        $this->definition = [
            'name' => $name,
            'type' => $type,
            'json' => $json,
            'nullable' => false,
            'default' => null,
            'hasDefault' => false,
            'unique' => false,
            'primary' => false,
            'autoIncrement' => false,
            'unsigned' => false,
            'references' => null,
            'onDelete' => null,
            'index' => false,
            'indexName' => null,
        ];
    }

    public function nullable(): static
    {
        $this->definition['nullable'] = true;

        return $this;
    }

    public function unique(): static
    {
        $this->definition['unique'] = true;

        return $this;
    }

    public function primary(): static
    {
        $this->definition['primary'] = true;

        return $this;
    }

    public function unsigned(): static
    {
        $this->definition['unsigned'] = true;

        return $this;
    }

    public function autoIncrement(): static
    {
        $this->definition['autoIncrement'] = true;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->definition['default'] = $value;
        $this->definition['hasDefault'] = true;

        return $this;
    }

    public function references(string $table, string $column = 'id'): static
    {
        $this->definition['references'] = ['table' => $table, 'column' => $column];

        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->definition['onDelete'] = $action;

        return $this;
    }

    public function index(?string $name = null): static
    {
        $this->definition['index'] = true;
        $this->definition['indexName'] = $name;

        return $this;
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }
}
