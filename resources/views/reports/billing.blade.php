@php
    use App\Support\Format;

    $money = fn ($value, int $places = 2) => Format::money($value, $places);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    {{-- Every rule is inline so the file still reads right as an attachment, offline, a year from now. --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 2.5rem 2rem; background: #f7f8f9; color: #14171c; font: 300 14px/1.5 'Helvetica Neue', Helvetica, Arial, system-ui, sans-serif; }
        .toolbar { max-width: 60rem; margin: 0 auto 1rem; display: flex; justify-content: flex-end; gap: .5rem; }
        .toolbar button, .toolbar a { font: 400 .8rem 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: .45rem .9rem; border: 1px solid #00263e; background: #00263e; color: #fff; border-radius: 2px; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #fff; color: #00263e; }
        .report-sheet { max-width: 60rem; margin: 0 auto; padding: 2rem; background: #fff; border: 1px solid #e7e9ea; }
        .report-title { text-align: center; margin-bottom: 1.4rem; }
        .report-title h3 { margin: 0; font-size: 1.1rem; font-weight: 400; letter-spacing: .02em; }
        .report-title p { margin: .2rem 0 0; font-size: .82rem; color: #6f767e; }
        .report-totals { display: flex; flex-wrap: wrap; gap: 1.5rem; padding: .9rem 1rem; background: #fafbfb; border: 1px solid #e7e9ea; margin-bottom: 1.6rem; }
        .report-totals span { display: block; font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #8b9099; }
        .report-totals b { display: block; font-size: 1.1rem; font-weight: 500; font-variant-numeric: tabular-nums; }
        .job { page-break-inside: avoid; break-inside: avoid; margin-bottom: 1.1rem; }
        .job-head { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; padding: .3rem 0; border-bottom: 1px solid #14171c; }
        .job-head small { font-size: .75rem; color: #8b9099; margin-left: .4rem; }
        .job-head strong { font-weight: 500; }
        table { width: 100%; border-collapse: collapse; font-size: .82rem; font-variant-numeric: tabular-nums; }
        td { padding: .25rem .4rem; vertical-align: baseline; }
        td.r { text-align: right; white-space: nowrap; }
        tr.hour td:first-child { padding-left: 1.25rem; color: #6f767e; }
        tr.hour small { color: #8b9099; }
        .data-note { color: #6f767e; font-size: .85rem; }
        .report-foot { max-width: 60rem; margin: 1rem auto 0; font-size: .72rem; color: #8b9099; line-height: 1.6; }
        .report-foot strong { font-weight: 500; color: #44515e; }
        @page { margin: 16mm; }
        @media print {
            body { padding: 0; background: #fff; }
            .toolbar { display: none; }
            .report-sheet { border: 0; padding: 0; max-width: none; }
            .report-foot { margin-top: 1rem; max-width: none; }
        }
    </style>
</head>
<body>
    @unless($download)
        <div class="toolbar">
            <a href="{{ request()->fullUrlWithQuery(['download' => 1]) }}">{{ __('Download as HTML') }}</a>
            <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        </div>
    @endunless

    <div class="report-sheet">
        <div class="report-title">
            <h3>{{ __('Monthly Billing Report') }}</h3>
            <p>Formwerks Architects · {{ Format::month($report['period']) }} · {{ $report['status'] }}</p>
        </div>

        <div class="report-totals">
            <div><span>{{ __('Total this month') }}</span><b>{{ $money($report['totalThisMonth']) }}</b></div>
            <div><span>{{ __('Of which extras (hourly)') }}</span><b>{{ $money($report['totalExtras']) }}</b></div>
            <div><span>{{ __('Milestone billing') }}</span><b>{{ $money($report['totalPhaseBilling']) }}</b></div>
            <div><span>{{ __('Jobs billed') }}</span><b>{{ $report['jobCount'] }}</b></div>
            <div><span>{{ __('Hours logged') }}</span><b>{{ Format::number($report['hoursThisMonth'], 1) }}</b></div>
        </div>

        @forelse($report['jobs'] as $job)
            <div class="job">
                <div class="job-head">
                    <div><strong>{{ $job['jobName'] }}</strong><small>{{ $job['jobNumber'] }} · {{ $job['developer'] ?: '—' }} · {{ $job['division'] ?: '—' }}</small></div>
                    <strong>{{ $money($job['totals']['billed']) }}</strong>
                </div>
                <table>
                    @foreach($job['phases'] as $phase)
                        @continue((float) $phase['billedThisMonth'] == 0)
                        <tr>
                            <td>{{ $phase['phase'] }}</td>
                            <td class="r">{{ $phase['fee'] !== null ? $money($phase['fee'], 0) : '' }}</td>
                            <td class="r">{{ $phase['basis'] === 'amount' ? __('draw') : number_format((float) $phase['pctThisMonth'] * 100, 1).'%' }}</td>
                            <td class="r">{{ $money($phase['billedThisMonth']) }}</td>
                        </tr>
                    @endforeach
                    @foreach($job['hourLines'] as $line)
                        <tr class="hour">
                            <td>{{ $line['staff'] ?? '—' }}@if($line['note']) <small>— {{ $line['note'] }}</small>@endif</td>
                            <td class="r">{{ $line['hours'] !== null ? number_format((float) $line['hours'], 2).' h' : '' }}</td>
                            <td class="r">{{ $line['rate'] !== null ? $money($line['rate'], 0) : '' }}</td>
                            <td class="r">{{ $money($line['total']) }}</td>
                        </tr>
                    @endforeach
                    @if($job['hourlyOverride'] !== null)
                        <tr class="hour">
                            <td colspan="3">{{ __('Hourly billed as a flat figure') }}</td>
                            <td class="r">{{ $money($job['hourlyOverride']) }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        @empty
            <p class="data-note">{{ __('Nothing billed in this period.') }}</p>
        @endforelse
    </div>

    <p class="report-foot">
        <strong>{{ __('Total extras is inside the month\'s total, not added to it.') }}</strong>
        {{ __(':hourly of hourly billing + :phase of milestone billing = :total.', ['hourly' => $money($report['totalExtras']), 'phase' => $money($report['totalPhaseBilling']), 'total' => $money($report['totalThisMonth'])]) }}
        {{ __('Phases that billed nothing this month are omitted.') }}
        {{ __('Generated :when from the billing database.', ['when' => now()->format('j M Y, g:i a')]) }}
    </p>
</body>
</html>
