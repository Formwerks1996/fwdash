<?php

namespace App\Enums;

enum PursuitStage: string
{
    case Contacted = 'Contacted';
    case InDiscussion = 'In Discussion';
    case Won = 'Won';
    case Lost = 'Lost';

    public function isSettled(): bool
    {
        return in_array($this, [self::Won, self::Lost], true);
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Won => 2,
            self::Lost => 3,
            default => 1,
        };
    }

    /** @return array{0: string, 1: string} background and text colour */
    public function colors(): array
    {
        return match ($this) {
            self::Contacted => ['#eff1f2', '#44515e'],
            self::InDiscussion => ['#fdf3d8', '#8a6009'],
            self::Won => ['#dcefe2', '#1c6f50'],
            self::Lost => ['#fbe3dd', '#a83f24'],
        };
    }
}
