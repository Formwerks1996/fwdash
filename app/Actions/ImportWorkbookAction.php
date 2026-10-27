<?php

namespace App\Actions;

use App\Actions\Billing\RefreshProjectFinancialsAction;
use App\Billing\BillingEngine;
use App\Imports\DashboardWorkbookImporter;
use App\Imports\WorkbookReconciler;
use App\Models\User;
use App\Models\WorkbookImport;
use App\Support\AuditLogger;

class ImportWorkbookAction
{
    public function __construct(
        protected AuditLogger $audit,
        protected BillingEngine $engine,
        protected RefreshProjectFinancialsAction $refreshFinancials,
    ) {}

    public function execute(string $path, string $filename, ?User $user = null): WorkbookImport
    {
        $result = (new DashboardWorkbookImporter($path))->import();

        $this->engine->load();
        $this->refreshFinancials->execute();

        $reconciliation = (new WorkbookReconciler($path, $this->engine))->reconcile();

        $import = WorkbookImport::create([
            'user_id' => $user?->id,
            'filename' => $filename,
            'counts' => $result->counts,
            'warnings' => $result->warnings,
            'reconciliation' => $reconciliation,
        ]);

        $this->audit->log('import', 'workbook_imports', $import->id, after: [
            'filename' => $filename,
            'counts' => $result->counts,
            'warnings' => count($result->warnings),
            'mismatches' => count($reconciliation['mismatches']),
        ], user: $user);

        return $import;
    }
}
