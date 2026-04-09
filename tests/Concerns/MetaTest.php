<?php

namespace Queryable\Tests\Concerns;

use Queryable\DB;

class MetaTest extends QueryBuilderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::init([
            'schema' => [
                'posts' => [
                    'meta' => [
                        'table' => 'postmeta',
                        'foreignKey' => 'post_id',
                        'primaryKey' => 'ID',
                        'aliases' => [
                            'price' => '_wp_product_price',
                            'color' => '_product_color',
                            'tags' => ['key' => '_product_tags', 'multiple' => true],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testWithMetaSingleKey(): void
    {
        $this->assertEquals(
            "SELECT ID, post_title, meta_price.meta_value AS price FROM posts LEFT JOIN postmeta AS meta_price ON posts.ID = meta_price.post_id AND meta_price.meta_key = '_wp_product_price'",
            DB::table('posts')->select('ID', 'post_title')->withMeta('price')->toSQL(),
        );
    }

    public function testWithMetaMultipleKeys(): void
    {
        $this->assertEquals(
            "SELECT ID, post_title, meta_price.meta_value AS price, meta_color.meta_value AS color FROM posts LEFT JOIN postmeta AS meta_price ON posts.ID = meta_price.post_id AND meta_price.meta_key = '_wp_product_price' LEFT JOIN postmeta AS meta_color ON posts.ID = meta_color.post_id AND meta_color.meta_key = '_product_color'",
            DB::table('posts')->select('ID', 'post_title')->withMeta('price', 'color')->toSQL(),
        );
    }

    public function testWithMetaMultipleUsesGroupConcat(): void
    {
        $this->assertEquals(
            "SELECT ID, CONCAT('[',GROUP_CONCAT(DISTINCT CONCAT('\"',meta_tags.meta_value,'\"')),']') AS tags FROM posts LEFT JOIN postmeta AS meta_tags ON posts.ID = meta_tags.post_id AND meta_tags.meta_key = '_product_tags'",
            DB::table('posts')->select('ID')->withMeta('tags')->toSQL(),
        );
    }

    public function testWithMetaSingleDoesNotUseGroupConcat(): void
    {
        $this->assertEquals(
            "SELECT ID, meta_price.meta_value AS price FROM posts LEFT JOIN postmeta AS meta_price ON posts.ID = meta_price.post_id AND meta_price.meta_key = '_wp_product_price'",
            DB::table('posts')->select('ID')->withMeta('price')->toSQL(),
        );
    }

    public function testOrderByMeta(): void
    {
        $this->assertEquals(
            "SELECT ID, meta_price.meta_value AS price FROM posts LEFT JOIN postmeta AS meta_price ON posts.ID = meta_price.post_id AND meta_price.meta_key = '_wp_product_price' ORDER BY meta_price.meta_value DESC",
            DB::table('posts')->select('ID')->withMeta('price')->orderBy('price', 'DESC')->toSQL(),
        );
    }

    public function testWhereByMeta(): void
    {
        $this->assertEquals(
            "SELECT ID, meta_price.meta_value AS price FROM posts LEFT JOIN postmeta AS meta_price ON posts.ID = meta_price.post_id AND meta_price.meta_key = '_wp_product_price' WHERE meta_price.meta_value > 100",
            DB::table('posts')->select('ID')->withMeta('price')->where('price', 100, '>')->toSQL(),
        );
    }

    public function testGroupByMeta(): void
    {
        $this->assertEquals(
            "SELECT ID, meta_color.meta_value AS color FROM posts LEFT JOIN postmeta AS meta_color ON posts.ID = meta_color.post_id AND meta_color.meta_key = '_product_color' GROUP BY meta_color.meta_value",
            DB::table('posts')->select('ID')->withMeta('color')->groupBy('color')->toSQL(),
        );
    }

    public function testWithMetaThrowsWithoutConfig(): void
    {
        DB::reset();
        $this->expectException(\RuntimeException::class);
        DB::table('users')->withMeta('price');
    }
}
