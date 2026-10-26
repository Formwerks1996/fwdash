<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property ?string $cid
 * @property ?string $user_name
 * @property ?string $model
 * @property ?string $serial
 * @property ?string $override
 * @property ?int $replace_year
 * @property ?string $sophos
 * @property ?CarbonImmutable $purchase_date
 * @property ?string $purchase_price
 * @property bool $retired
 */
#[Fillable([
    'cid', 'user_name', 'name_future', 'dept', 'replace_year', 'override', 'upgrade', 'sophos', 'location', 'ip_address',
    'purchase_date', 'purchase_price', 'serial', 'model', 'screen_size', 'os_version', 'cpu', 'cpu_speed', 'cpu_cores',
    'memory', 'mem_type', 'mem_banks', 'graphics', 'hd1', 'hd2', 'notes', 'retired',
])]
class Hardware extends Model
{
    /** @var array<string, array{0: string, 1: string}> kind => [label, icon] */
    public const Kinds = [
        'laptop' => ['MacBook Pro', 'ph-laptop'],
        'imac' => ['iMac', 'ph-desktop'],
        'studio' => ['Mac Studio', 'ph-hard-drives'],
        'pc' => ['PC', 'ph-desktop-tower'],
        'other' => ['Other', 'ph-cpu'],
    ];

    protected $table = 'hardware';

    protected function casts(): array
    {
        return [
            'retired' => 'boolean',
            'replace_year' => 'integer',
            'cpu_cores' => 'integer',
            'purchase_date' => 'date:Y-m-d',
        ];
    }

    public function kind(): string
    {
        $text = strtolower("{$this->model} {$this->cid}");

        return match (true) {
            str_contains($text, 'macbook') || str_contains($text, 'fwmb') => 'laptop',
            str_contains($text, 'imac') || str_contains($text, 'fwim') => 'imac',
            str_contains($text, 'studio') || str_contains($text, 'fwsm') => 'studio',
            (bool) preg_match('/\bpc\b/', $text) || str_contains($text, 'fwpc') => 'pc',
            default => 'other',
        };
    }

    public function isSpare(): bool
    {
        return (bool) preg_match('/spare/i', (string) $this->user_name);
    }

    /** A year in Override wins over the planned replacement year. */
    public function dueYear(): ?int
    {
        if ($this->override && preg_match('/\b(20\d\d)\b/', $this->override, $match)) {
            return (int) $match[1];
        }

        return $this->replace_year;
    }

    /** @return array{key: string, label: string} */
    public function dueStatus(): array
    {
        $year = $this->dueYear();
        $thisYear = (int) now()->format('Y');

        return match (true) {
            $year === null => ['key' => 'none', 'label' => __('No year')],
            $year < $thisYear => ['key' => 'overdue', 'label' => __('Overdue · :year', ['year' => $year])],
            $year === $thisYear => ['key' => 'now', 'label' => __('Due :year', ['year' => $year])],
            $year === $thisYear + 1 => ['key' => 'next', 'label' => __('Next year · :year', ['year' => $year])],
            default => ['key' => 'later', 'label' => (string) $year],
        };
    }

    public function ageInYears(): ?float
    {
        if (! $this->purchase_date) {
            return null;
        }

        return round(CarbonImmutable::parse($this->purchase_date)->diffInDays(now()) / 365.25, 1);
    }
}
