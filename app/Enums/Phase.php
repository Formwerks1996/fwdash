<?php

namespace App\Enums;

enum Phase: string
{
    case Fs = 'FS';
    case Sd = 'SD';
    case DdToRz = 'DD to RZ';
    case Rzp = 'RZP';
    case DdToDp = 'DD to DP';
    case Dpp = 'DPP';
    case Bp = 'BP';
    case Ift = 'IFT';
    case Ifc = 'IFC';
    case Ca = 'CA';
    case Misc = 'Misc.';

    /** The pseudo-phase the workbook uses for time-and-materials rows. */
    public const Hours = 'Hours';

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_map(fn (self $phase) => $phase->value, self::cases());
    }

    public function seq(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public static function seqOf(string $code): int
    {
        return self::tryFrom($code)?->seq() ?? 99;
    }
}
