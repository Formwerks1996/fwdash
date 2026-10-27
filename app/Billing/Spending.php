<?php

namespace App\Billing;

use App\Models\MonthlyCost;
use Illuminate\Support\Facades\DB;

/*
 * Where the money went over a set of months, from both places that know:
 * the bookkeeper's totals on the Costs sheet, line by line, and staff time at
 * each person's hourly cost. The two overlap (payroll is mostly staff time),
 * so they are shown side by side and never added together.
 */
class Spending
{
    public function __construct(protected StaffCosts $staffCosts) {}

    /**
     * What each month cost, for profit: the Costs sheet total where the month
     * has one, otherwise staff time at cost as an estimate (no overhead in it).
     *
     * @param  array<int, string>  $months  "YYYY-MM"
     * @return array<string, array{total: ?float, payroll: float, overhead: float, basis: ?string, uncostedHours: float}> basis is "sheet", "staff" or null
     */
    public function monthCosts(array $months, bool $withStaff): array
    {
        $sheet = MonthlyCost::byMonth();
        $costs = [];

        foreach ($months as $month) {
            if (isset($sheet[$month])) {
                $costs[$month] = [...$sheet[$month], 'basis' => 'sheet', 'uncostedHours' => 0.0];

                continue;
            }

            $staff = $withStaff ? $this->staffCosts->month($month) : null;

            $costs[$month] = $staff && $staff['spent'] !== null
                ? ['total' => $staff['spent'], 'payroll' => $staff['spent'], 'overhead' => 0.0, 'basis' => 'staff', 'uncostedHours' => $staff['uncostedHours']]
                : ['total' => null, 'payroll' => 0.0, 'overhead' => 0.0, 'basis' => null, 'uncostedHours' => 0.0];
        }

        return $costs;
    }

    /**
     * @param  array<int, string>  $months  "YYYY-MM"
     * @return array{
     *     months: array<int, array{key: string, payroll: float, overhead: float, total: float, staff: ?float, note: ?string}>,
     *     categories: array<int, array{category: string, amount: float, share: float}>,
     *     costsTotal: float,
     *     payroll: float,
     *     staff: ?array{people: array<int, array{name: string, hours: float, cost: ?float, rate: ?float, share: float}>, total: float, hours: float, uncostedHours: float}
     * }
     */
    public function breakdown(array $months, bool $withStaff): array
    {
        sort($months);

        $byMonth = MonthlyCost::byMonth();
        $notes = DB::table('monthly_cost_notes')->whereIn('month', $months)->pluck('note', 'month');
        $categories = MonthlyCost::byCategory($months);
        $costsTotal = array_sum($categories);
        $staff = $withStaff ? $this->staff($months) : null;

        return [
            'months' => array_map(fn (string $month) => [
                'key' => $month,
                'payroll' => $byMonth[$month]['payroll'] ?? 0.0,
                'overhead' => $byMonth[$month]['overhead'] ?? 0.0,
                'total' => $byMonth[$month]['total'] ?? 0.0,
                'staff' => $staff['byMonth'][$month] ?? null,
                'note' => $notes[$month] ?? null,
            ], $months),
            'categories' => collect($categories)
                ->map(fn (float $amount, string $category) => ['category' => $category, 'amount' => $amount, 'share' => $costsTotal ? $amount / $costsTotal : 0.0])
                ->values()
                ->all(),
            'costsTotal' => $costsTotal,
            'payroll' => (float) ($categories[MonthlyCost::Payroll] ?? 0),
            'staff' => $staff ? collect($staff)->except('byMonth')->all() : null,
        ];
    }

    /**
     * Each person's hours and cost over the months; a rate change mid-range
     * prices each month at the rate it had.
     *
     * @param  array<int, string>  $months
     * @return array<string, mixed>
     */
    protected function staff(array $months): array
    {
        $people = [];
        $byMonth = [];

        foreach ($months as $month) {
            foreach ($this->staffCosts->hoursIn($month) as $email => $person) {
                $rate = $this->staffCosts->rateOn($email, $month);

                $people[$email] ??= ['name' => $person['name'] ?? $email, 'hours' => 0.0, 'costedHours' => 0.0, 'cost' => null];
                $people[$email]['hours'] += $person['hours'];

                if ($rate !== null) {
                    $cost = $person['hours'] * $rate;
                    $people[$email]['costedHours'] += $person['hours'];
                    $people[$email]['cost'] = ($people[$email]['cost'] ?? 0.0) + $cost;
                    $byMonth[$month] = ($byMonth[$month] ?? 0.0) + $cost;
                }
            }
        }

        $people = collect($people);
        $total = (float) $people->sum('cost');

        return [
            'people' => $people
                ->map(fn (array $person) => [
                    'name' => $person['name'],
                    'hours' => round($person['hours'], 2),
                    'cost' => $person['cost'] === null ? null : round($person['cost'], 2),
                    'rate' => $person['costedHours'] ? $person['cost'] / $person['costedHours'] : null,
                    'share' => $total && $person['cost'] ? $person['cost'] / $total : 0.0,
                ])
                ->sortByDesc(fn (array $person) => $person['cost'] ?? -1)
                ->values()
                ->all(),
            'total' => round($total, 2),
            'hours' => (float) $people->sum('hours'),
            'uncostedHours' => round($people->sum(fn (array $person) => $person['hours'] - $person['costedHours']), 2),
            'byMonth' => array_map(fn (float $cost) => round($cost, 2), $byMonth),
        ];
    }
}
