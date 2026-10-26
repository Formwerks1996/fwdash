<div class="notice me-provenance space-y-1">
    <p class="font-medium"><i class="ph ph-info"></i> {{ __('Where each column comes from') }}</p>
    <p>{{ __('Contract, billed, balance, payments and outstanding come from the billing database: phase draws plus hourly billing as entered at month end, and payments as recorded. Hours come from the hours table, the pre-system opening balance included in a job\'s lifetime hours but never in a month.') }}</p>
    <p>{{ __('$/hr is lifetime: everything billed through the period, less consultant fees, over every hour ever logged, so a long-running job is not flattered by one good month. A month\'s own $/hr is deliberately not shown: a phase draw is a decision about progress and not a settling-up of that month\'s timesheets.') }}</p>
    <p>{{ __('The $95 line and the bands are the firm\'s own, read off the "Report for Jim" sheet (under 95, 95.01 to 130, 130.01 to 165, 165.01 and up). Rows sort worst rate first so the problems are at the top.') }}</p>
</div>
