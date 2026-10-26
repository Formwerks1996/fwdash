<?php

namespace App\Support;

use RoundingMode;

/*
 * Exact decimal arithmetic for billing figures.
 *
 * The workbook rounds the way Excel does: half away from zero at 2 dp, at every
 * step, summing the rounded values. Floats cannot reproduce that (24189.375
 * rounds to .37 as a float and .38 in Excel), so every figure the engine
 * produces goes through bcmath strings.
 */
class Money
{
    protected const Scale = 10;

    /** @return numeric-string */
    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            $shortest = (string) json_encode($value);

            $value = str_contains($shortest, 'E') || str_contains($shortest, 'e')
                ? rtrim(rtrim(sprintf('%.12F', $value), '0'), '.')
                : $shortest;
        }

        $value = trim((string) $value);

        return is_numeric($value)
            ? $value
            : '0';
    }

    /** @return numeric-string */
    public static function round(mixed $value, int $places = 2): string
    {
        return bcround(static::normalize($value), $places, RoundingMode::HalfAwayFromZero);
    }

    /** @return numeric-string */
    public static function add(mixed ...$values): string
    {
        return array_reduce(
            $values,
            fn (string $carry, mixed $value) => bcadd($carry, static::normalize($value), static::Scale),
            '0',
        );
    }

    /**
     * @param  iterable<mixed>  $values
     * @return numeric-string
     */
    public static function sum(iterable $values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, static::normalize($value), static::Scale);
        }

        return $total;
    }

    /** @return numeric-string */
    public static function sub(mixed $left, mixed $right): string
    {
        return bcsub(static::normalize($left), static::normalize($right), static::Scale);
    }

    /** @return numeric-string */
    public static function mul(mixed $left, mixed $right): string
    {
        return bcmul(static::normalize($left), static::normalize($right), static::Scale);
    }

    /** @return numeric-string|null */
    public static function div(mixed $left, mixed $right): ?string
    {
        if (static::isZero($right)) {
            return null;
        }

        return bcdiv(static::normalize($left), static::normalize($right), static::Scale);
    }

    /** @return numeric-string */
    public static function maxZero(mixed $value): string
    {
        return bccomp(static::normalize($value), '0', static::Scale) > 0
            ? static::normalize($value)
            : '0';
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return bccomp(static::normalize($left), static::normalize($right), static::Scale);
    }

    public static function isZero(mixed $value): bool
    {
        return bccomp(static::normalize($value), '0', static::Scale) === 0;
    }

    public static function toFloat(mixed $value): float
    {
        return (float) static::normalize($value);
    }
}
