<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Software;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/*
 * Rates to CAD. A rate typed on the Software page wins; otherwise today's
 * published rate; otherwise none at all. A total built on a made-up rate is
 * worse than three honest per-currency totals.
 */
class ExchangeRates
{
    protected const FeedUrl = 'https://open.er-api.com/v6/latest/CAD';

    /** @return array<string, array{rate: float, source: string}> */
    public function toCad(): array
    {
        $rates = ['CAD' => ['rate' => 1.0, 'source' => 'base']];

        foreach ($this->published()['rates'] ?? [] as $currency => $rate) {
            $rates[$currency] = ['rate' => $rate, 'source' => 'live'];
        }

        foreach ($this->pinned() as $currency => $rate) {
            $rates[$currency] = ['rate' => $rate, 'source' => 'manual'];
        }

        return $rates;
    }

    /** @return array<string, float> */
    public function pinned(): array
    {
        return AppSetting::where('key', 'like', 'fx.%')
            ->get()
            ->filter(fn (AppSetting $setting) => (float) $setting->value > 0)
            ->mapWithKeys(fn (AppSetting $setting) => [substr($setting->key, 3) => (float) $setting->value])
            ->all();
    }

    /** @return array{rates?: array<string, float>, asOf?: string, error?: string} */
    public function published(): array
    {
        if ($cached = Cache::get('fx.published')) {
            return $cached;
        }

        try {
            $response = Http::timeout(5)->get(self::FeedUrl)->throw()->json();
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        $published = [
            'rates' => collect((array) ($response['rates'] ?? []))
                ->only(Software::Currencies)
                ->except('CAD')
                ->filter(fn (mixed $rate) => (float) $rate > 0)
                ->map(fn (mixed $rate) => round(1 / (float) $rate, 6))
                ->all(),
            'asOf' => $response['time_last_update_utc'] ?? null,
        ];

        Cache::put('fx.published', $published, now()->addDay());

        return $published;
    }
}
