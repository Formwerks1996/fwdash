@php
    use App\Billing\PeriodReports;
    use App\Support\Format;
    use App\Support\RateBand;

    $f = $figures;
    $monthName = Format::month($period);
    $blank = fn ($value, callable $format) => $value ? $format($value) : '';
    $hoursLive = $rows->sum('hoursLive');
    $blended = $hoursLive ? ($rows->sum('billed') - $rows->sum('consultantTotal')) / $hoursLive : null;
    $band = RateBand::classify($f['realized'], $f['hoursListed']);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @fonts
    @vite(['resources/css/app.css'])
    <style>
        @page { size: letter portrait; margin: 10mm 9mm; }
        body { background: #f7f8f9; }
        .me-report { max-width: 8.5in; margin: 0 auto; padding: .5in .45in; background: #fff; font-size: 9.5pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .me-report .card { box-shadow: none; border: 1px solid #e3e5e8; padding: .6rem .7rem; margin-bottom: .6rem; break-inside: avoid; }
        .me-report .kpis { margin-bottom: .6rem; break-inside: avoid; }
        .me-report .kpi { padding: .5rem .6rem; }
        .me-report .kpi-value { font-size: 15pt; }
        .me-report .me-legend li { font-size: 7pt; }
        .me-report .me-mix-body { flex-direction: column; align-items: center; gap: .5rem; }
        .me-report .me-legend { width: 100%; min-width: 0; }
        .me-report .me-ring-mid b { font-size: 9pt; }
        .me-report .me-cash .mc-cols-head { grid-template-columns: repeat(3, 1fr); font-size: 5.6pt; }
        .me-report .me-cash .mc-cols-head > div { display: grid; grid-template-columns: 1fr auto 6.2em; }
        .me-report .me-cash .mc-all { columns: 3; column-gap: 1.1rem; font-size: 6.8pt; line-height: 1.3; }
        .me-report .me-cash .mc-all li { grid-template-columns: 1fr auto 6.2em; padding: .04rem 0; }
        .me-report .me-cash .mc-bar { height: 8px; }
        .me-report .me-cash .mc-key { font-size: 7pt; }
        .me-report .me-rate-legend .rate-chip { font-size: 6.5pt; }
        .me-pdf-table { break-before: page; }
        .me-pdf-table table { width: 100%; border-collapse: collapse; font-size: 6.6pt; line-height: 1.2; }
        .me-pdf-table th { text-align: left; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; font-size: 5.6pt; color: #5b616a; padding: 2px 3px; border-bottom: 1px solid #1b1f24; vertical-align: bottom; }
        .me-pdf-table td { padding: 1.2px 3px; border-bottom: 1px solid #eceef0; white-space: nowrap; vertical-align: middle; }
        .me-pdf-table tbody tr:nth-child(even) td { background: #f7f8f9; }
        .me-pdf-table .is-num { text-align: right; font-variant-numeric: tabular-nums; }
        .me-pdf-table .mp-job { max-width: 2.05in; overflow: hidden; text-overflow: ellipsis; }
        .me-pdf-table .mp-job b { font-weight: 500; }
        .me-pdf-table .mp-no { color: #9aa0a8; font-size: 5.6pt; }
        .me-pdf-table .mp-dev { max-width: 1.05in; overflow: hidden; text-overflow: ellipsis; color: #4b5058; }
        .me-pdf-table .mp-over { color: #b8532f; font-weight: 500; }
        .me-pdf-table .rate-chip { font-size: 6.2pt; padding: 0 3px; }
        .me-pdf-table tfoot td { font-weight: 500; border-top: 1px solid #1b1f24; border-bottom: 0; padding-top: 2px; }
        .me-pdf-table thead { display: table-header-group; }
        .me-pdf-table tfoot { display: table-row-group; }
        .me-pdf-table tr { break-inside: avoid; }
        .me-provenance { break-inside: avoid; font-size: 8pt; margin-top: .6rem; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .me-report { max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print mx-auto flex max-w-[8.5in] items-center justify-between gap-3 py-4">
        <span class="text-xs text-muted">{{ __('Print to letter, portrait. In the print dialog choose "Save as PDF" to keep a file.') }}</span>
        <button type="button" class="btn" onclick="window.print()"><i class="ph ph-printer"></i>{{ __('Print / Save as PDF') }}</button>
    </div>

    <div class="me-report">
        <header class="mb-3 flex items-end justify-between gap-4">
            <div>
                <h1 class="font-heading text-[18pt] font-medium text-navy">{{ __('Month end') }}</h1>
                <p class="text-[9pt] text-muted">{{ $monthName }}@if($closed) · {{ __('closed') }}@endif</p>
            </div>
            <p class="text-[8.5pt] text-[#5b616a]">{{ __(PeriodReports::Filters[$only]['print']) }} · {{ __('printed :date', ['date' => now()->format('j F Y')]) }}</p>
        </header>

        <div class="kpis grid-cols-3">
            <x-kpi :label="__('Revenue')" :value="Format::compact($f['total'])">
                {{ __('invoiced in :month', ['month' => $monthName]) }}
                @if($f['change'] !== null)
                    · <span @class(['font-medium', 'text-[#20825c]' => $f['change'] >= 0, 'text-[#9d6714]' => $f['change'] < 0])>{{ $f['change'] >= 0 ? '+' : '−' }}{{ number_format(abs($f['change']) * 100) }}%</span> {{ __('on last month') }}
                @endif
                @if($f['ytd']) · {{ Format::compact($f['ytd']) }} {{ __('year to date') }} @endif
            </x-kpi>
            <x-kpi :label="__('Realized rate')" :value="$f['realized'] ? '$'.number_format($f['realized']).'<small>/hr</small>' : '—'" :band="$band && $band['reliable'] ? $band : null">
                {{ $band ? $band['label'] : __('no hours to divide by') }}
            </x-kpi>
            <x-kpi :label="__('Under $95/hr')" :value="(string) $f['under']['count']" :band="$f['under']['count'] ? RateBand::Bands['low'] : null">
                {{ $f['under']['count'] ? $f['under']['names'] : __('nothing below the line') }}
            </x-kpi>
        </div>

        <section class="card">
            <div class="mb-1 flex items-baseline justify-between gap-3">
                <h2 class="section-title">{{ __('Revenue by month') }} <small class="font-sans text-xs font-normal text-muted">{{ $f['year'] }}</small></h2>
                <div class="flex gap-4 text-[7pt] text-muted">
                    <span class="flex items-center gap-1.5"><span class="inline-block w-4 border-t-[2.5px] border-navy"></span>{{ __('Billed') }}</span>
                    @if($f['estimated'])
                        <span class="flex items-center gap-1.5"><span class="inline-block w-4 border-t-[2.5px] border-dashed border-[#a9bccb]"></span>{{ __('Estimated') }}</span>
                    @endif
                    <span class="flex items-center gap-1.5"><span class="inline-block w-4 border-t-[1.5px] border-dashed border-muted"></span>{{ $f['year'] - 1 }}</span>
                </div>
            </div>
            @include('reports.partials.revenue-svg', ['chart' => $f['chart']])
        </section>

        <section class="card">
            <div class="grid grid-cols-2 gap-6">
                @foreach($f['rings'] as $ring)
                    @include('reports.partials.ring', ['ring' => $ring, 'monthName' => $monthName, 'size' => 88])
                @endforeach
            </div>
        </section>

        @include('reports.partials.outstanding', ['outstanding' => $f['outstanding'], 'period' => $period])

        @include('reports.partials.rate-legend')

        <div class="me-pdf-table">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Job') }}</th>
                        <th>{{ __('Developer') }}</th>
                        <th class="is-num">{{ __('Hrs (mo)') }}</th>
                        <th class="is-num">{{ __('Billed (mo)') }}</th>
                        <th class="is-num">{{ __('$/hr') }}</th>
                        <th class="is-num">{{ __('Contract') }}</th>
                        <th class="is-num">{{ __('Billed') }}</th>
                        <th class="is-num">{{ __('%') }}</th>
                        <th class="is-num">{{ __('Balance') }}</th>
                        <th class="is-num">{{ __('Extras') }}</th>
                        <th class="is-num">{{ __('Consult.') }}</th>
                        <th class="is-num">{{ __('Hrs total') }}</th>
                        <th class="is-num">{{ __('Paid') }}</th>
                        <th class="is-num">{{ __('Last paid') }}</th>
                        <th class="is-num">{{ __('Outstanding') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="mp-job"><b>{{ $row['jobName'] }}</b> <span class="mp-no">{{ $row['jobNumber'] }}</span></td>
                            <td class="mp-dev">{{ $row['developer'] }}</td>
                            <td class="is-num">{{ $blank($row['hoursMonth'], fn ($v) => Format::number($v)) }}</td>
                            <td class="is-num">{{ $blank($row['billedMonth'], fn ($v) => Format::compact($v)) }}</td>
                            <td class="is-num"><x-rate-chip :rate="$row['rateLive']" :hours="$row['hoursLive']" /></td>
                            <td class="is-num">{{ $blank($row['contract'], fn ($v) => Format::compact($v)) }}</td>
                            <td class="is-num">{{ $blank($row['billed'], fn ($v) => Format::compact($v)) }}</td>
                            <td @class(['is-num', 'mp-over' => $row['billedPct'] !== null && $row['billedPct'] > 1])>{{ $row['billedPct'] !== null ? Format::pct($row['billedPct']) : '' }}</td>
                            <td class="is-num">{{ $blank($row['balance'], fn ($v) => Format::compact($v)) }}</td>
                            <td class="is-num">{{ $blank($row['hourlyBilled'], fn ($v) => Format::compact($v)) }}</td>
                            <td class="is-num">{{ $blank($row['consultantTotal'], fn ($v) => Format::compact($v)) }}</td>
                            <td class="is-num">{{ $blank($row['hoursTotal'], fn ($v) => Format::number($v)) }}</td>
                            <td class="is-num">{{ $blank($row['paymentsTotal'], fn ($v) => Format::compact($v)) }}</td>
                            <td class="is-num">{{ $row['lastPaymentDate'] ? str_replace('-', '/', $row['lastPaymentDate']) : '' }}</td>
                            <td class="is-num"><span class="{{ PeriodReports::ageClass($row['daysSincePayment']) }}">{{ $blank($row['outstanding'], fn ($v) => Format::compact($v)) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="15">{{ __('Nothing in this group.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td><b>{{ trans_choice(':count job|:count jobs', $rows->count(), ['count' => $rows->count()]) }}</b></td>
                        <td></td>
                        <td class="is-num">{{ Format::number($rows->sum('hoursMonth')) }}</td>
                        <td class="is-num">{{ Format::compact($rows->sum('billedMonth')) }}</td>
                        <td class="is-num">{{ $blended === null ? '' : '$'.number_format($blended) }}</td>
                        <td class="is-num">{{ Format::compact($rows->sum('contract')) }}</td>
                        <td class="is-num">{{ Format::compact($rows->sum('billed')) }}</td>
                        <td class="is-num">{{ $rows->sum('contract') ? Format::pct($rows->sum('billed') / $rows->sum('contract')) : '' }}</td>
                        <td class="is-num">{{ Format::compact($rows->sum('balance')) }}</td>
                        <td class="is-num">{{ Format::compact($rows->sum('hourlyBilled')) }}</td>
                        <td class="is-num">{{ Format::compact($rows->sum('consultantTotal')) }}</td>
                        <td class="is-num">{{ Format::number($rows->sum('hoursTotal')) }}</td>
                        <td class="is-num">{{ Format::compact($rows->sum('paymentsTotal')) }}</td>
                        <td></td>
                        <td class="is-num">{{ Format::compact($rows->sum('outstanding')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @include('reports.partials.provenance')
    </div>
</body>
</html>
