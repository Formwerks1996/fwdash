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

        <div
            class="overflow-x-auto"
            x-data="{ leads: @js($people->where('partner', true)->values()), team: @js($people->values()) }"
        >
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Project') }}</th>
                        <th>{{ __('Developer') }}</th>
                        <th class="w-[15rem]">{{ __('Lead') }}</th>
                        <th class="w-[15rem]">{{ __('Project manager') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr wire:key="roles-{{ $row['jobNumber'] }}" class="group">
                            <td>
                                <a href="{{ route('projects.show', $row['jobNumber']) }}" class="font-medium text-ink hover:text-navy" wire:navigate>{{ $row['jobName'] }}</a>
                                <span class="ml-1 text-xs text-muted">{{ $row['jobNumber'] }}</span>
                            </td>
                            <td class="max-w-[14rem] truncate">{{ $row['developer'] ?? '—' }}</td>
                            @foreach([ProjectRole::Lead => ['leads', $row['lead'], __('Lead')], ProjectRole::ProjectManager => ['team', $row['pm'], __('PM')]] as $role => [$list, $current, $roleLabel])
                                @php($person = $current ? $people->get($current) : null)
                                <td class="py-1.5">
                                    <div
                                        class="relative"
                                        x-data="assignee(@js($row['jobNumber']), @js($role), @js($list))"
                                        data-current="{{ $current }}"
                                        x-on:click.outside="close()"
                                        x-on:keydown.escape.prevent.stop="close(true)"
                                        x-on:scroll.window="open && place()"
                                        x-on:resize.window="open && place()"
                                    >
                                        <button
                                            type="button"
                                            x-ref="trigger"
                                            x-on:click="toggle()"
                                            @class([
                                                'assignee',
                                                'is-empty' => ! $current,
                                            ])
                                            :class="open && 'is-open'"
                                            aria-haspopup="listbox"
                                            :aria-expanded="open"
                                            aria-label="{{ __(':role of :project', ['role' => $roleLabel, 'project' => $row['jobName']]) }}"
                                        >
                                            @if($current)
                                                <span class="avatar">{{ $person['initials'] ?? \App\Livewire\Projects\ProjectRoles::initials($current) }}</span>
                                                <span class="truncate">{{ $person['label'] ?? $current }}</span>
                                            @else
                                                <span class="avatar is-empty"><i class="ph ph-plus"></i></span>
                                                <span>{{ __('Assign') }}</span>
                                            @endif
                                            <i class="ph ph-caret-down ml-auto text-xs opacity-0 transition-opacity group-hover:opacity-60"></i>
                                        </button>

                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition duration-150 ease-out"
                                            x-transition:enter-start="-translate-y-1 opacity-0"
                                            x-transition:enter-end="translate-y-0 opacity-100"
                                            x-ref="menu"
                                            :style="position"
                                            class="fixed z-40 w-64 border border-line bg-white shadow-[0_16px_32px_-12px_rgba(0,38,62,0.25)]"
                                        >
                                            <div class="relative border-b border-line">
                                                <i class="ph ph-magnifying-glass absolute top-1/2 left-3 -translate-y-1/2 text-xs text-muted"></i>
                                                <input
                                                    x-ref="search"
                                                    x-model="query"
                                                    x-on:input="cursor = 0"
                                                    x-on:keydown.arrow-down.prevent="cursor = Math.min(cursor + 1, options.length - 1)"
                                                    x-on:keydown.arrow-up.prevent="cursor = Math.max(cursor - 1, 0)"
                                                    x-on:keydown.enter.prevent="options[cursor] && choose(options[cursor].name)"
                                                    class="w-full bg-transparent py-2 pr-3 pl-8 text-[0.78rem] focus:outline-none"
                                                    placeholder="{{ $role === ProjectRole::Lead ? __('Find a partner') : __('Find someone') }}"
                                                    autocomplete="off"
                                                >
                                            </div>
                                            <ul class="max-h-64 overflow-y-auto py-1 text-[0.78rem]" role="listbox">
                                                <template x-for="(person, index) in options" :key="person.name">
                                                    <li>
                                                        <button
                                                            type="button"
                                                            class="flex w-full items-center gap-2.5 px-3 py-1.5 text-left"
                                                            :class="index === cursor && 'bg-gold-soft'"
                                                            role="option"
                                                            :aria-selected="person.name === current"
                                                            x-on:mousemove="cursor = index"
                                                            x-on:click="choose(person.name)"
                                                        >
                                                            <span class="avatar" x-text="person.initials"></span>
                                                            <span class="truncate text-ink" x-text="person.label"></span>
                                                            <span class="ml-auto text-[0.68rem] text-muted" x-text="person.hint"></span>
                                                            <i class="ph ph-check w-3 text-navy" :class="person.name === current || 'invisible'"></i>
                                                        </button>
                                                    </li>
                                                </template>
                                                <li x-show="! options.length" class="px-3 py-3 text-muted">{{ __('Nobody matches.') }}</li>
                                            </ul>
                                            <button
                                                type="button"
                                                x-show="current"
                                                class="flex w-full items-center gap-2 border-t border-line px-3 py-2 text-left text-[0.76rem] text-muted hover:text-danger"
                                                x-on:click="choose('')"
                                            ><i class="ph ph-user-minus"></i>{{ $role === ProjectRole::Lead ? __('Remove lead') : __('Remove PM') }}</button>
                                        </div>
                                    </div>
                                </td>
                            @endforeach
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
