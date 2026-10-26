<?php

namespace App\Http\Controllers;

use App\Billing\BillingEngine;
use App\Billing\PeriodReports;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/*
 * The printed reports. Each is a standalone page with its own print CSS and
 * server-drawn SVG charts, so the browser's "Save as PDF" makes the file: the
 * serverless host has no headless Chrome to do it.
 */
class BillingReportsController
{
    public function monthEnd(Request $request, string $period, BillingEngine $engine, PeriodReports $reports): View
    {
        abort_unless(in_array($period, $engine->periods(), true), 404);

        $only = array_key_exists((string) $request->query('only'), PeriodReports::Filters) ? (string) $request->query('only') : 'billing';
        $sort = (string) $request->query('sort', 'rateLive');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $figures = $reports->monthEnd($period, $only);

        if ($request->query('variant') === 'classic') {
            $rows = $figures['rows']
                ->sortBy(fn (array $row) => [mb_strtolower((string) $row['developer']), mb_strtolower((string) $row['jobName'])])
                ->values();

            return view('reports.month-end-classic', [
                'period' => $period,
                'rows' => $rows,
                'title' => 'MF Monthly Contract Status Report - '.Format::month($period),
            ]);
        }

        $columns = array_keys($figures['rows']->first() ?? []);

        return view('reports.month-end', [
            'period' => $period,
            'only' => $only,
            'figures' => $figures,
            'rows' => in_array($sort, $columns, true) ? PeriodReports::sortRows($figures['rows'], $sort, $direction) : $figures['rows'],
            'closed' => $engine->periodStatus($period) === 'closed',
            'title' => 'MF Monthly Contract Status - '.Format::month($period),
        ]);
    }

    public function billing(Request $request, string $period, BillingEngine $engine): View|Response
    {
        abort_unless(in_array($period, $engine->periods(), true), 404);

        $title = 'Billing Report — '.Format::month($period);

        $view = view('reports.billing', [
            'report' => $engine->periodReport($period),
            'title' => $title,
            'download' => $request->boolean('download'),
        ]);

        if (! $request->boolean('download')) {
            return $view;
        }

        return response($view->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.str_replace('—', '-', $title).'.html"; filename*=UTF-8\'\''.rawurlencode("{$title}.html"),
            'Cache-Control' => 'no-store',
        ]);
    }
}
