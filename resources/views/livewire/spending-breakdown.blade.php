@php
    use App\Support\Format;
    use Carbon\CarbonImmutable;
@endphp

<div>
    <x-modal wire:model="open" :title="$this->title()" max-width="860px">
        @if($open)
            @php
                $b = $this->breakdown;
                $staff = $b['staff'];
            @endphp

            <div class="space-y-7">
                <section>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="section-title">{{ __('From the Costs sheet') }}</h3>
                        @if($b['costsTotal'])
                            <span class="font-heading text-xl text-navy">{{ Format::money($b['costsTotal']) }}</span>
                        @endif
                    </div>

                    @if($b['categories'])
                        <ul class="mt-3 space-y-1.5 text-sm">
                            @foreach($b['categories'] as $line)
                                <li class="grid grid-cols-[minmax(7rem,12rem)_1fr_auto_3.2rem] items-center gap-3">
                                    <span class="truncate">{{ $line['category'] }}</span>
                                    <span class="h-2 rounded-[2px] bg-[#eef0f2]"><i class="block h-full rounded-[2px] bg-[#c4694a]" style="width: {{ round($line['share'] * 100, 2) }}%"></i></span>
                                    <b class="text-right font-medium tabular-nums">{{ Format::money($line['amount']) }}</b>
                                    <span class="text-right text-xs text-muted tabular-nums">{{ Format::pct($line['share']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm text-muted">{{ __('No costs entered for these months. Add them to the Costs sheet of the workbook — one row per month, a column per expense (Payroll, Rent, Software…).') }}</p>
                    @endif
                </section>

                @if(count($b['months']) > 1 && ($b['costsTotal'] || $staff))
                    <section>
                        <h3 class="section-title">{{ __('Month by month') }}</h3>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-xs text-muted">
                                    <tr class="border-b border-dark">
                                        <th class="py-1 text-left font-normal">{{ __('Month') }}</th>
                                        <th class="py-1 text-right font-normal">{{ __('Payroll') }}</th>
                                        <th class="py-1 text-right font-normal">{{ __('Overhead') }}</th>
                                        <th class="py-1 text-right font-normal">{{ __('Costs sheet') }}</th>
                                        @if($staff)
                                            <th class="py-1 text-right font-normal">{{ __('Staff time') }}</th>
                                        @endif
                                        <th class="py-1 pl-4 text-left font-normal">{{ __('Note') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="tabular-nums">
                                    @foreach($b['months'] as $month)
                                        <tr class="border-b border-[#f0f1f3]">
                                            <td class="py-1">{{ CarbonImmutable::createFromFormat('!Y-m', $month['key'])->format('M Y') }}</td>
                                            <td class="py-1 text-right">{{ $month['total'] ? Format::compact($month['payroll']) : '—' }}</td>
                                            <td class="py-1 text-right">{{ $month['total'] ? Format::compact($month['overhead']) : '—' }}</td>
                                            <td class="py-1 text-right font-medium">{{ $month['total'] ? Format::compact($month['total']) : '—' }}</td>
                                            @if($staff)
                                                <td class="py-1 text-right">{{ Format::compact($month['staff']) }}</td>
                                            @endif
                                            <td class="py-1 pl-4 text-xs text-muted">{{ $month['note'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @elseif(($b['months'][0]['note'] ?? null))
                    <p class="-mt-4 text-xs text-muted">{{ $b['months'][0]['note'] }}</p>
                @endif

                @if($staff)
                    <section>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="section-title">{{ __('Staff time at cost') }}</h3>
                            <span class="font-heading text-xl text-navy">{{ Format::money($staff['total']) }}</span>
                        </div>
                        <p class="section-note">
                            {{ __('Each person\'s hours, all of them, at their hourly cost: an estimate of payroll.') }}
                            @if($b['costsTotal'])
                                {{ __('It overlaps the Payroll line above, so the two are not added.') }}
                            @endif
                            @if($b['payroll'] && $staff['total'])
                                {{ __('Payroll on the sheet is :pct of it.', ['pct' => Format::pct($b['payroll'] / $staff['total'])]) }}
                            @endif
                        </p>

                        @if($staff['people'])
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-muted">
                                        <tr class="border-b border-dark">
                                            <th class="py-1 text-left font-normal">{{ __('Person') }}</th>
                                            <th class="py-1 text-right font-normal">{{ __('Hours') }}</th>
                                            <th class="py-1 text-right font-normal">{{ __('Cost / h') }}</th>
                                            <th class="py-1 text-right font-normal">{{ __('Cost') }}</th>
                                            <th class="w-[22%] py-1 pl-3 font-normal"><span class="sr-only">{{ __('Share') }}</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @foreach($staff['people'] as $person)
                                            <tr @class(['border-b border-[#f0f1f3]', 'text-muted' => $person['cost'] === null])>
                                                <td class="py-1">{{ $person['name'] }}</td>
                                                <td class="py-1 text-right">{{ Format::number($person['hours']) }}</td>
                                                <td class="py-1 text-right">{{ $person['rate'] ? '$'.number_format($person['rate'], 2) : __('no rate') }}</td>
                                                <td class="py-1 text-right font-medium">{{ Format::money($person['cost']) }}</td>
                                                <td class="py-1 pl-3"><span class="block h-2 rounded-[2px] bg-[#eef0f2]"><i class="block h-full rounded-[2px] bg-navy" style="width: {{ round($person['share'] * 100, 2) }}%"></i></span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="tabular-nums">
                                        <tr>
                                            <td class="py-1 font-medium">{{ __('Total') }}</td>
                                            <td class="py-1 text-right">{{ Format::number($staff['hours']) }}</td>
                                            <td></td>
                                            <td class="py-1 text-right font-medium">{{ Format::money($staff['total']) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            @if($staff['uncostedHours'])
                                <p class="mt-2 text-xs text-[#9d6714]">
                                    <i class="ph ph-warning"></i> {{ __(':hours h are by people with no cost rate, so they are not costed.', ['hours' => Format::number($staff['uncostedHours'])]) }}
                                    @can(\App\Enums\Permission::ManageStaffCosts->value)
                                        <a href="{{ route('staff-costs') }}" wire:navigate class="underline">{{ __('Set rates') }}</a>
                                    @endcan
                                </p>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-muted">{{ __('No hours logged for these months.') }}</p>
                        @endif
                    </section>
                @endif
            </div>
        @endif
    </x-modal>
</div>
