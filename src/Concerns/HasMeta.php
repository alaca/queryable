<?php

namespace Queryable\Concerns;

use Queryable\Clauses\RawSQL;

trait HasMeta
{
    private array $metaKeys = [];      // alias => actual meta_key name
    private array $metaMultiple = [];  // alias => true for multi-value keys

    public function withMeta(string ...$keys): static
    {
        $metaConfig = $this->schema['meta'] ?? null;

        if (!$metaConfig) {
            throw new \RuntimeException('No meta configuration defined for this table.');
        }

        $aliases = $metaConfig['aliases'] ?? [];

        // No keys provided — use all defined aliases
        if (empty($keys)) {
            $keys = array_keys($aliases);
        }

        // Resolve friendly alias to actual meta_key name
        foreach ($keys as $key) {
            $aliasConfig = $aliases[$key] ?? $key;

            if (is_array($aliasConfig)) {
                $this->metaKeys[$key] = $aliasConfig['key'];
                if (!empty($aliasConfig['multiple'])) {
                    $this->metaMultiple[$key] = true;
                }
            } else {
                $this->metaKeys[$key] = $aliasConfig;
            }
        }

        if (empty($this->metaKeys)) {
            return $this;
        }

        $metaTable = $metaConfig['table'];
        $foreignKey = $metaConfig['foreignKey'];
        $primaryKey = $metaConfig['primaryKey'];
        $metaKeyCol = $metaConfig['metaKey'] ?? 'meta_key';
        $metaValueCol = $metaConfig['metaValue'] ?? 'meta_value';
        $baseTable = $this->query['table'][0]->name ?? null;

        if (empty($this->query['select'])) {
            $this->query['select'][] = new RawSQL($baseTable ? "{$baseTable}.*" : '*');
        }

        foreach ($this->metaKeys as $alias => $actualKey) {
            $joinAlias = "meta_{$alias}";
            $localRef = $baseTable ? "{$baseTable}.{$primaryKey}" : $primaryKey;

            $this->joinRaw(
                "LEFT JOIN {$metaTable} AS {$joinAlias} ON {$localRef} = {$joinAlias}.{$foreignKey} AND {$joinAlias}.{$metaKeyCol} = '{$actualKey}'",
            );

            if (!empty($this->metaMultiple[$alias])) {
                // Collect multiple values into a JSON array: ["val1","val2"]
                $this->query['select'][] = new RawSQL(
                    "CONCAT('[',GROUP_CONCAT(DISTINCT CONCAT('\"',{$joinAlias}.{$metaValueCol},'\"')),']') AS {$alias}",
                );
            } else {
                $this->query['select'][] = new RawSQL("{$joinAlias}.{$metaValueCol} AS {$alias}");
            }
        }

        return $this;
    }

    private function resolveColumn(string $column): string
    {
        if (isset($this->metaKeys[$column])) {
            return "meta_{$column}.meta_value";
        }

        return $column;
    }


    private function splitMeta(array $data): array
    {
        if (empty($this->metaKeys)) {
            return [$data, []];
        }

        $tableData = [];
        $metaData = [];

        foreach ($data as $key => $value) {
            if (isset($this->metaKeys[$key])) {
                $metaData[$this->metaKeys[$key]] = $value;
            } else {
                $tableData[$key] = $value;
            }
        }

        return [$tableData, $metaData];
    }


