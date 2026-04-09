<?php

namespace Queryable\Concerns;

use Queryable\Clauses\{Data, RawSQL};
use Queryable\QueryResult;

/**
 * INSERT, UPDATE, DELETE, TRUNCATE, UPSERT, increment, decrement.
 * Handles meta splitting automatically when withMeta() is active
 */
trait HasMutations
{
    private function setData(array $data): static
    {
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $row) {
                $rowData = [];
                foreach ($row as $key => $value) {
                    $rowData[] = new Data($key, $value);
                }
                $this->query['data'][] = $rowData;
            }
        } else {
            foreach ($data as $key => $value) {
                $this->query['data'][] = new Data($key, $value);
            }
        }

        return $this;
    }

    public function insert(array $data): QueryResult
    {
        global $wpdb;

        [$tableData, $metaData] = $this->splitMeta($data);

        $this->setQueryType('INSERT');
        $this->setData($tableData);
        $result = $this->run($this->toSQL());

        if (!empty($metaData) && $wpdb && $result->insertId) {
            $this->insertMetaRows($result->insertId, $metaData);
        }

        return $result;
    }

    public function upsert(array $data, array $conflictColumns, array $updateColumns): QueryResult
    {
        $this->setQueryType('INSERT');
        $this->setData($data);
        $this->query['upsertConflict'] = $conflictColumns;
        $this->query['upsertUpdate'] = $updateColumns;

        return $this->run($this->toSQL());
    }

    public function insertRaw(string $sql, mixed ...$args): QueryResult
    {
        $this->setQueryType('INSERT');
        $this->query['data'][] = new RawSQL($sql, $args);

        return $this->run($this->toSQL());
    }

    public function update(array $data): QueryResult
    {
        global $wpdb;

        [$tableData, $metaData] = $this->splitMeta($data);

        if (!empty($metaData) && $wpdb) {
            $whereSQL = $this->compiler->compileWhere() ?? '';
            $this->updateMetaRows($whereSQL, $metaData);
        }

        if (!empty($tableData)) {
            $this->setQueryType('UPDATE');
            $this->setData($tableData);

            return $this->run($this->toSQL());
        }

        return new QueryResult();
    }

    public function updateRaw(string $sql, mixed ...$args): QueryResult
    {
        $this->setQueryType('UPDATE');
        $this->query['data'][] = new RawSQL($sql, $args);

        return $this->run($this->toSQL());
    }

    public function increment(string $column, int $amount = 1): QueryResult
    {
        return $this->updateRaw("{$column} = {$column} + {$amount}");
    }

    public function decrement(string $column, int $amount = 1): QueryResult
    {
        return $this->updateRaw("{$column} = {$column} - {$amount}");
    }

    public function delete(): QueryResult
    {
        global $wpdb;

        if (!empty($this->schema['meta']) && $wpdb) {
            $whereSQL = $this->compiler->compileWhere() ?? '';
            $this->deleteMetaRows($whereSQL);
        }

        $this->setQueryType('DELETE');

        return $this->run($this->toSQL());
    }

    public function truncate(): QueryResult
    {
        global $wpdb;

        if (!empty($this->schema['meta']) && $wpdb) {
            $metaTable = $this->schema['meta']['table'];
            $this->execute("TRUNCATE TABLE {$metaTable}");
        }

        $this->setQueryType('TRUNCATE');

        return $this->run($this->toSQL());
    }
}
