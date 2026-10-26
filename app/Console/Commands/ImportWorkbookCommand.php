<?php

namespace App\Console\Commands;

use App\Actions\ImportWorkbookAction;
use App\Imports\WorkbookImportException;
use Illuminate\Console\Command;

class ImportWorkbookCommand extends Command
{
    protected $signature = 'import-workbook {path : Path to Dashboard Data.xlsx}';

    protected $description = 'Replace the billing and office data with a Dashboard Data workbook';

    public function handle(ImportWorkbookAction $importWorkbook): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("Cannot read `{$path}`.");

            return self::FAILURE;
        }

        $this->info("Importing `{$path}`...");

        try {
            $import = $importWorkbook->execute($path, basename($path));
        } catch (WorkbookImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Table', 'Rows'], collect($import->counts)->map(fn (int $count, string $table) => [$table, $count]));

        foreach ($import->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($import->reconciliation['mismatches'] as $mismatch) {
            $this->warn($mismatch);
        }

        $this->comment('Imported with '.count($import->warnings).' warning(s).');
        $this->comment("Checked {$import->reconciliation['checked']} report figures against the workbook: ".count($import->reconciliation['mismatches']).' differ.');

        return self::SUCCESS;
    }
}
