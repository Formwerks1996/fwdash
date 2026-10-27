@php
    use App\Models\ProjectRole;

    $rows = $this->rows;
    $choices = ['active' => __('Active'), 'unmanaged' => __('Active, no PM'), 'all' => __('All')];
@endphp

<div class="space-y-8">
    <div>
        <h1 class="page-title">{{ __('Leads & PMs') }}</h1>
        <p class="page-subtitle">{{ __('Who leads each project, and who manages it under them. Changes show on the PM Desk and the Projects pages straight away.') }}</p>
    </div>

    <div class="card p-0">
        <div class="toolbar px-6 pt-5 pb-4">
            <div class="relative w-full max-w-sm">
                <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-muted"></i>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search project, job #, developer, lead, PM') }}" class="search-input">
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="seg">
                    @foreach($choices as $value => $label)
                        <button type="button" wire:click="$set('show', '{{ $value }}')" @class(['is-active' => $show === $value])>{{ $label }}</button>
                    @endforeach
                </div>
                <span class="text-xs text-muted">{{ trans_choice(':count project|:count projects', $rows->count(), ['count' => $rows->count()]) }}</span>
            </div>
        </div>

        @error('role') <p class="error-note px-6 pb-3">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Project') }}</th>
                        <th>{{ __('Developer') }}</th>
                        <th>{{ __('Lead') }}</th>
                        <th>{{ __('Project manager') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr wire:key="roles-{{ $row['jobNumber'] }}">
                            <td>
                                <a href="{{ route('projects.show', $row['jobNumber']) }}" class="font-medium text-ink hover:text-navy" wire:navigate>{{ $row['jobName'] }}</a>
                                <span class="ml-1 text-xs text-muted">{{ $row['jobNumber'] }}</span>
                            </td>
                            <td class="max-w-[14rem] truncate">{{ $row['developer'] ?? '—' }}</td>
                            <td>
                                <select class="select" aria-label="{{ __('Lead of :project', ['project' => $row['jobName']]) }}" wire:change="setRole(@js($row['jobNumber']), @js(ProjectRole::Lead), $event.target.value)">
                                    <option value="">{{ __('No one') }}</option>
                                    @foreach($leads as $person)
                                        <option value="{{ $person->name }}" @selected($row['lead'] === $person->name)>{{ $person->displayName() }}</option>
                                    @endforeach
                                    @if($row['lead'] && ! $leads->contains('name', $row['lead']))
                                        <option value="{{ $row['lead'] }}" selected>{{ $row['lead'] }}</option>
                                    @endif
                                </select>
                            </td>
                            <td>
                                <select class="select" aria-label="{{ __('Project manager of :project', ['project' => $row['jobName']]) }}" wire:change="setRole(@js($row['jobNumber']), @js(ProjectRole::ProjectManager), $event.target.value)">
                                    <option value="">{{ __('No one') }}</option>
                                    @foreach($team as $person)
                                        <option value="{{ $person->name }}" @selected($row['pm'] === $person->name)>{{ $person->displayName() }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-muted">{{ __('No projects match.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
