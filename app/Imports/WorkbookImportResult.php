<?php

namespace App\Imports;

class WorkbookImportResult
{
    /**
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public array $counts,
        public array $warnings,
    ) {}
}
