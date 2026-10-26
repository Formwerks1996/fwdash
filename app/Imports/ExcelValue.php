<?php

namespace App\Imports;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

class ExcelValue
{
    /** A cell as a Y-m-d date: a date cell, an Excel serial number, or parseable text. */
    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
