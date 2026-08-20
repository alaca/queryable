<?php

namespace Queryable\Tests;

use Queryable\DB;
use ReflectionProperty;
use RuntimeException;

if (!function_exists('tests_add_filter')) {
    return;
}

/**
 * The nested-transaction contract against a real database.
 *
 * A host that already holds a transaction open (WordPress's own test case does
 * exactly this, and so does any caller that wrapped a batch) pins the nesting
 * depth so product code runs nested. What that has to buy is a throw inside
 * DB::transaction() undoing that block's writes and nothing else, with the
 * enclosing transaction still open afterwards.
 */
class SavepointTest extends \WP_UnitTestCase
{
    private string $table;

    public function set_up(): void
    {
        parent::set_up();

        global $wpdb;

        // Real tables, as ModelTest does: the harness rewrites CREATE TABLE to
        // CREATE TEMPORARY TABLE, and the point here is ordinary InnoDB rows.
        remove_filter('query', [$this, '_create_temporary_tables']);
        remove_filter('query', [$this, '_drop_temporary_tables']);

        $this->table = $wpdb->prefix . 'queryable_savepoints';

        // Outside any transaction: DDL implicitly commits, which would destroy
        // savepoints taken later in the test.
        $wpdb->query('ROLLBACK');
        $wpdb->query("DROP TABLE IF EXISTS {$this->table}");
        $wpdb->query(
            "CREATE TABLE {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                label VARCHAR(64) NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB",
        );

        $this->setDepth(0);
    }

    public function tear_down(): void
    {
        global $wpdb;

        $this->setDepth(0);
        $wpdb->query('ROLLBACK');
        $wpdb->query("DROP TABLE IF EXISTS {$this->table}");

        parent::tear_down();
    }

    private function setDepth(int $depth): void
    {
        $property = new ReflectionProperty(DB::class, 'transactionDepth');
        $property->setAccessible(true);
        $property->setValue(null, $depth);
    }

    private function insert(string $label): void
    {
        DB::table('queryable_savepoints')->insert(['label' => $label]);
    }

    /** @return string[] */
    private function labels(): array
    {
        global $wpdb;

        return $wpdb->get_col("SELECT label FROM {$this->table} ORDER BY id");
    }

    /**
     * The property the whole thing exists for: with the depth pinned, a throw
     * inside a product transaction really does undo that block.
     */
    public function test_a_nested_rollback_is_observable(): void
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        $this->insert('outer-before');

        $this->setDepth(1);

        try {
            DB::transaction(function (): void {
                $this->insert('inner');
                throw new RuntimeException('boom');
            });
            $this->fail('expected the callback exception to propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->setDepth(0);

        $this->assertSame(['outer-before'], $this->labels(), 'the inner write must be gone');
    }

    /**
     * Rolling back to a savepoint leaves the enclosing transaction open: the
     * host can keep writing and its own commit still decides everything.
     */
    public function test_the_enclosing_transaction_survives_a_nested_rollback(): void
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        $this->insert('outer-before');

        $this->setDepth(1);

        try {
            DB::transaction(function (): void {
                $this->insert('inner');
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $e) {
            // asserted below
        }

        $this->setDepth(0);

        // Still inside the same transaction, so this write joins it.
        $this->insert('outer-after');
        $wpdb->query('COMMIT');

        $this->assertSame(['outer-before', 'outer-after'], $this->labels());
    }

    /**
     * Savepoints would otherwise pile up for the life of the transaction, so a
     * block that returns normally must release its own.
     */
    public function test_a_successful_nested_block_releases_its_savepoint(): void
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        $this->setDepth(1);

        $seen = [];
        $recorder = static function (string $sql) use (&$seen): string {
            if (preg_match('/^\s*(SAVEPOINT|RELEASE SAVEPOINT|ROLLBACK TO SAVEPOINT)\b/i', $sql)) {
                $seen[] = trim($sql);
            }

            return $sql;
        };
        add_filter('query', $recorder);

        DB::transaction(function (): void {
            $this->insert('kept');
        });

        remove_filter('query', $recorder);
        $this->setDepth(0);

        $this->assertSame(
            ['SAVEPOINT queryable_sp_1', 'RELEASE SAVEPOINT queryable_sp_1'],
            $seen,
            'a block that returns must take a savepoint and give it back',
        );

        // And the release really took effect, rather than merely being sent.
        $wpdb->suppress_errors(true);
        $wpdb->query('ROLLBACK TO SAVEPOINT queryable_sp_1');
        $error = $wpdb->last_error;
        $wpdb->suppress_errors(false);
        $wpdb->last_error = '';

        $this->assertNotEmpty($error, 'the savepoint should be gone after a successful block');

        $wpdb->query('COMMIT');
        $this->assertSame(['kept'], $this->labels());
    }

    /**
     * Two levels deep: the inner rollback must not reach the level above it.
     */
    public function test_an_inner_rollback_leaves_the_level_above_intact(): void
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        $this->setDepth(1);

        DB::transaction(function (): void {
            $this->insert('level-one');

            try {
                DB::transaction(function (): void {
                    $this->insert('level-two');
                    throw new RuntimeException('inner');
                });
            } catch (RuntimeException $e) {
                // Swallowed on purpose: level one carries on.
            }

            $this->insert('level-one-after');
        });

        $this->setDepth(0);
        $wpdb->query('COMMIT');

        $this->assertSame(['level-one', 'level-one-after'], $this->labels());
    }

    /**
     * Unpinned, the outermost call still owns a real transaction.
     */
    public function test_an_unpinned_transaction_still_commits_for_real(): void
    {
        DB::transaction(function (): void {
            $this->insert('committed');
        });

        $this->assertSame(['committed'], $this->labels());
    }

    public function test_an_unpinned_transaction_still_rolls_back_for_real(): void
    {
        try {
            DB::transaction(function (): void {
                $this->insert('discarded');
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $e) {
            // asserted below
        }

        $this->assertSame([], $this->labels());
    }
}
