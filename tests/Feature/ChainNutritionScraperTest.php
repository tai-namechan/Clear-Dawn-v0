<?php

namespace Tests\Feature;

use App\Services\ChainNutritionScraper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChainNutritionScraperTest extends TestCase
{
    private function scraper(): ChainNutritionScraper
    {
        return new ChainNutritionScraper;
    }

    public function test_returns_nutrition_from_fatsecret(): void
    {
        $detailHtml = <<<'HTML'
        <html><body>
        <h1>すき家 牛丼並盛</h1>
        <div>1食分（350g）</div>
        <div>カロリー: 733 kcal</div>
        <div>たんぱく質: 22.7 g</div>
        <div>脂質: 25.1 g</div>
        <div>炭水化物: 104.1 g</div>
        </body></html>
        HTML;

        Http::fake([
            'www.fatsecret.jp/*' => Http::sequence()
                ->push('<a href="https://www.fatsecret.jp/カロリー-栄養/すき家/牛丼並盛/1食" class="prominent">牛丼並盛</a>', 200)
                ->push($detailHtml, 200),
        ]);

        $result = $this->scraper()->search('すき家', '牛丼並盛');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(733.0, $result['kcal'], 0.01);
        $this->assertEqualsWithDelta(22.7, $result['protein_g'], 0.01);
        $this->assertEqualsWithDelta(25.1, $result['fat_g'], 0.01);
        $this->assertEqualsWithDelta(104.1, $result['carb_g'], 0.01);
        $this->assertSame('serving', $result['per']);
        $this->assertSame('すき家 牛丼並盛', $result['name']);
    }

    public function test_returns_null_on_search_404(): void
    {
        Http::fake([
            'www.fatsecret.jp/*' => Http::response('', 404),
        ]);

        $this->assertNull($this->scraper()->search('テスト', 'メニュー'));
    }

    public function test_returns_null_when_no_detail_link_found(): void
    {
        Http::fake([
            'www.fatsecret.jp/*' => Http::response('<html><body>結果なし</body></html>', 200),
        ]);

        $this->assertNull($this->scraper()->search('テスト', 'メニュー'));
    }

    public function test_returns_null_when_kcal_missing_from_detail(): void
    {
        Http::fake([
            'www.fatsecret.jp/*' => Http::sequence()
                ->push('<a href="https://www.fatsecret.jp/カロリー-栄養/brand/item/1" class="prominent">item</a>', 200)
                ->push('<html><body><h1>Some Item</h1><div>No nutrition info here</div></body></html>', 200),
        ]);

        $this->assertNull($this->scraper()->search('テスト', 'メニュー'));
    }

    public function test_returns_kcal_even_if_pfc_missing(): void
    {
        Http::fake([
            'www.fatsecret.jp/*' => Http::sequence()
                ->push('<a href="https://www.fatsecret.jp/カロリー-栄養/brand/item/1" class="prominent">item</a>', 200)
                ->push('<html><body><h1>Some Item</h1><div>カロリー: 500 kcal</div></body></html>', 200),
        ]);

        $result = $this->scraper()->search('テスト', 'メニュー');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(500.0, $result['kcal'], 0.01);
        $this->assertNull($result['protein_g']);
        $this->assertNull($result['fat_g']);
        $this->assertNull($result['carb_g']);
    }

    public function test_returns_null_on_connection_failure(): void
    {
        Http::fake([
            'www.fatsecret.jp/*' => fn () => throw new ConnectionException('timeout'),
        ]);

        $this->assertNull($this->scraper()->search('テスト', 'メニュー'));
    }

    public function test_extracts_serving_label(): void
    {
        Http::fake([
            'www.fatsecret.jp/*' => Http::sequence()
                ->push('<a href="https://www.fatsecret.jp/カロリー-栄養/brand/item/1" class="prominent">item</a>', 200)
                ->push('<html><body><h1>テスト品</h1><div>1食分（280g）</div><div>カロリー: 600 kcal</div></body></html>', 200),
        ]);

        $result = $this->scraper()->search('テスト', 'メニュー');

        $this->assertNotNull($result);
        $this->assertSame('280g', $result['serving_label']);
    }
}
