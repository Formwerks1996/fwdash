@php
    use App\Support\LineupTree;
    use Carbon\CarbonImmutable;

    $tree = $this->tree;
    $projects = $this->projects;
    $partners = $tree->partners();
    $loose = $tree->loose();
    $total = count($tree->nonPartners());
    $people = fn (int $count) => trans_choice(':count person|:count people', $count, ['count' => $count]);
    $shared = ['tree' => $tree, 'tags' => $tags, 'badges' => $badges, 'projects' => $projects, 'canEdit' => $canEdit];

    $asking = collect($badges)
        ->filter(fn (array $badge) => in_array($badge['type'], ['deadline', 'help'], true))
        ->sortBy(fn (array $badge) => $badge['type'] === 'deadline' ? 0 : 1);
    $available = collect($badges)->filter(fn (array $badge) => $badge['type'] === 'available');

    $payload = [
        'boss' => (object) $tree->boss,
        'partners' => $partners,
        'people' => collect($tree->people)->map(fn ($member) => ['name' => $member->displayName(), 'first' => $member->firstName()]),
        'editable' => $canEdit,
    ];
@endphp

<div class="lu-page" data-lineup="{{ json_encode($payload) }}">
    <style>
        .lu-page { max-width: 1180px; --lu-a: #00263e; --lu-b: #85754e; --lu-hair: #d8cfb4; --lu-hot: #f5f1e6; color: #0f2233; }
        .lu-caps { font-size: .75rem; font-weight: 500; letter-spacing: .2em; text-transform: uppercase; color: #5c6873; }
        .lu-top { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem 2rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--lu-hair); }
        .lu-sub { color: #44515e; font-size: .92rem; margin-top: .25rem; }
        .lu-week { display: flex; align-items: center; gap: .25rem; }
        .lu-nav { width: 2rem; height: 2rem; cursor: pointer; font-size: 1rem; }
        .lu-nav:hover { color: #6b5c3c; }
        .lu-week-label { text-align: center; min-width: 11rem; }
        .lu-week-label b { display: block; font-weight: 400; font-size: 1.15rem; }
        .lu-actions { display: flex; gap: 1.25rem; align-items: baseline; }
        .lu-link { border-bottom: 1px solid transparent; padding: .25rem 0; cursor: pointer; font-size: .75rem; font-weight: 500; letter-spacing: .2em; text-transform: uppercase; color: #44515e; }
        .lu-link:hover { color: #0f2233; border-bottom-color: var(--lu-b); }
        .lu-link[disabled] { opacity: .35; cursor: default; border-bottom-color: transparent; }
        .lu-link.lu-erase, .lu-link.lu-erase:hover { color: #c62828; }
        .lu-link.lu-erase:hover { border-bottom-color: #c62828; }
        .lu-erase-ask { display: inline-flex; gap: .7rem; align-items: baseline; font-size: .78rem; }
        .lu-sides { display: grid; grid-template-columns: 1fr 1fr; padding-top: 2rem; }
        .lu-side { padding: 0 2.25rem .5rem; min-width: 0; transition: background .15s; }
        .lu-side:first-child { padding-left: 0; border-right: 1px solid var(--lu-hair); }
        .lu-side:last-child { padding-right: 0; }
        .lu-a { --lu-c: var(--lu-a); } .lu-b { --lu-c: var(--lu-b); }
        .lu-phead { padding: .25rem .6rem .9rem; margin: 0 -.6rem .5rem; border-bottom: 2px solid var(--lu-c); }
        .lu-phead h2 { font-size: clamp(1.5rem, 2.4vw, 2rem); font-weight: 400; letter-spacing: .12em; text-transform: uppercase; line-height: 1.15; margin-bottom: .3rem; display: inline-block; }
        .lu-direct { margin-top: .6rem; display: flex; flex-wrap: wrap; gap: .2rem .9rem; align-items: baseline; }
        .lu-teams { display: grid; gap: .2rem; }
        .lu-team { padding: .7rem .6rem .6rem; margin: 0 -.6rem; transition: background .15s; }
        .lu-team-head { display: flex; align-items: baseline; justify-content: space-between; gap: .75rem; }
        .lu-people { list-style: none; margin: .35rem 0 0; padding: 0 0 0 .85rem; border-left: 1px solid var(--lu-c); display: grid; gap: 1px; }
        .lu-people .lu-people { margin-top: .1rem; margin-bottom: .25rem; }
        .lu-row { display: flex; align-items: baseline; justify-content: space-between; gap: .75rem; }
        .lu-none { font-size: .82rem; color: #5c6873; font-style: italic; padding: .1rem 0 .1rem .5rem; }
        .lu-empty { color: #5c6873; font-size: .9rem; padding: 1.4rem .6rem; border: 1px dashed #e4e6e8; margin-top: .4rem; text-align: center; }
        .lu-name { display: inline-flex; align-items: baseline; gap: .1rem; min-width: 0; flex-wrap: wrap; }
        .lu-who { display: inline-flex; align-items: baseline; padding: .18rem .5rem; margin-left: -.5rem; font-size: .95rem; cursor: grab; text-align: left; user-select: none; -webkit-user-select: none; touch-action: none; transition: background .12s, opacity .2s; }
        .lu-who.is-static { cursor: default; touch-action: auto; }
        .lu-who:hover { background: #f5f6f7; }
        .lu-who.lu-lead { font-size: 1.2rem; }
        .lu-who.is-sel { background: var(--lu-hot); outline: 1px solid var(--lu-b); }
        .lu-dim { opacity: .28; }
        .lu-badge { display: inline-flex; align-items: baseline; gap: .3rem; padding: .05rem .4rem; margin-left: .35rem; cursor: pointer; font-size: .75rem; font-weight: 500; letter-spacing: .14em; text-transform: uppercase; vertical-align: middle; white-space: nowrap; }
        .lu-badge[disabled] { cursor: default; }
        .lu-badge small { font-size: .75rem; letter-spacing: 0; text-transform: none; font-weight: 400; max-width: 11rem; overflow: hidden; text-overflow: ellipsis; }
        .lu-badge-deadline { background: #c62828; color: #fff; }
        .lu-badge-help { background: #f6c945; color: #3d2f00; }
        .lu-badge-available { background: #2e7d4f; color: #fff; }
        .lu-badge-add, .lu-tag-add { padding: 0 .25rem; margin-left: .15rem; font-size: .78rem; color: #5c6873; cursor: pointer; opacity: 0; transition: opacity .12s; }
        .lu-name:hover .lu-badge-add, .lu-name:hover .lu-tag-add, .lu-badge-add:focus-visible, .lu-tag-add:focus-visible { opacity: 1; }
        .lu-badge-add:hover { color: #c62828; }
        .lu-tag-add:hover { color: #6b5c3c; }
        .lu-dragging .lu-badge-add, .lu-dragging .lu-tag-add { display: none; }
        .lu-ptag { border: 1px solid var(--lu-hair); background: #faf8f2; color: #44515e; padding: .05rem .45rem; margin-left: .35rem; font-size: .75rem; cursor: pointer; max-width: 15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        .lu-ptag[disabled] { cursor: default; }
        .lu-ptag b { font-weight: 500; color: #6b5c3c; }
        .lu-ptag:hover { border-color: var(--lu-b); }
        .lu-hand { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 1rem 2.5rem; margin-top: 1.5rem; padding: .9rem 1.1rem; border: 1px solid #e4e6e8; border-left-color: #c62828; background: #fff; }
        .lu-hand.is-quiet { display: flex; flex-wrap: wrap; gap: 1rem; align-items: baseline; border-left-color: #e4e6e8; background: none; }
        .lu-hand-col { display: grid; gap: .35rem; align-content: start; min-width: 0; }
        .lu-hand-row { display: grid; grid-template-columns: auto auto auto minmax(0, 1fr); gap: .55rem; align-items: baseline; font-size: .9rem; }
        .lu-hand-row b { font-weight: 400; }
        .lu-hand-what { font-size: .8rem; color: #44515e; }
        .lu-hand-deadline .lu-hand-what { color: #c62828; font-weight: 500; }
        .lu-hand-meta { font-size: .78rem; color: #5c6873; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lu-dot { width: .55rem; height: .55rem; border-radius: 50%; display: inline-block; background: #f6c945; }
        .lu-hand-deadline .lu-dot { background: #c62828; }
        .lu-dot-available { background: #2e7d4f; margin-right: .4rem; }
        .lu-hand-free { display: flex; flex-wrap: wrap; gap: .3rem 1.2rem; font-size: .9rem; }
        .lu-hand-free small { color: #5c6873; font-size: .75rem; }
        .lu-dragging .lu-who { cursor: grabbing; }
        .lu-dragging .lu-sides .lu-who:not(.lu-nodrop) { box-shadow: inset 0 -1px 0 var(--lu-hair); }
        .lu-who.lu-nodrop { opacity: .25; }
        .lu-hot-name, .lu-who.lu-hot-name { background: var(--lu-a) !important; color: #fff !important; box-shadow: none !important; opacity: 1 !important; }
        .lu-phead h2.lu-hot-name { padding: 0 .4rem; margin-left: -.4rem; }
        .lu-hot { background: var(--lu-hot) !important; }
        .lu-tray { margin-top: 2.25rem; padding: 1rem 1.1rem; background: #fdfdfd; border-top: 1px solid var(--lu-hair); display: grid; gap: .6rem; transition: background .15s; }
        .lu-tray-head { display: flex; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
        .lu-tray-cols { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem 2rem; }
        .lu-tray-col { display: flex; flex-direction: column; align-items: flex-start; gap: .1rem; min-width: 0; }
        .lu-tray-col > .lu-caps { margin-bottom: .3rem; }
        .lu-tray-col .lu-none { padding-left: 0; }
        .lu-tray-item { display: inline-flex; align-items: baseline; max-width: 100%; }
        .lu-tray-item .lu-name { flex-wrap: nowrap; white-space: nowrap; }
        .lu-team-head .lu-caps, .lu-row > .lu-caps { white-space: nowrap; }
        .lu-plus { font-size: .75rem; color: #5c6873; margin-left: -.2rem; }
        .lu-history { margin-top: 2rem; }
        .lu-hist-head { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; padding-bottom: .5rem; border-bottom: 1px solid var(--lu-hair); }
        .lu-hist-row { display: grid; width: 100%; grid-template-columns: 9.5rem 5.5rem minmax(0, 1fr) minmax(0, 1fr) 7rem 7.5rem; align-items: baseline; gap: 1rem; padding: .6rem .5rem; border-bottom: 1px solid #e4e6e8; cursor: pointer; font-size: .88rem; color: #44515e; text-align: left; }
        .lu-hist-row:hover { background: #f5f6f7; }
        .lu-hist-row.is-on { background: var(--lu-hot); }
        .lu-hist-date { font-size: .98rem; color: #0f2233; }
        .lu-hist-side b { font-weight: 500; color: #0f2233; }
        .lu-hist-tag { display: flex; justify-content: flex-end; }
        .lu-tag { font-size: .75rem; font-weight: 500; letter-spacing: .16em; text-transform: uppercase; padding: .12rem .45rem; border: 1px solid var(--lu-a); color: var(--lu-a); white-space: nowrap; }
        .lu-status { margin-top: 1rem; font-size: .75rem; color: #5c6873; min-height: 1em; }
        .lu-status.is-bad { color: #b33a32; }
        .lu-toast { position: fixed; left: 50%; bottom: 1.25rem; transform: translateX(-50%) translateY(1rem); opacity: 0; pointer-events: none; background: #00263e; color: #fff; border-bottom: 2px solid #85754e; padding: .6rem 1rem; display: flex; gap: .9rem; align-items: center; font-size: .88rem; transition: opacity .2s, transform .2s; z-index: 1000; }
        .lu-toast.is-on { opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
        .lu-toast button { border: 1px solid #fff; padding: .15rem .6rem; cursor: pointer; font-size: .75rem; font-weight: 500; letter-spacing: .16em; text-transform: uppercase; }
        .lu-ghost { position: fixed; z-index: 1000; left: 0; top: 0; pointer-events: none; background: #fff; border: 1px solid #e4e6e8; border-left-color: #5c6873; padding: .4rem .8rem; font-size: .88rem; white-space: nowrap; box-shadow: 0 10px 26px rgba(0, 20, 40, .16); }
        .lu-ghost b { font-weight: 500; }
        .lu-ghost small, .lu-ghost-hint { color: #5c6873; }
        .lu-ghost-hint { font-size: .78rem; margin-left: .3rem; }
        .lu-ghost.is-armed { border-left-color: #00263e; }
        .lu-ghost .lu-arrow { color: #6b5c3c; margin: 0 .2rem; }
        .lu-picker { position: fixed; z-index: 1001; width: 340px; max-width: calc(100vw - 24px); background: #fff; border: 1px solid #e4e6e8; border-top: 2px solid #85754e; box-shadow: 0 14px 34px rgba(0, 20, 40, .16); padding: .75rem; display: grid; gap: .5rem; color: #0f2233; }
        .lu-picker.lu-badge-menu { width: 320px; }
        .lu-picker-head { display: flex; justify-content: space-between; align-items: baseline; }
        .lu-picker-q { width: 100%; font-size: .88rem; padding: .45rem .55rem; border: 1px solid #e4e6e8; }
        .lu-picker-list { max-height: 280px; overflow-y: auto; display: grid; }
        .lu-pick { display: grid; grid-template-columns: 3.2rem minmax(0, 1fr) auto; gap: .5rem; align-items: baseline; text-align: left; padding: .38rem .35rem; font-size: .84rem; cursor: pointer; }
        .lu-pick:hover, .lu-pick.is-on, .lu-badge-opt:hover, .lu-badge-opt.is-on { background: #f5f1e6; }
        .lu-pick b { font-weight: 500; color: #6b5c3c; }
        .lu-pick span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lu-pick span small { display: block; font-size: .75rem; color: #5c6873; }
        .lu-pick i { font-style: normal; font-size: .75rem; color: #5c6873; }
        .lu-pick-sec { margin: .35rem 0 .15rem; padding: 0 .35rem; font-size: .75rem; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; color: #5c6873; }
        .lu-badge-opt { display: grid; grid-template-columns: 6.2rem 1fr; align-items: center; gap: .5rem; padding: .4rem .3rem; text-align: left; font-size: .84rem; cursor: pointer; color: #44515e; }
        .lu-badge-opt .lu-badge { margin: 0; justify-self: start; }
        @media (max-width: 980px) {
            .lu-hist-row { grid-template-columns: 1fr auto; }
            .lu-hist-row > .lu-caps, .lu-hist-side, .lu-hist-loose { grid-column: 1 / -1; }
        }
        @media (max-width: 820px) {
            .lu-hand { grid-template-columns: 1fr; }
            .lu-sides { grid-template-columns: 1fr; gap: 2rem; }
            .lu-side, .lu-side:first-child, .lu-side:last-child { padding: 0; border: 0; }
        }
        @media (max-width: 640px) { .lu-tray-cols { grid-template-columns: 1fr; } }
    </style>

    <header class="lu-top">
        <div>
            <h1 class="page-title">{{ __('Team Lineup') }}</h1>
            <p class="lu-sub">
                {{ __('Who goes to whom this week.') }}
                @if($canEdit) {{ __('Drag any name onto another name to put them under that person.') }} @endif
            </p>
        </div>
        <div class="lu-week">
            <button type="button" class="lu-nav" wire:click="previousWeek" aria-label="{{ __('Previous week') }}"><i class="ph ph-caret-left"></i></button>
            <div class="lu-week-label">
                <b>{{ $monday->format('M j') }} – {{ $friday->format('M j') }}</b>
                <span class="lu-caps">{{ __('Week') }} {{ $monday->isoWeek() }} · {{ $friday->format('Y') }}@if($isThisWeek) · {{ __('this week') }}@endif</span>
            </div>
            <button type="button" class="lu-nav" wire:click="nextWeek" aria-label="{{ __('Next week') }}"><i class="ph ph-caret-right"></i></button>
        </div>
        <div class="lu-actions">
            <button type="button" class="lu-link" wire:click="thisWeek" @disabled($isThisWeek)>{{ __('This week') }}</button>
            @if($canEdit)
                @if($confirmErase)
                    <span class="lu-erase-ask">
                        {{ __('Erase this week?') }}
                        <button type="button" class="lu-link lu-erase" wire:click="erase">{{ __('Yes, erase') }}</button>
                        <button type="button" class="lu-link" wire:click="$set('confirmErase', false)">{{ __('Cancel') }}</button>
                    </span>
                @else
                    <button type="button" class="lu-link lu-erase" wire:click="$set('confirmErase', true)" title="{{ __('Reset this week to the defaults') }}">{{ __('Erase') }}</button>
                @endif
                <button type="button" class="lu-link" wire:click="copyLastWeek">{{ __('Copy last week') }}</button>
            @endif
        </div>
    </header>

    @if(! $tree->people)
        <div class="card mt-6 text-sm text-muted">{{ __('No team members yet. Add people in the roster.') }}</div>
    @else
        @if($asking->isEmpty() && $available->isEmpty())
            <section class="lu-hand is-quiet">
                <span class="lu-caps">{{ __('Needs a hand') }}</span>
                <span class="lu-none">{{ __('No deadlines or help requests flagged this week.') }} @if($canEdit) {{ __('Hover a name and click the flag to add one.') }} @endif</span>
            </section>
        @else
            <section class="lu-hand">
                <div class="lu-hand-col">
                    <span class="lu-caps">{{ __('Needs a hand') }} · {{ $asking->count() }}</span>
                    @forelse($asking as $name => $badge)
                        @php
                            $tag = $tags[$name] ?? null;
                            $project = $tag ? ($projects[$tag] ?? null) : null;
                            $up = $tree->parentOf($name);
                        @endphp
                        <div class="lu-hand-row lu-hand-{{ $badge['type'] }}" wire:key="lu-ask-{{ $name }}">
                            <span class="lu-dot"></span>
                            <b>{{ $tree->people[$name]->displayName() }}</b>
                            <span class="lu-hand-what">{{ LineupTree::Badges[$badge['type']]['label'] }}@isset($badge['note']) · {{ $badge['note'] }}@endisset</span>
                            <span class="lu-hand-meta">
                                @if($project){{ $project['number'] }} {{ $project['title'] }}@endif{{ $project && $up ? ' · ' : '' }}@if($up){{ __('with :first', ['first' => $tree->people[$up]->firstName()]) }}@endif
                            </span>
                        </div>
                    @empty
                        <span class="lu-none">{{ __('No one has asked this week.') }}</span>
                    @endforelse
                </div>
                <div class="lu-hand-col">
                    <span class="lu-caps">{{ __('Available to help') }} · {{ $available->count() }}</span>
                    @if($available->isNotEmpty())
                        <div class="lu-hand-free">
                            @foreach($available as $name => $badge)
                                <span wire:key="lu-free-{{ $name }}"><span class="lu-dot lu-dot-available"></span>{{ $tree->people[$name]->displayName() }}@isset($badge['note']) <small>{{ $badge['note'] }}</small>@endisset</span>
                            @endforeach
                        </div>
                    @else
                        <span class="lu-none">{{ __('No one has marked themselves available yet.') }}</span>
                    @endif
                </div>
            </section>
        @endif

        <div class="lu-sides">
            @foreach($partners as $index => $partner)
                @php
                    $teams = $tree->teamsUnder($partner);
                    $direct = $tree->directUnder($partner);
                @endphp
                <section class="lu-side {{ $index === 0 ? 'lu-a' : 'lu-b' }}" data-drop="under:{{ $partner }}" wire:key="lu-side-{{ $partner }}">
                    <div class="lu-phead">
                        <h2 data-pid="{{ $partner }}">{{ $tree->people[$partner]->displayName() }}</h2>
                        <div class="lu-caps">{{ __('Partner') }} · {{ $people($tree->countUnder($partner)) }}</div>
                        @if($direct)
                            <div class="lu-direct">
                                <span class="lu-caps">{{ __('Direct') }}</span>
                                @foreach($direct as $name)
                                    @include('livewire.team.lineup-who', [...$shared, 'name' => $name])
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="lu-teams">
                        @forelse($teams as $lead)
                            @php $children = $tree->childrenOf($lead); @endphp
                            <div class="lu-team" data-drop="under:{{ $lead }}" wire:key="lu-team-{{ $lead }}">
                                <div class="lu-team-head">
                                    @include('livewire.team.lineup-who', [...$shared, 'name' => $lead, 'extra' => 'lu-lead'])
                                    <span class="lu-caps">{{ $people($tree->countUnder($lead)) }}</span>
                                </div>
                                <ul class="lu-people">
                                    @forelse($children as $child)
                                        @include('livewire.team.lineup-branch', [...$shared, 'name' => $child])
                                    @empty
                                        <li class="lu-none">{{ __('No one yet.') }} @if($canEdit) {{ __('Drag a name here.') }} @endif</li>
                                    @endforelse
                                </ul>
                            </div>
                        @empty
                            <div class="lu-empty">
                                @if($canEdit)
                                    {{ __('Drag someone here to put them under :first.', ['first' => $tree->people[$partner]->firstName()]) }}
                                @else
                                    {{ __('No one under :first this week.', ['first' => $tree->people[$partner]->firstName()]) }}
                                @endif
                            </div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        <section class="lu-tray" data-drop="tray">
            <div class="lu-tray-head">
                <span class="lu-caps">{{ $loose ? __('Not placed this week') : __('Everyone is placed') }}</span>
                <span class="lu-caps">{{ __(':placed of :total placed', ['placed' => $tree->placedCount(), 'total' => $total]) }}</span>
            </div>
            @if($loose)
                <div class="lu-tray-cols">
                    @foreach(['pm' => __('PMs'), 'tech' => __('Technologists'), 'other' => __('Other')] as $group => $label)
                        @php $column = array_values(array_filter($loose, fn (string $name) => $tree->people[$name]->group() === $group)); @endphp
                        <div class="lu-tray-col" wire:key="lu-tray-{{ $group }}">
                            <span class="lu-caps">{{ $label }} · {{ count($column) }}</span>
                            @forelse($column as $name)
                                <span class="lu-tray-item" wire:key="lu-tray-item-{{ $name }}">
                                    @include('livewire.team.lineup-who', [...$shared, 'name' => $name])
                                    @if($count = $tree->countUnder($name)) <span class="lu-plus">+{{ $count }}</span> @endif
                                </span>
                            @empty
                                <span class="lu-none">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="lu-history">
            <div class="lu-hist-head"><span class="lu-caps">{{ __('Saved weeks') }}@if($this->history->isNotEmpty()) · {{ $this->history->count() }}@endif</span></div>
            @forelse($this->history as $entry)
                @php $start = CarbonImmutable::parse($entry['week']); @endphp
                <button type="button" @class(['lu-hist-row', 'is-on' => $entry['week'] === $week]) wire:click="goToWeek('{{ $entry['week'] }}')" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })" wire:key="lu-hist-{{ $entry['week'] }}">
                    <span class="lu-hist-date">{{ $start->format('M j') }} – {{ $start->addDays(4)->format('M j') }}</span>
                    <span class="lu-caps">{{ __('Week') }} {{ $start->isoWeek() }}</span>
                    @foreach($entry['summary']['sides'] as $side)
                        <span class="lu-hist-side"><b>{{ $side['first'] }}</b> {{ trans_choice(':count team|:count teams', $side['teams'], ['count' => $side['teams']]) }} · {{ $people($side['people']) }}</span>
                    @endforeach
                    <span class="lu-hist-loose">{{ $entry['summary']['loose'] ? __(':count not placed', ['count' => $entry['summary']['loose']]) : __('All placed') }}</span>
                    <span class="lu-hist-tag">@if($entry['week'] === $week)<span class="lu-tag">{{ __('Viewing') }}</span>@endif</span>
                </button>
            @empty
                <p class="lu-none">{{ __('No weeks saved yet. A week is saved the first time someone moves a name.') }}</p>
            @endforelse
        </section>
    @endif

    <p @class(['lu-status', 'is-bad' => $statusIsError]) role="status">{{ $status }}</p>

    @can('edit team')
        <details class="card mt-10 p-0" wire:ignore.self>
            <summary class="flex cursor-pointer items-center justify-between gap-4 px-6 py-4">
                <span>
                    <span class="section-title">{{ __('Roster') }}</span>
                    <span class="ml-2 text-xs text-muted">{{ __('Who appears on the lineup and has a PM Desk. :count people.', ['count' => $members->where('active', true)->count()]) }}</span>
                </span>
                <button type="button" class="btn-secondary" wire:click.prevent="addPerson"><i class="ph ph-plus"></i>{{ __('Add person') }}</button>
            </summary>
            <div class="overflow-x-auto border-t border-line">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Full name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Tier') }}</th>
                            <th>{{ __('Group') }}</th>
                            <th>{{ __('Default boss') }}</th>
                            <th>{{ __('Active') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                            <tr wire:key="lu-member-{{ $member->id }}" @class(['is-link', 'is-muted' => ! $member->active]) wire:click="editPerson({{ $member->id }})" tabindex="0" wire:keydown.enter="editPerson({{ $member->id }})">
                                <td class="font-medium">
                                    {{ $member->name }}
                                    @unless($staffNames->has($member->name)) <span class="chip is-warn">{{ __('not in Reference sheet') }}</span> @endunless
                                </td>
                                <td>{{ $member->full_name ?? '—' }}</td>
                                <td>{{ $member->email ?? '—' }}</td>
                                <td>{{ ['partner' => __('Partner'), 'pm' => __('PM'), 'staff' => __('Staff')][$member->tier] ?? $member->tier }}</td>
                                <td>{{ $member->tier === 'staff' ? (['tech' => __('Technologist'), 'other' => __('Other')][$member->grp ?? 'other'] ?? $member->grp) : '—' }}</td>
                                <td>{{ $member->default_boss ?? '—' }}</td>
                                <td>{{ $member->active ? __('Yes') : __('Left') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($orphanAssignments->isNotEmpty())
                <p class="notice m-4">
                    {{ __('Assignments to projects no longer in the workbook (ignored):') }}
                    {{ $orphanAssignments->map(fn ($assignment) => $assignment->job_number.' · '.$assignment->member_name)->implode(', ') }}
                </p>
            @endif
        </details>
    @endcan

    @if($editorOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy/30 p-4 sm:p-10" wire:click.self="$set('editorOpen', false)" x-on:keydown.escape.window="$wire.set('editorOpen', false)">
            <form wire:submit="savePerson" class="card w-full max-w-[640px] border-t-2 border-t-gold">
                <h2 class="section-title text-xl">{{ $editingId ? $form['name'] : __('Add person') }}</h2>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="tm-name">{{ __('Name') }}</label>
                        @if($editingId)
                            <input id="tm-name" value="{{ $form['name'] }}" class="field-input bg-hover" disabled>
                            <p class="mt-1 text-xs text-muted">{{ __('The name every week and assignment uses; it cannot change. Untick Active when someone leaves.') }}</p>
                        @else
                            <input id="tm-name" wire:model="form.name" class="field-input" list="tm-candidates" placeholder="{{ __('As in the Reference sheet') }}">
                            <datalist id="tm-candidates">
                                @foreach($candidates as $candidate) <option value="{{ $candidate }}"> @endforeach
                            </datalist>
                        @endif
                        @error('form.name') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="tm-full">{{ __('Full name') }}</label>
                        <input id="tm-full" wire:model="form.full_name" class="field-input">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label" for="tm-email">{{ __('Email') }}</label>
                        <input id="tm-email" type="email" wire:model="form.email" class="field-input" placeholder="name@formwerks.ca">
                        <p class="mt-1 text-xs text-muted">{{ __('Their Microsoft sign-in, so PM Desk opens on their own desk.') }}</p>
                        @error('form.email') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="tm-tier">{{ __('Tier') }}</label>
                        <select id="tm-tier" wire:model.live="form.tier" class="field-input">
                            <option value="partner">{{ __('Partner') }}</option>
                            <option value="pm">{{ __('PM') }}</option>
                            <option value="staff">{{ __('Staff') }}</option>
                        </select>
                        @error('form.tier') <p class="error-note mt-1">{{ $message }}</p> @enderror
                    </div>
                    @if(($form['tier'] ?? '') === 'staff')
                        <div>
                            <label class="field-label" for="tm-grp">{{ __('Group') }}</label>
                            <select id="tm-grp" wire:model="form.grp" class="field-input">
                                <option value="tech">{{ __('Technologist') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                            @error('form.grp') <p class="error-note mt-1">{{ $message }}</p> @enderror
                        </div>
                    @elseif(($form['tier'] ?? '') === 'pm')
                        <div>
                            <label class="field-label" for="tm-boss">{{ __('Default boss') }}</label>
                            <select id="tm-boss" wire:model="form.default_boss" class="field-input">
                                <option value="">{{ __('None: not placed until moved') }}</option>
                                @foreach($members->where('tier', 'partner')->where('active', true) as $partner)
                                    <option value="{{ $partner->name }}">{{ $partner->displayName() }}</option>
                                @endforeach
                            </select>
                            @error('form.default_boss') <p class="error-note mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm text-ink-2">
                    <input type="checkbox" wire:model="form.active" class="accent-navy"> {{ __('Active — untick when someone leaves; past weeks keep them') }}
                </label>

                <div class="mt-8 flex items-center justify-between gap-2">
                    <div>
                        @if($editingId)
                            <button type="button" class="btn-danger text-sm" wire:click="deletePerson" wire:confirm="{{ __('Delete :name from the roster for good? To keep them on record, untick Active instead.', ['name' => $form['name']]) }}">{{ __('Delete') }}</button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary" wire:click="$set('editorOpen', false)">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn">{{ $editingId ? __('Save changes') : __('Add person') }}</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    <div class="lu-toast" wire:ignore data-toast role="status"><span></span><button type="button" data-act="undo">{{ __('Undo') }}</button></div>
</div>

@script
<script>
    const root = $wire.$el;
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const badgeTypes = @js(LineupTree::Badges);
    const state = { drag: null, selected: null, hover: null, menu: null, suppressClick: false, toastTimer: null };

    const data = () => JSON.parse(root.dataset.lineup || '{}');
    const person = (id) => data().people?.[id];
    const isPartner = (id) => (data().partners || []).includes(id);
    const parentOf = (id, d = data()) => {
        const boss = d.boss?.[id];

        return boss && d.people?.[boss] ? boss : null;
    };
    const isUnder = (id, ancestor, d = data()) => {
        for (let at = parentOf(id, d), hops = 0; at && hops < 40; at = parentOf(at, d), hops++) {
            if (at === ancestor) {
                return true;
            }
        }

        return false;
    };
    const countUnder = (id, d = data()) => Object.keys(d.people || {})
        .filter((other) => parentOf(other, d) === id)
        .reduce((total, child) => total + 1 + countUnder(child, d), 0);
    const accepts = (id, drop) => {
        const d = data();

        if (!drop || !d.editable || !d.people?.[id] || isPartner(id)) {
            return false;
        }

        if (drop === 'tray') {
            return !!parentOf(id, d);
        }

        const to = drop.slice(6);

        return !!d.people[to] && to !== id && !isUnder(to, id, d) && parentOf(id, d) !== to;
    };
    const targetFrom = (element, id) => {
        for (let at = element; at && at !== document.body; at = at.parentElement) {
            if (at.dataset?.drop) {
                return root.contains(at) && accepts(id, at.dataset.drop) ? at : null;
            }
        }

        return null;
    };
    const dropLabel = (drop) => (drop === 'tray' ? 'Not placed' : `under ${person(drop.slice(6))?.first ?? ''}`);

    const focusChain = () => {
        const focus = state.hover || state.selected;

        root.querySelectorAll('.lu-sides .lu-who').forEach((who) => {
            const id = who.dataset.id;

            who.classList.toggle('lu-dim', !!focus && !(id === focus || isUnder(focus, id) || isUnder(id, focus)));
            who.classList.toggle('is-sel', id === state.selected);
        });
    };

    const toast = (message, canUndo) => {
        const element = root.querySelector('[data-toast]');

        element.querySelector('span').textContent = message;
        element.querySelector('button').hidden = !canUndo;
        element.classList.add('is-on');
        clearTimeout(state.toastTimer);
        state.toastTimer = setTimeout(() => element.classList.remove('is-on'), 7000);
    };

    $wire.on('lineup-toast', ({ message, undo }) => toast(message, undo));

    const closeMenu = () => {
        state.menu?.remove();
        state.menu = null;
    };

    const placeMenu = (menu, anchor, width, height) => {
        const rect = anchor.getBoundingClientRect();

        menu.style.left = `${Math.max(12, Math.min(rect.left, window.innerWidth - width - 12))}px`;
        menu.style.top = `${rect.bottom + 6 + height > window.innerHeight ? Math.max(12, rect.top - height - 6) : rect.bottom + 6}px`;
    };

    const openPicker = async (id, anchor) => {
        closeMenu();

        const menu = document.createElement('div');
        menu.className = 'lu-picker';
        state.menu = menu;
        menu.innerHTML = `<div class="lu-picker-head"><span class="lu-caps">Project for ${esc(person(id)?.first)}</span>
            ${anchor.classList.contains('lu-ptag') ? '<button type="button" class="lu-link" data-pick="">Remove tag</button>' : ''}</div>
            <input type="search" class="lu-picker-q" placeholder="Search by number or name" aria-label="Search projects">
            <div class="lu-picker-list"><p class="lu-none">Loading…</p></div>`;
        document.body.append(menu);
        placeMenu(menu, anchor, 340, 360);

        const list = menu.querySelector('.lu-picker-list');
        const search = menu.querySelector('.lu-picker-q');
        search.focus();

        const choices = await $wire.projectChoices(id);

        if (state.menu !== menu) {
            return;
        }

        const byNumber = Object.fromEntries(choices.all.map((project) => [project.number, project]));
        const mine = choices.mine.filter((row) => byNumber[row.number]);
        const mineNumbers = new Set(mine.map((row) => row.number));
        const row = (project, note) => `<button type="button" class="lu-pick" data-pick="${esc(project.number)}">
            <b>${esc(project.number)}</b><span>${esc(project.title)}${note ? `<small>${esc(note)}</small>` : ''}</span>
            <i>${esc(project.division ?? '')}${project.active ? '' : ' · closed'}</i></button>`;

        const draw = () => {
            const term = search.value.trim().toLowerCase();
            const hit = (project) => !term || `${project.number} ${project.title} ${project.division ?? ''}`.toLowerCase().includes(term);
            const own = mine.filter((entry) => hit(byNumber[entry.number]));
            const rest = choices.all.filter((project) => !mineNumbers.has(project.number) && (term ? hit(project) : project.active)).slice(0, 80);
            let html = '';

            if (own.length) {
                html += `<p class="lu-pick-sec">${esc(person(id)?.first)}'s projects</p>${own.map((entry) => row(byNumber[entry.number], entry.note)).join('')}`;
            }

            if (rest.length) {
                html += `<p class="lu-pick-sec">${term ? 'Other matches' : 'All active projects'}</p>${rest.map((project) => row(project)).join('')}`;
            }

            list.innerHTML = html || '<p class="lu-none">No project on the list matches. Tags can only use projects from the Projects page.</p>';
        };

        draw();
        search.addEventListener('input', draw);
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }

            if (event.key === 'Enter') {
                const first = list.querySelector('[data-pick]');

                if (first) {
                    closeMenu();
                    $wire.setTag(id, first.dataset.pick);
                }
            }
        });
        menu.addEventListener('click', (event) => {
            const pick = event.target.closest('[data-pick]');

            if (pick) {
                closeMenu();
                $wire.setTag(id, pick.dataset.pick || null);
            }
        });
    };

    const openBadgeMenu = (id, anchor) => {
        closeMenu();

        const current = anchor.classList.contains('lu-badge') ? [...anchor.classList].find((c) => c.startsWith('lu-badge-'))?.slice(9) : null;
        const currentNote = anchor.querySelector('small')?.textContent ?? '';
        const menu = document.createElement('div');
        menu.className = 'lu-picker lu-badge-menu';
        state.menu = menu;
        menu.innerHTML = `<div class="lu-picker-head"><span class="lu-caps">Badge for ${esc(person(id)?.first)}</span>
            ${current ? '<button type="button" class="lu-link" data-badge="">Remove badge</button>' : ''}</div>
            ${Object.entries(badgeTypes).map(([type, badge]) => `<button type="button" class="lu-badge-opt${current === type ? ' is-on' : ''}" data-badge="${type}">
                <span class="lu-badge lu-badge-${type}">${esc(badge.label)}</span><span>${esc(badge.hint)}</span></button>`).join('')}
            <input type="text" class="lu-picker-q" maxlength="80" placeholder="Note, e.g. DP resubmission Thu" aria-label="Badge note" value="${esc(currentNote)}">`;
        document.body.append(menu);
        placeMenu(menu, anchor, 320, 230);

        const note = menu.querySelector('input');

        menu.addEventListener('click', (event) => {
            const option = event.target.closest('[data-badge]');

            if (!option) {
                return;
            }

            closeMenu();
            $wire.setBadge(id, option.dataset.badge || null, option.dataset.badge ? note.value.trim() : '');
        });
        note.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }

            if (event.key === 'Enter' && current) {
                closeMenu();
                $wire.setBadge(id, current, note.value.trim());
            }
        });
    };

    const showTarget = (target) => {
        root.querySelectorAll('.lu-hot, .lu-hot-name').forEach((element) => element.classList.remove('lu-hot', 'lu-hot-name'));

        if (target) {
            target.classList.add('lu-hot');

            if (target.dataset.drop.startsWith('under:')) {
                const to = CSS.escape(target.dataset.drop.slice(6));

                root.querySelectorAll(`.lu-who[data-id="${to}"], .lu-phead h2[data-pid="${to}"]`).forEach((element) => element.classList.add('lu-hot-name'));
            }
        }

        const drag = state.drag;

        if (drag?.ghost) {
            drag.ghost.innerHTML = `<b>${esc(person(drag.id)?.name)}</b>${drag.count ? ` <small>+${drag.count}</small>` : ''}`
                + (target ? ` <span class="lu-arrow">→</span> ${esc(dropLabel(target.dataset.drop))}` : ' <span class="lu-ghost-hint">drop on a name</span>');
            drag.ghost.classList.toggle('is-armed', !!target);
        }
    };

    const stopDragging = () => {
        root.classList.remove('lu-dragging');
        root.querySelectorAll('.lu-nodrop').forEach((element) => element.classList.remove('lu-nodrop'));
        showTarget(null);
    };

    const onPointerDown = (event) => {
        if (!root.isConnected) {
            return cleanup();
        }

        if (state.menu && !state.menu.contains(event.target) && !event.target.closest('[data-act="tag"], [data-act="badge"]')) {
            closeMenu();
        }

        if (event.button > 0 || !data().editable) {
            return;
        }

        const who = event.target.closest('.lu-who[data-id]');

        if (!who || !root.contains(who) || isPartner(who.dataset.id)) {
            return;
        }

        state.drag = { id: who.dataset.id, x: event.clientX, y: event.clientY, on: false, count: countUnder(who.dataset.id) };
    };

    const onPointerMove = (event) => {
        if (!root.isConnected) {
            return cleanup();
        }

        const drag = state.drag;

        if (!drag) {
            if (event.pointerType === 'mouse') {
                const who = event.target.closest?.('.lu-who[data-id]');
                const id = who && root.contains(who) ? who.dataset.id : null;

                if (id !== state.hover) {
                    state.hover = id;
                    focusChain();
                }
            }

            return;
        }

        if (!drag.on) {
            if (Math.hypot(event.clientX - drag.x, event.clientY - drag.y) < 6) {
                return;
            }

            drag.on = true;
            state.hover = null;
            state.selected = null;
            focusChain();
            drag.ghost = document.createElement('div');
            drag.ghost.className = 'lu-ghost';
            document.body.append(drag.ghost);
            root.classList.add('lu-dragging');
            root.querySelectorAll('.lu-who[data-id]').forEach((who) => {
                if (who.dataset.id === drag.id || isUnder(who.dataset.id, drag.id)) {
                    who.classList.add('lu-nodrop');
                }
            });
            showTarget(null);
        }

        event.preventDefault();
        drag.ghost.style.left = `${event.clientX + 16}px`;
        drag.ghost.style.top = `${event.clientY - 46}px`;

        const target = targetFrom(document.elementFromPoint(event.clientX, event.clientY), drag.id);

        if (target !== drag.target) {
            drag.target = target;
            showTarget(target);
        }
    };

    const endDrag = (event, cancelled) => {
        const drag = state.drag;
        state.drag = null;

        if (!drag?.on) {
            return;
        }

        drag.ghost.remove();
        stopDragging();
        state.suppressClick = true;
        setTimeout(() => {
            state.suppressClick = false;
        }, 0);

        if (cancelled) {
            return;
        }

        const target = targetFrom(document.elementFromPoint(event.clientX, event.clientY), drag.id);

        if (target) {
            $wire.move(drag.id, target.dataset.drop);
        }
    };
    const onPointerUp = (event) => endDrag(event, false);
    const onPointerCancel = (event) => endDrag(event, true);

    const onKeyDown = (event) => {
        if (event.key === 'Escape' && state.selected) {
            state.selected = null;
            focusChain();
        }
    };

    root.addEventListener('click', (event) => {
        if (state.suppressClick) {
            return;
        }

        const action = event.target.closest('[data-act]');

        if (action?.dataset.act === 'undo') {
            root.querySelector('[data-toast]').classList.remove('is-on');

            return $wire.undoLast();
        }

        if (action?.dataset.act === 'badge' || action?.dataset.act === 'tag') {
            if (state.menu) {
                return closeMenu();
            }

            return action.dataset.act === 'badge' ? openBadgeMenu(action.dataset.id, action) : openPicker(action.dataset.id, action);
        }

        if (!data().editable) {
            return;
        }

        const who = event.target.closest('.lu-who[data-id]');

        if (state.selected) {
            const target = targetFrom(event.target, state.selected);

            if (target && !(who && who.dataset.id === state.selected)) {
                const id = state.selected;
                state.selected = null;
                focusChain();

                return $wire.move(id, target.dataset.drop);
            }
        }

        if (who && !isPartner(who.dataset.id)) {
            state.selected = state.selected === who.dataset.id ? null : who.dataset.id;
        } else if (state.selected) {
            state.selected = null;
        }

        focusChain();
    });

    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('pointermove', onPointerMove, { passive: false });
    document.addEventListener('pointerup', onPointerUp);
    document.addEventListener('pointercancel', onPointerCancel);
    document.addEventListener('keydown', onKeyDown);

    // Re-read every 30 seconds so two people on the page see each other's
    // changes; never mid-drag, with a name selected or a menu open.
    const poll = setInterval(() => {
        if (!root.isConnected) {
            return cleanup();
        }

        if (document.visibilityState === 'visible' && !state.drag && !state.selected && !state.menu) {
            $wire.$refresh();
        }
    }, 30000);

    function cleanup() {
        clearInterval(poll);
        closeMenu();
        state.drag?.ghost?.remove();
        document.removeEventListener('pointerdown', onPointerDown);
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        document.removeEventListener('pointercancel', onPointerCancel);
        document.removeEventListener('keydown', onKeyDown);
    }

    document.addEventListener('livewire:navigating', cleanup, { once: true });
</script>
@endscript
