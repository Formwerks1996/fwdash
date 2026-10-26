@php
    use App\Support\Format;

    /** Excel's accounting format: $ flush left, figure flush right, zero as "$ -", negatives in brackets. */
    $accounting = function ($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        $number = round((float) $value, 2);

        if ($number == 0) {
            return '<span class="xc-acct"><i>$</i><b>-</b></span>';
        }

        $figure = number_format(abs($number), 2);

        return '<span class="xc-acct"><i>$</i><b>'.($number < 0 ? "({$figure})" : $figure).'</b></span>';
    };

    /** The workbook's 3-colour scale on $/hr, with a hard red cut under $95. */
    $rateFill = function ($rate): ?string {
        if ($rate === null || $rate <= 0) {
            return null;
        }

        if ($rate < 95) {
            return '#b33a32';
        }

        if ($rate >= 165) {
            return 'rgb(127,198,239)';
        }

        [$from, $to, $share] = $rate < 130
            ? [[255, 255, 0], [179, 208, 67], ($rate - 95) / 35]
            : [[179, 208, 67], [127, 198, 239], ($rate - 130) / 35];

        $mix = array_map(fn ($a, $b) => (int) round($a + ($b - $a) * $share), $from, $to);

        return "rgb({$mix[0]},{$mix[1]},{$mix[2]})";
    };

    $hours = fn ($value) => abs((float) $value) < 0.005 ? '-' : number_format((float) $value, 2);

    $sum = fn (string $key) => $rows->sum($key);
    $hoursSum = $sum('hoursTotal');
    $totalRate = $hoursSum ? round(($sum('billed') - $sum('consultantTotal')) / $hoursSum, 2) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        @page { size: 17in 11in; margin: .3in .35in; }
        body { margin: 0; padding: 1rem; background: #fff; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; font: 12px 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #5c6873; }
        .toolbar button { font: inherit; padding: .45rem .9rem; border: 1px solid #00263e; background: #00263e; color: #fff; border-radius: 2px; cursor: pointer; }
        .me-classic { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; color: #000; font-size: 7.5pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .xc-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .12in; }
        h1 { font-size: 18pt; font-weight: 700; margin: 0; }
        .xc-legend { border-collapse: collapse; font-size: 9pt; }
        .xc-legend td { padding: 0 10px; min-width: 1.1in; line-height: 1.3; }
        .xc-table { width: 100%; border-collapse: collapse; }
        .xc-table th { font-weight: 700; vertical-align: bottom; text-align: left; padding: 3px 5px; border-top: 1px solid #000; border-bottom: 1px solid #000; line-height: 1.1; }
        .xc-table td { padding: 0 5px; line-height: 1.3; border-bottom: 1px solid #bfbfbf; white-space: nowrap; }
        .xc-table td.xc-c, .xc-table th.xc-c { padding-left: 9px; padding-right: 9px; }
        .xc-table tbody tr:nth-child(odd) td { background-color: #d9d9d9; }
        .xc-table tfoot td { font-weight: 700; border-top: 1px solid #000; border-bottom: 2px solid #000; background: #fff; }
        .xc-table thead { display: table-header-group; }
        .xc-table tfoot { display: table-row-group; }
        .xc-table tr { break-inside: avoid; }
        .xc-r { text-align: right; }
        .xc-c { text-align: center; }
        th.xc-r, th.xc-c { text-align: center; }
        .xc-acct { display: flex; justify-content: space-between; gap: 6px; }
        .xc-acct i { font-style: normal; }
        .xc-acct b { font-weight: inherit; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ __('Print to 11x17 (tabloid), landscape. In the print dialog choose "Save as PDF" to keep a file.') }}</span>
        <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>

    <div class="me-classic">
        <div class="xc-top">
            <h1>MF Monthly Contract Status Report {{ Format::month($period) }}</h1>
            <table class="xc-legend">
                <tr><td style="background: #FF0000">&lt; 95</td></tr>
                <tr><td style="background: #FFFF00">95.01 to 130</td></tr>
                <tr><td style="background: #92D050">130.01 to 165</td></tr>
                <tr><td style="background: #8BBFDB">165.01 and up</td></tr>
            </table>
        </div>

        <table class="xc-table">
            <thead>
                <tr>
                    <th>DeveloperName</th>
                    <th>JobName</th>
                    <th class="xc-r">Contract Amount</th>
                    <th class="xc-r">Billed to Date ($)</th>
                    <th class="xc-c">Billed To Date (%)</th>
                    <th class="xc-r">Balance</th>
                    <th class="xc-r">Billed to Date ($) - Extras</th>
                    <th class="xc-r">Balance Design</th>
                    <th class="xc-r">Balance CA</th>
                    <th class="xc-r">Consultants Cost</th>
                    <th class="xc-r">Hours (total)</th>
                    <th class="xc-c">Weight</th>
                    <th class="xc-r">$ / hr</th>
                    <th class="xc-r">Hours (this Month)</th>
                    <th class="xc-c">Last Payment Date</th>
                    <th class="xc-r">Last Payment Amount</th>
                    <th class="xc-r">Total Payments</th>
                    <th class="xc-r">Outstanding Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        $fill = $rateFill($row['rateLive']);
                    @endphp
                    <tr>
                        <td>{{ $row['developer'] }}</td>
                        <td>{{ $row['jobName'] }}</td>
                        <td>{!! $accounting($row['contract']) !!}</td>
                        <td>{!! $accounting($row['billed']) !!}</td>
                        <td class="xc-c">{{ $row['billedPct'] !== null ? round($row['billedPct'] * 100).'%' : '' }}</td>
                        <td>{!! $accounting($row['balance']) !!}</td>
                        <td>{!! $accounting($row['hourlyBilled']) !!}</td>
                        <td>{!! $accounting($row['balanceDesign']) !!}</td>
                        <td>{!! $accounting($row['balanceCA']) !!}</td>
                        <td>{!! $accounting($row['consultantTotal']) !!}</td>
                        <td class="xc-r">{{ $hours($row['hoursTotal']) }}</td>
                        <td class="xc-c">{{ $hoursSum ? number_format($row['hoursTotal'] / $hoursSum * 100, 1) : '0.0' }}%</td>
                        <td @if($fill) style="background-color: {{ $fill }}" @endif>{!! $accounting($row['rateLive']) !!}</td>
                        <td class="xc-r">{{ $hours($row['hoursMonth'] ?? 0) }}</td>
                        <td class="xc-c">{{ $row['lastPaymentDate'] ? str_replace('-', '/', $row['lastPaymentDate']) : '' }}</td>
                        <td>{!! $row['lastPaymentDate'] ? $accounting($row['lastPaymentAmount']) : '' !!}</td>
                        <td>{!! $row['lastPaymentDate'] ? $accounting($row['paymentsTotal']) : '' !!}</td>
                        <td>{!! $accounting($row['outstanding']) !!}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $fill = $rateFill($totalRate);
                @endphp
                <tr>
                    <td>Grand Total</td>
                    <td></td>
                    <td>{!! $accounting($sum('contract')) !!}</td>
                    <td>{!! $accounting($sum('billed')) !!}</td>
                    <td class="xc-c">{{ $sum('contract') ? round($sum('billed') / $sum('contract') * 100).'%' : '' }}</td>
                    <td>{!! $accounting($sum('balance')) !!}</td>
                    <td>{!! $accounting($sum('hourlyBilled')) !!}</td>
                    <td>{!! $accounting($sum('balanceDesign')) !!}</td>
                    <td>{!! $accounting($sum('balanceCA')) !!}</td>
                    <td>{!! $accounting($sum('consultantTotal')) !!}</td>
                    <td class="xc-r">{{ $hours($hoursSum) }}</td>
                    <td class="xc-c">100.0%</td>
                    <td @if($fill) style="background-color: {{ $fill }}" @endif>{!! $accounting($totalRate) !!}</td>
                    <td class="xc-r">{{ $hours($sum('hoursMonth')) }}</td>
                    <td></td>
                    <td></td>
                    <td>{!! $accounting($sum('paymentsTotal')) !!}</td>
                    <td>{!! $accounting($sum('outstanding')) !!}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
