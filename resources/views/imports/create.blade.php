<x-layouts::app :title="__('Upload workbook')">
    <div class="max-w-4xl">
        <h1 class="page-title">{{ __('Upload workbook') }}</h1>
        <p class="page-subtitle">{{ __('Update the dashboard from the Dashboard Data spreadsheet.') }}</p>

        @if($latest = $imports->firstWhere('id', session('import')))
            @php
                $mismatches = $latest->reconciliation['mismatches'] ?? [];
            @endphp

            <div class="card mt-8 border-t-2 border-t-positive">
                <h2 class="section-title">{{ __('Imported :file', ['file' => $latest->filename]) }}</h2>
                <p class="section-note mt-1">
                    {{ __(':projects projects, :entries month-end entries, :payments payments and :hours hours rows.', [
                        'projects' => number_format($latest->counts['projects'] ?? 0),
                        'entries' => number_format($latest->counts['month_entries'] ?? 0),
                        'payments' => number_format($latest->counts['payments'] ?? 0),
                        'hours' => number_format($latest->counts['job_hours'] ?? 0),
                    ]) }}
                </p>

                @if($latest->reconciliation['checked'] ?? 0)
                    <p class="mt-4 text-sm">
                        @if($mismatches)
                            <i class="ph ph-warning text-warning"></i>
                            {{ __(':count of :checked report figures differ from the workbook\'s Report for Jim.', ['count' => count($mismatches), 'checked' => number_format($latest->reconciliation['checked'])]) }}
                        @else
                            <i class="ph ph-check-circle text-positive"></i>
                            {{ __('All :checked figures on the workbook\'s Report for Jim match the dashboard to the cent.', ['checked' => number_format($latest->reconciliation['checked'])]) }}
                        @endif
                    </p>
                @endif

                @if($latest->warnings || $mismatches)
                    <ul class="mt-4 max-h-64 list-disc space-y-1 overflow-y-auto pl-5 text-xs text-ink-2">
                        @foreach([...$latest->warnings, ...$mismatches] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="card mt-8 space-y-6">
            @csrf

            <div class="note">
                {{ __('Uploading replaces projects, fees, month-end billing, hourly lines, payments, consultants, hours, staff rates, software and hardware with what the workbook holds. Project statuses, settings and the change log are kept.') }}
            </div>

            <div>
                <label for="workbook" class="field-label">{{ __('Dashboard Data.xlsx') }}</label>
                <input id="workbook" name="workbook" type="file" accept=".xlsx" required class="field-input file:mr-4 file:border-0 file:bg-gold-soft file:px-3 file:py-1 file:text-sm file:text-navy">
                @error('workbook')
                    <p class="error-note mt-2">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-start gap-2 text-sm text-ink-2">
                <input type="checkbox" name="confirm" value="1" class="mt-1 accent-navy">
                {{ __('I understand this replaces the current data with the workbook\'s.') }}
            </label>
            @error('confirm')
                <p class="error-note">{{ $message }}</p>
            @enderror

            <button type="submit" class="btn">
                <i class="ph ph-upload-simple"></i>
                {{ __('Upload and update') }}
            </button>
        </form>

        @if($imports->isNotEmpty())
            <div class="card mt-8 overflow-x-auto p-0">
                <div class="px-6 pt-5 pb-2">
                    <h2 class="section-title">{{ __('Recent uploads') }}</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('When') }}</th>
                            <th>{{ __('Who') }}</th>
                            <th>{{ __('File') }}</th>
                            <th class="is-num">{{ __('Projects') }}</th>
                            <th class="is-num">{{ __('Warnings') }}</th>
                            <th class="is-num">{{ __('Mismatches') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($imports as $import)
                            <tr>
                                <td>{{ $import->created_at->timezone(config('app.display_timezone'))->format('M j, Y g:i a') }}</td>
                                <td>{{ $import->user?->name ?? __('Command line') }}</td>
                                <td>{{ $import->filename }}</td>
                                <td class="is-num">{{ $import->counts['projects'] ?? 0 }}</td>
                                <td class="is-num">{{ count($import->warnings) }}</td>
                                <td class="is-num">{{ count($import->reconciliation['mismatches'] ?? []) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts::app>
