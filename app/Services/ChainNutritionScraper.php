<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ChainNutritionScraper
{
    private const HTTP_TIMEOUT = 5;

    private const CONNECT_TIMEOUT = 3;

    /**
     * @return array{name: string, serving_label: string, per: string, kcal: float, protein_g: float|null, fat_g: float|null, carb_g: float|null}|null
     */
    public function search(string $storeName, string $menuName): ?array
    {
        try {
            return $this->tryFatsecret($storeName, $menuName);
        } catch (Throwable $e) {
            Log::info('ChainNutritionScraper failed, falling back to AI.', [
                'store_name' => $storeName,
                'menu_name' => $menuName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{name: string, serving_label: string, per: string, kcal: float, protein_g: float|null, fat_g: float|null, carb_g: float|null}|null
     */
    private function tryFatsecret(string $storeName, string $menuName): ?array
    {
        $query = $storeName.' '.$menuName;
        $searchUrl = 'https://www.fatsecret.jp/カロリー-栄養/search?q='.urlencode($query);

        $searchResponse = Http::timeout(self::HTTP_TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->withHeaders(['Accept-Language' => 'ja'])
            ->get($searchUrl);

        if ($searchResponse->failed()) {
            return null;
        }

        $detailUrl = $this->extractFirstDetailUrl($searchResponse->body());
        if ($detailUrl === null) {
            return null;
        }

        $detailResponse = Http::timeout(self::HTTP_TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->withHeaders(['Accept-Language' => 'ja'])
            ->get($detailUrl);

        if ($detailResponse->failed()) {
            return null;
        }

        return $this->parseNutritionFromHtml($detailResponse->body(), $menuName);
    }

    private function extractFirstDetailUrl(string $html): ?string
    {
        if (preg_match(
            '#href="(https://www\.fatsecret\.jp/カロリー-栄養/[^"]+)"[^>]*class="prominent"#u',
            $html,
            $m
        )) {
            return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        if (preg_match(
            '#<a[^>]+href="(https://www\.fatsecret\.jp/カロリー-栄養/[^"]*)"[^>]*>#u',
            $html,
            $m
        )) {
            return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        return null;
    }

    /**
     * @return array{name: string, serving_label: string, per: string, kcal: float, protein_g: float|null, fat_g: float|null, carb_g: float|null}|null
     */
    private function parseNutritionFromHtml(string $html, string $fallbackName): ?array
    {
        $plainText = strip_tags($html);
        $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');

        $kcal = $this->extractNumericValue($plainText, [
            '/カロリー[：:\s]*(\d+(?:\.\d+)?)\s*kcal/u',
            '/(\d+(?:\.\d+)?)\s*kcal/u',
        ]);

        if ($kcal === null || $kcal <= 0) {
            return null;
        }

        $protein = $this->extractNumericValue($plainText, [
            '/たんぱく質[：:\s]*(\d+(?:\.\d+)?)\s*g/u',
            '/タンパク質[：:\s]*(\d+(?:\.\d+)?)\s*g/u',
            '/protein[：:\s]*(\d+(?:\.\d+)?)\s*g/iu',
        ]);

        $fat = $this->extractNumericValue($plainText, [
            '/脂質[：:\s]*(\d+(?:\.\d+)?)\s*g/u',
            '/脂肪[：:\s]*(\d+(?:\.\d+)?)\s*g/u',
            '/fat[：:\s]*(\d+(?:\.\d+)?)\s*g/iu',
        ]);

        $carb = $this->extractNumericValue($plainText, [
            '/炭水化物[：:\s]*(\d+(?:\.\d+)?)\s*g/u',
            '/糖質[：:\s]*(\d+(?:\.\d+)?)\s*g/u',
            '/carb(?:ohydrate)?s?[：:\s]*(\d+(?:\.\d+)?)\s*g/iu',
        ]);

        $name = $this->extractPageTitle($html) ?? $fallbackName;
        $servingLabel = $this->extractServingLabel($plainText) ?? '1人前';

        return [
            'name' => mb_substr($name, 0, 100),
            'serving_label' => mb_substr($servingLabel, 0, 50),
            'per' => 'serving',
            'kcal' => round($kcal, 2),
            'protein_g' => $protein !== null ? round($protein, 2) : null,
            'fat_g' => $fat !== null ? round($fat, 2) : null,
            'carb_g' => $carb !== null ? round($carb, 2) : null,
        ];
    }

    /**
     * @param  list<string>  $patterns
     */
    private function extractNumericValue(string $text, array $patterns): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $value = (float) $m[1];

                return $value >= 0 ? $value : null;
            }
        }

        return null;
    }

    private function extractPageTitle(string $html): ?string
    {
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/u', $html, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));

            return $title !== '' ? $title : null;
        }

        return null;
    }

    private function extractServingLabel(string $text): ?string
    {
        if (preg_match('/1食分[（(]([^）)]+)[）)]/u', $text, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/サービングサイズ[：:\s]*([^\n]+)/u', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
