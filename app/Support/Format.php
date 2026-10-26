<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/*
 * Number and date formatting shared by every page. An em dash is the glyph for
 * "no value"; it is never a zero.
 */
class Format
{
    public const Empty = '—';

    public static function money(mixed $value, int $places = 0): string
    {
        if ($value === null || $value === '') {
            return static::Empty;
        }

        $number = (float) $value;
        $formatted = '$'.number_format(abs($number), $places);

        return $number < 0 ? "−{$formatted}" : $formatted;
    }

    /** $1.23M, $14k, $950. */
    public static function compact(mixed $value): string
    {
        if ($value === null || $value === '') {
            return static::Empty;
        }

        $number = (float) $value;
        $absolute = abs($number);
        $sign = $number < 0 ? '−' : '';

        if ($absolute >= 1_000_000) {
            return $sign.'$'.number_format($absolute / 1_000_000, 2).'M';
        }

        if ($absolute >= 1_000) {
            return $sign.'$'.number_format(round($absolute / 1_000)).'k';
        }

        return $sign.'$'.number_format(round($absolute));
    }

    public static function pct(mixed $fraction, int $places = 0): string
    {
        if ($fraction === null || $fraction === '') {
            return static::Empty;
        }

        return number_format((float) $fraction * 100, $places).'%';
    }

    public static function number(mixed $value, int $places = 0): string
    {
        if ($value === null || $value === '') {
            return static::Empty;
        }

        return number_format((float) $value, $places);
    }

    public static function date(?string $date): string
    {
        return $date
            ? CarbonImmutable::parse($date)->format('M j, Y')
            : static::Empty;
    }

    public static function month(?string $period): string
    {
        return $period
            ? CarbonImmutable::parse($period)->format('F Y')
            : static::Empty;
    }

    public static function shortMonth(?string $period): string
    {
        return $period
            ? CarbonImmutable::parse($period)->format('M Y')
            : static::Empty;
    }
}
