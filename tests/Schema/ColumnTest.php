<?php

declare(strict_types=1);

namespace Queryable\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Queryable\Schema\Column;

final class ColumnTest extends TestCase
{
    public function test_charset_and_collate_appear_in_the_definition(): void
    {
        $col = (new Column('uuid', 'CHAR(36)'))->charset('ascii', 'ascii_bin');

        $def = $col->getDefinition();

        self::assertSame('ascii', $def['charset']);
        self::assertSame('ascii_bin', $def['collate']);
    }

    public function test_a_column_without_charset_reports_null(): void
    {
        $def = (new Column('title', 'VARCHAR(255)'))->getDefinition();

        self::assertNull($def['charset']);
        self::assertNull($def['collate']);
    }
}
