<?php

namespace App\Support;

/*
 * One key per firm however it is typed: lower-case, letters and digits only.
 * Unicode-aware, so accented names fold the same way everywhere.
 */
class DeveloperKey
{
    public static function make(?string $name): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower((string) $name));
    }
}
