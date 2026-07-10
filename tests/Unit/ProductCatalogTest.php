<?php

namespace Tests\Unit;

use App\Support\ProductCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function test_resolve_from_path(string $path, string $expected): void
    {
        $this->assertSame($expected, ProductCatalog::resolveFromPath($path));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pathProvider(): array
    {
        return [
            'dashboard' => ['dashboard', ProductCatalog::CLEAR_DAWN],
            'life areas' => ['life-areas', ProductCatalog::CLEAR_DAWN],
            'yoyu home' => ['yoyu', ProductCatalog::YOYU],
            'yoyu nested' => ['yoyu/tasks', ProductCatalog::YOYU],
            'kioku home' => ['kioku', ProductCatalog::KIOKU],
            'kioku nested' => ['kioku/memories', ProductCatalog::KIOKU],
        ];
    }
}