    private function insertMetaRows(int $id, array $metaData): void
    {
        $meta = $this->schema['meta'];
        $metaTable = $meta['table'];
        $foreignKey = $meta['foreignKey'];
        $metaKeyCol = $meta['metaKey'] ?? 'meta_key';
        $metaValueCol = $meta['metaValue'] ?? 'meta_value';

        $values = [];

        foreach ($metaData as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $values[] = "({$id}, '{$key}', " . RawSQL::escape($v) . ')';
                }
            } else {
                $values[] = "({$id}, '{$key}', " . RawSQL::escape($value) . ')';
            }
        }

        if (!empty($values)) {
            $this->execute(
                "INSERT INTO {$metaTable} ({$foreignKey}, {$metaKeyCol}, {$metaValueCol}) VALUES " . implode(', ', $values),
            );
        }
    }


    private function updateMetaRows(string $whereSQL, array $metaData): void
    {
        $meta = $this->schema['meta'];
        $metaTable = $meta['table'];
        $foreignKey = $meta['foreignKey'];
        $primaryKey = $meta['primaryKey'];
        $metaKeyCol = $meta['metaKey'] ?? 'meta_key';
        $metaValueCol = $meta['metaValue'] ?? 'meta_value';
        $baseTable = $this->query['table'][0]->name ?? null;

        $idResult = $this->execute("SELECT {$primaryKey} FROM {$baseTable} {$whereSQL}", ARRAY_A);
        $ids = array_column($idResult['rows'], $primaryKey);

        if (empty($ids)) {
            return;
        }

        $idList = implode(', ', $ids);

        // Collect multi-value keys to delete in one query
        $multiKeys = [];
        $insertValues = [];
        $singleUpdates = [];

        foreach ($metaData as $key => $value) {
            if (is_array($value)) {
                $multiKeys[] = "'{$key}'";
                foreach ($ids as $id) {
                    foreach ($value as $v) {
                        $insertValues[] = "({$id}, '{$key}', " . RawSQL::escape($v) . ')';
                    }
                }
            } else {
                $singleUpdates[$key] = $value;
            }
        }

        // Bulk delete all multi-value keys at once
        if (!empty($multiKeys)) {
            $keyList = implode(', ', $multiKeys);
            $this->execute("DELETE FROM {$metaTable} WHERE {$foreignKey} IN ({$idList}) AND {$metaKeyCol} IN ({$keyList})");
        }

        // Bulk insert all multi-value rows at once
        if (!empty($insertValues)) {
            $this->execute(
                "INSERT INTO {$metaTable} ({$foreignKey}, {$metaKeyCol}, {$metaValueCol}) VALUES " . implode(', ', $insertValues),
            );
        }

        // Single-value keys: upsert one at a time (can't batch these safely)
        foreach ($ids as $id) {
            foreach ($singleUpdates as $key => $value) {
                $escaped = RawSQL::escape($value);
                $result = $this->execute("UPDATE {$metaTable} SET {$metaValueCol} = {$escaped} WHERE {$foreignKey} = {$id} AND {$metaKeyCol} = '{$key}'");
                if ($result->affectedRows === 0) {
                    $this->execute("INSERT INTO {$metaTable} ({$foreignKey}, {$metaKeyCol}, {$metaValueCol}) VALUES ({$id}, '{$key}', {$escaped})");
                }
            }

            $this->execute(
                "INSERT INTO {$metaTable} ({$foreignKey}, {$metaKeyCol}, {$metaValueCol}) VALUES " . implode(', ', $insertValues),
            );
        }
    }

    /**
     * Delete all meta rows for matched main table rows. Called before the main DELETE
     */
    private function deleteMetaRows(string $whereSQL): void
    {
        $meta = $this->schema['meta'];
        $metaTable = $meta['table'];
        $foreignKey = $meta['foreignKey'];
        $primaryKey = $meta['primaryKey'];
        $baseTable = $this->query['table'][0]->name ?? null;

        $idResult = $this->execute("SELECT {$primaryKey} FROM {$baseTable} {$whereSQL}", ARRAY_A);
        $ids = array_column($idResult['rows'], $primaryKey);

        if (empty($ids)) {
            return;
        }

        $idList = implode(', ', $ids);
        $this->execute("DELETE FROM {$metaTable} WHERE {$foreignKey} IN ({$idList})");
    }

    /**
     * Decode GROUP_CONCAT JSON strings back into PHP arrays for multi-value meta keys.
     */
    private function parseMetaResults(array $rows): array
    {
        if (empty($this->metaMultiple)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $isObject = is_object($row);

            foreach ($this->metaMultiple as $alias => $flag) {
                $value = $isObject ? ($row->$alias ?? null) : ($row[$alias] ?? null);

                if ($value !== null && is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        if ($isObject) {
                            $row->$alias = $decoded;
                        } else {
                            $row[$alias] = $decoded;
                        }
                    }
                } else {
                    if ($isObject) {
                        $row->$alias = [];
                    } else {
                        $row[$alias] = [];
                    }
                }
            }
        }
        unset($row);

        return $rows;
    }
}
