<?php

namespace App\Http\Controllers;

use App\Actions\ImportWorkbookAction;
use App\Http\Requests\StoreWorkbookImportRequest;
use App\Imports\WorkbookImportException;
use App\Models\WorkbookImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/*
 * The upload is read and applied in the same request: on serverless hosting
 * nothing written to disk survives into the next one.
 */
class WorkbookImportsController
{
    public function create(): View
    {
        return view('imports.create', [
            'imports' => WorkbookImport::with('user')->latest()->limit(10)->get(),
        ]);
    }

    public function store(StoreWorkbookImportRequest $request, ImportWorkbookAction $importWorkbook): RedirectResponse
    {
        $file = $request->file('workbook');

        try {
            $import = $importWorkbook->execute($file->getRealPath(), $file->getClientOriginalName(), $request->user());
        } catch (WorkbookImportException $exception) {
            return back()->withErrors(['workbook' => $exception->getMessage()]);
        }

        return to_route('imports.create')->with('import', $import->id);
    }
}
