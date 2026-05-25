<?php

namespace Queryable\Tests;

use PHPUnit\Framework\TestCase;
use Queryable\Schema\Table;

/**
 * The acceptance test for the dbDelta-canonical compiler: after a table is
 * created, a second dbDelta() of the SAME schema must be a no-op. If the
 * compiled column SQL can't round-trip against MySQL's reported schema, dbDelta
 * emits spurious `ALTER TABLE ... CHANGE COLUMN` on every call and never
 * converges. Reproduces a representative model (every finicky column type).
 */
class SchemaConvergenceTest extends TestCase
{
    private function representativeTable(): Table
    {
        $t = new Table();
        $t->id();
        $t->bigInteger('member_id')->unsigned();
        $t->bigInteger('group_id')->unsigned()->nullable()->index();
        $t->integer('donations_count')->unsigned()->default(0);
        $t->boolean('is_active')->default(1);
        $t->string('slug', 200)->unique();
        $t->string('display_name', 200);
        $t->decimal('amount', 10, 2)->default(0);
        $t->text('note')->nullable();
        $t->longText('story')->nullable();
        $t->json('meta')->nullable();
        $t->datetime('created_at');
        $t->datetime('updated_at')->nullable();
        $t->index(['is_active', 'created_at']);
        $t->unique(['member_id', 'group_id']);

        return $t;
    }

    public function test_second_dbdelta_is_a_noop(): void
    {
        global $wpdb;
        if (! isset($wpdb)) {
            $this->markTestSkipped('Needs the WP integration env ($wpdb).');
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'queryable_convergence';
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");

        $charset = $wpdb->get_charset_collate();
        $sql     = $this->representativeTable()->compile($table);
        // Table::compile() sets its own charset/collate; let WP's take over so the
        // test matches how Model::migrate() runs in core (it appends nothing).

        dbDelta($sql);                       // create
        $second = dbDelta($sql, false);      // would-be changes on a converged schema

        $version = $wpdb->get_var('SELECT VERSION()');
        $this->assertSame(
            [],
            $second,
            "MySQL {$version}: dbDelta wants to change a just-created table (non-canonical compile):\n"
            . var_export($second, true) . "\n\nSQL:\n" . $sql
        );

        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }
}
