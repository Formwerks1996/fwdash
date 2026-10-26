<?php

namespace App\Support;

/*
 * The single $/hr classifier, with the old "Report for Jim" thresholds
 * (95 / 130 / 165). Under 20 hours the rate is not meaningful, so it is
 * labelled rather than judged.
 */
class RateBand
{
    public const MinHours = 20;

    /** Upper bounds are exclusive. */
    public const Bands = [
        'low' => ['label' => 'Under $95', 'max' => 95, 'fill' => '#c25a3a', 'wash' => '#fbe7e3', 'text' => '#a83f24', 'border' => '#f2d3cc'],
        'mid' => ['label' => '$95 – $130', 'max' => 130, 'fill' => '#d3a02f', 'wash' => '#fbf1da', 'text' => '#8a6009', 'border' => '#f0e2c3'],
        'good' => ['label' => '$130 – $165', 'max' => 165, 'fill' => '#2f8a67', 'wash' => '#e4f1ea', 'text' => '#1c6f50', 'border' => '#cfe5da'],
        'high' => ['label' => 'Above $165', 'max' => INF, 'fill' => '#4b8fbb', 'wash' => '#e4eff7', 'text' => '#2a6489', 'border' => '#cfe1ee'],
    ];

    public const Unmeasured = ['label' => 'Not measurable', 'fill' => '#c3c7cc', 'wash' => '#f1f2f3', 'text' => '#5c6873', 'border' => '#e6e7e9'];

    /** @return array<string, mixed>|null */
    public static function classify(?float $rate, ?float $hours = null): ?array
    {
        if ($rate === null) {
            return null;
        }

        if ($hours !== null && $hours < static::MinHours) {
            return [
                ...static::Unmeasured,
                'key' => 'unreliable',
                'label' => 'Too few hours to judge',
                'reliable' => false,
                'reason' => number_format($hours, 1).' h logged — under '.static::MinHours.' h the rate is not meaningful',
            ];
        }

        foreach (static::Bands as $key => $band) {
            if ($rate < $band['max']) {
                return [...$band, 'key' => $key, 'reliable' => true, 'reason' => null];
            }
        }

        return null;
    }

    public static function bucket(?float $rate, ?float $hours = null): string
    {
        $band = static::classify($rate, $hours);

        return $band && $band['reliable'] ? $band['key'] : 'na';
    }
}
