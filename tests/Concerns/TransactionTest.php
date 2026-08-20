<?php

namespace Queryable\Tests\Concerns;

use PHPUnit\Framework\TestCase;
use Queryable\DB;
use Queryable\QueryException;
use ReflectionProperty;
use RuntimeException;

/**
 * A $wpdb stand-in that records the SQL it is handed and can be told to fail
 * on a chosen statement, the way $wpdb reports failure: a falsy return plus a
 * populated last_error.
 */
class RecordingWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $rows_affected = 0;
    public int $insert_id = 0;

    /** @var string[] */
    public array $queries = [];

    /** @var string[] statements (matched by prefix) that should report an error */
    public array $failOn = [];

    public function query(string $sql)
    {
        $this->queries[] = $sql;
        $this->last_error = '';

        foreach ($this->failOn as $needle) {
            if (stripos($sql, $needle) === 0) {
                $this->last_error = 'simulated failure for: ' . $sql;

                return false;
            }
        }

        return 0;
    }
}

/**
 * Nested DB::transaction() calls must be real nested transactions.
 *
 * The outermost call owns START TRANSACTION / COMMIT / ROLLBACK. Anything
 * inside it gets a SAVEPOINT, so a throw undoes that block's writes and
 * nothing else, leaving the enclosing transaction open.
 */
class TransactionTest extends TestCase
{
    private RecordingWpdb $wpdb;

    protected function setUp(): void
    {
        global $wpdb;

        $this->wpdb = new RecordingWpdb();
        $wpdb = $this->wpdb;

        $this->setDepth(0);
        DB::reset();
    }

    protected function tearDown(): void
    {
        $this->setDepth(0);
    }

    private function setDepth(int $depth): void
    {
        $property = new ReflectionProperty(DB::class, 'transactionDepth');
        $property->setAccessible(true);
        $property->setValue(null, $depth);
    }

    private function depth(): int
    {
        $property = new ReflectionProperty(DB::class, 'transactionDepth');
        $property->setAccessible(true);

        return (int) $property->getValue();
    }

    public function test_outermost_transaction_commits(): void
    {
        $result = DB::transaction(static fn () => 'value');

        $this->assertSame('value', $result);
        $this->assertSame(['START TRANSACTION', 'COMMIT'], $this->wpdb->queries);
    }

    public function test_outermost_transaction_rolls_back_on_throw(): void
    {
        try {
            DB::transaction(static function (): void {
                throw new RuntimeException('boom');
            });
            $this->fail('expected the callback exception to propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $this->wpdb->queries);
    }

    public function test_nested_transaction_takes_a_savepoint_and_releases_it(): void
    {
        $this->setDepth(1);

        $result = DB::transaction(static fn () => 'value');

        $this->assertSame('value', $result);
        $this->assertSame(
            ['SAVEPOINT queryable_sp_1', 'RELEASE SAVEPOINT queryable_sp_1'],
            $this->wpdb->queries,
        );
    }

    public function test_nested_transaction_never_starts_a_transaction(): void
    {
        $this->setDepth(1);

        DB::transaction(static fn () => null);

        $this->assertNotContains('START TRANSACTION', $this->wpdb->queries);
        $this->assertNotContains('COMMIT', $this->wpdb->queries);
        $this->assertNotContains('ROLLBACK', $this->wpdb->queries);
    }

    public function test_nested_transaction_rolls_back_to_its_savepoint_on_throw(): void
    {
        $this->setDepth(1);
        $thrown = new RuntimeException('boom');

        try {
            DB::transaction(static function () use ($thrown): void {
                throw $thrown;
            });
            $this->fail('expected the callback exception to propagate');
        } catch (RuntimeException $e) {
            $this->assertSame($thrown, $e, 'the original exception must propagate unchanged');
        }

        $this->assertSame(
            ['SAVEPOINT queryable_sp_1', 'ROLLBACK TO SAVEPOINT queryable_sp_1'],
            $this->wpdb->queries,
        );
        $this->assertNotContains('ROLLBACK', $this->wpdb->queries, 'the enclosing transaction must stay open');
    }

    public function test_each_nesting_level_gets_its_own_savepoint(): void
    {
        DB::transaction(static function (): void {
            DB::transaction(static function (): void {
                DB::transaction(static fn () => null);
            });
        });

        $this->assertSame([
            'START TRANSACTION',
            'SAVEPOINT queryable_sp_1',
            'SAVEPOINT queryable_sp_2',
            'RELEASE SAVEPOINT queryable_sp_2',
            'RELEASE SAVEPOINT queryable_sp_1',
            'COMMIT',
        ], $this->wpdb->queries);
    }

    public function test_inner_throw_rolls_back_only_the_inner_level(): void
    {
        DB::transaction(function (): void {
            try {
                DB::transaction(static function (): void {
                    throw new RuntimeException('inner');
                });
            } catch (RuntimeException $e) {
                // Swallowed on purpose: the outer block carries on and commits.
            }
        });

        $this->assertSame([
            'START TRANSACTION',
            'SAVEPOINT queryable_sp_1',
            'ROLLBACK TO SAVEPOINT queryable_sp_1',
            'COMMIT',
        ], $this->wpdb->queries);
    }

    public function test_sibling_nested_transactions_reuse_the_level_name(): void
    {
        $this->setDepth(1);

        DB::transaction(static fn () => null);
        DB::transaction(static fn () => null);

        $this->assertSame([
            'SAVEPOINT queryable_sp_1',
            'RELEASE SAVEPOINT queryable_sp_1',
            'SAVEPOINT queryable_sp_1',
            'RELEASE SAVEPOINT queryable_sp_1',
        ], $this->wpdb->queries);
    }

    /**
     * A savepoint that was never created cannot roll anything back, so running
     * the callback anyway would silently reproduce the very hole this exists to
     * close. Nothing has run yet at that point, so failing here costs nothing.
     */
    public function test_a_failed_savepoint_throws_before_the_callback_runs(): void
    {
        $this->setDepth(1);
        $this->wpdb->failOn = ['SAVEPOINT'];
        $ran = false;

        try {
            DB::transaction(static function () use (&$ran): void {
                $ran = true;
            });
            $this->fail('expected a QueryException for the failed savepoint');
        } catch (QueryException $e) {
            $this->assertStringContainsString('simulated failure', $e->getMessage());
        }

        $this->assertFalse($ran, 'the callback must not run without a savepoint to undo it');
        $this->assertSame(['SAVEPOINT queryable_sp_1'], $this->wpdb->queries);
        $this->assertSame(1, $this->depth(), 'depth must be restored when the savepoint fails');
    }

    /**
     * The callback's exception is the caller's cause and control flow may be
     * keyed on its type, so a failing ROLLBACK TO SAVEPOINT must not replace it.
     */
    public function test_a_failed_rollback_does_not_mask_the_callback_exception(): void
    {
        $this->setDepth(1);
        $this->wpdb->failOn = ['ROLLBACK TO SAVEPOINT'];

        try {
            DB::transaction(static function (): void {
                throw new RuntimeException('boom');
            });
            $this->fail('expected the callback exception to propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
            $this->assertNotInstanceOf(QueryException::class, $e);
        }

        $this->assertSame(
            ['SAVEPOINT queryable_sp_1', 'ROLLBACK TO SAVEPOINT queryable_sp_1'],
            $this->wpdb->queries,
        );
    }

    public function test_depth_is_restored_after_a_nested_throw(): void
    {
        $this->setDepth(1);

        try {
            DB::transaction(static function (): void {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $e) {
            // asserted below
        }

        $this->assertSame(1, $this->depth());
    }
}
