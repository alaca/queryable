<?php

namespace Queryable\Concerns;

/**
 * Eager loading via with()
 */
trait HasRelations
{
    private array $eagerLoad = [];

    public function with(string ...$relations): static
    {
        $defined = $this->schema['relations'] ?? [];
        foreach ($relations as $name) {
            if (!isset($defined[$name])) {
                throw new \RuntimeException("Relation '{$name}' is not defined.");
            }
            $this->eagerLoad[] = $name;
        }

        return $this;
    }

    private function loadRelations(array $rows): array
    {
        if (empty($this->eagerLoad) || empty($rows)) {
            return $rows;
        }

        $defined = $this->schema['relations'] ?? [];
        $isObject = isset($rows[0]) && is_object($rows[0]);

        foreach ($this->eagerLoad as $name) {
            $rel = $defined[$name];
            $primaryKeyName = $rel['primaryKey'];
            $foreignKeyName = $rel['foreignKey'];

            $primaryKeys = array_filter(array_map(
                fn ($row) => $isObject ? ($row->$primaryKeyName ?? null) : ($row[$primaryKeyName] ?? null),
                $rows,
            ), fn ($k) => $k !== null);

            if (empty($primaryKeys)) {
                continue;
            }

            $placeholders = implode(', ', array_map(fn ($k) => is_string($k) ? "'{$k}'" : $k, $primaryKeys));
            $result = $this->execute("SELECT * FROM {$rel['table']} WHERE {$foreignKeyName} IN ({$placeholders})");

            $grouped = [];
            foreach ($result['rows'] as $relRow) {
                $grouped[$relRow[$foreignKeyName]][] = $relRow;
            }

            foreach ($rows as &$row) {
                $key = $isObject ? ($row->$primaryKeyName ?? null) : ($row[$primaryKeyName] ?? null);
                $related = $grouped[$key] ?? [];
                $value = $rel['type'] === 'hasMany' ? $related : ($related[0] ?? null);

                if ($isObject) {
                    $row->$name = $value;
                } else {
                    $row[$name] = $value;
                }
            }
            unset($row);
        }

        return $rows;
    }
}
