<?php

namespace App\Enums;

/*
 * What a block of time was spent on. Mosaic kept office and time off on two
 * pseudo-projects ("Office", "Time Off") with work categories on top; here
 * they are categories, and only project time points at a job number.
 */
enum TimeCategory: string
{
    case Project = 'project';
    case Office = 'office';
    case Vacation = 'vacation';
    case Sick = 'sick';
    case BankedTime = 'banked time';
    case UnpaidTimeOff = 'unpaid time off';

    public function label(): string
    {
        return match ($this) {
            self::Project => __('Project'),
            self::Office => __('Office'),
            self::Vacation => __('Vacation'),
            self::Sick => __('Sick'),
            self::BankedTime => __('Banked time'),
            self::UnpaidTimeOff => __('Unpaid time off'),
        };
    }

    public function needsProject(): bool
    {
        return $this === self::Project;
    }

    public function isTimeOff(): bool
    {
        return ! in_array($this, [self::Project, self::Office], true);
    }
}
