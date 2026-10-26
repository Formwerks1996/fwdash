@php
    use App\Livewire\BusinessDevelopment\OutreachShow;

    $p = $prospect;
    $a = $p->activity;
    $person = $p->person;
    $tagLabels = ['In deck' => __('In regional deck'), 'Check first' => __('Possible client'), 'Warm lead' => __('Worked with us')];
    $short = fn (string $url) => preg_replace('#^https?://(www\.)?#', '', $url);
    $link = function (?string $url, ?string $label = null) use ($short) {
        $safe = OutreachShow::safeUrl($url);

        return $safe
            ? '<a href="'.e($safe).'" target="_blank" rel="noopener" class="text-navy underline">'.e($label ?? $short($safe)).'</a>'
            : e($label ?? $url ?? '—');
    };
    $copy = fn (string $value) => '<button type="button" class="ml-1 text-muted hover:text-navy" title="'.e(__('Copy')).'" x-on:click="navigator.clipboard.writeText('.e(json_encode($value)).')"><i class="ph ph-copy"></i></button>';
@endphp

<div class="space-y-6" x-data>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('outreach.index') }}" class="btn-secondary" wire:navigate><i class="ph ph-arrow-left"></i>{{ __('Outreach list') }}</a>
        @if($position !== null)
            <div class="flex items-center gap-2 text-xs text-muted">
                {{ $position + 1 }} {{ __('of') }} {{ count($keys) }}
                <a @class(['btn-secondary', 'pointer-events-none opacity-40' => ! $previous]) href="{{ $previous ? route('outreach.show', $previous) : '#' }}" wire:navigate>{{ __('Previous') }}</a>
                <a @class(['btn-secondary', 'pointer-events-none opacity-40' => ! $next]) href="{{ $next ? route('outreach.show', $next) : '#' }}" wire:navigate>{{ __('Next') }}</a>
            </div>
        @endif
    </div>

    <div @class(['relative overflow-hidden', 'h-60' => $p->img])>
        @if($p->img)
            <img src="{{ route('outreach.images', $p->img) }}" alt="" class="absolute inset-0 size-full object-cover brightness-75">
            <div class="absolute inset-0 bg-linear-to-t from-black/70 to-transparent"></div>
        @endif
        <div @class(['relative', 'absolute bottom-0 p-6 text-white' => $p->img])>
            <h1 @class(['page-title', '!text-white' => $p->img])>{{ $p->name }}</h1>
            <p @class(['mt-1 text-sm', 'text-white/80' => $p->img, 'text-muted' => ! $p->img])>{{ $p->region }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="chip">{{ __('Tier :tier', ['tier' => $p->tier]) }}</span>
                <span class="chip">{{ __('fit :score/5', ['score' => $p->score]) }}</span>
                @foreach($p->types as $type)
                    <span class="chip border-l-2" style="border-color: {{ $typeColors[$type] ?? '#555b63' }}">{{ $type }}</span>
                @endforeach
                @foreach($p->tags as $tag)
                    @isset($tagLabels[$tag]) <span class="chip is-warn">{{ $tagLabels[$tag] }}</span> @endisset
                @endforeach
            </div>
        </div>
    </div>

    @if($p->flag || $p->client_note)
        <div class="notice">
            <p class="font-medium">{{ $p->flag ?: $p->client_note }}</p>
            @if($p->flag && $p->client_note) <p class="mt-1">{{ $p->client_note }}</p> @endif
        </div>
    @endif

    <div class="card">
        <div class="flex items-center gap-3">
            <h2 class="section-title">{{ __('Activity') }}</h2>
            <x-outreach.activity-pill :prospect="$p" />
        </div>
        <p class="section-note">{{ ($a['checked'] ?? null) ? __('Checked :date. Based on public listings, not a guarantee.', ['date' => $a['checked']]) : __('Not researched yet.') }}</p>
        <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[10rem_1fr]">
            <dt class="text-muted">{{ __('Latest project') }}</dt><dd>{{ $a['project'] ?? '—' }}@if($a['stage'] ?? null) ({{ $a['stage'] }})@endif</dd>
            <dt class="text-muted">{{ __('Last milestone') }}</dt><dd>{{ $a['milestone'] ?? '—' }}</dd>
            <dt class="text-muted">{{ __('Website') }}</dt><dd>{{ ['live' => __('live'), 'down' => __('down')][$a['site'] ?? ''] ?? __('not checked') }}</dd>
            <dt class="text-muted">{{ __('Notes') }}</dt><dd>{{ $a['notes'] ?? '—' }}</dd>
            <dt class="text-muted">{{ __('Evidence') }}</dt><dd>{!! $link($a['evidence'] ?? null) !!}</dd>
        </dl>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <h2 class="section-title">{{ __('Who to contact') }}</h2>
            <div class="mt-4 space-y-2 text-sm">
                @if($person)
                    <p class="font-medium">
                        {{ $person['name'] }}
                        @if(($person['confidence'] ?? '') === 'low') <span class="chip is-warn">{{ __('unverified — confirm first') }}</span> @endif
                    </p>
                    @if($person['title'] ?? null) <p class="text-muted">{{ $person['title'] }}</p> @endif
                    @if($person['email'] ?? null) <p><a href="mailto:{{ $person['email'] }}" class="underline">{{ $person['email'] }}</a>{!! $copy($person['email']) !!}</p> @endif
                    @if($person['linkedin'] ?? null) <p>{!! $link($person['linkedin'], 'LinkedIn') !!}</p> @endif
                    @if($person['source'] ?? null) <p class="text-xs">{!! $link($person['source'], __('where this came from')) !!}</p> @endif
                    @if($person['pattern'] ?? null) <p class="text-xs text-muted">{{ __('Email pattern, not confirmed:') }} {{ preg_replace('/\s*\(evidence:.*\)$/', '', $person['pattern']) }}</p> @endif
                @else
                    <p class="text-muted">{{ __('No named person found yet. Use the office contact below.') }}</p>
                @endif

                <dl class="mt-4 grid gap-x-4 gap-y-2 border-t border-line pt-4 sm:grid-cols-[8rem_1fr]">
                    @if($p->others) <dt class="text-muted">{{ __('Other people') }}</dt><dd>{{ $p->others }}</dd> @endif
                    @if($p->email) <dt class="text-muted">{{ __('Office email') }}</dt><dd><a href="mailto:{{ $p->email }}" class="underline">{{ $p->email }}</a>{!! $copy($p->email) !!}</dd> @endif
                    @if($p->phone) <dt class="text-muted">{{ __('Phone') }}</dt><dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $p->phone) }}" class="underline">{{ $p->phone }}</a></dd> @endif
                    @if($p->contact_page) <dt class="text-muted">{{ __('Contact page') }}</dt><dd>{!! $link($p->contact_page) !!}</dd> @endif
                    @if($p->website)
                        <dt class="text-muted">{{ __('Website') }}</dt>
                        <dd>{!! $link($p->website) !!} @unless(in_array($p->webcheck, ['ok', 'fixed'], true)) <span class="chip is-warn">{{ $p->webcheck ?: __('not checked') }}</span> @endunless</dd>
                    @endif
                </dl>

                @if($p->contact_notes || $p->route)
                    <details class="mt-3 text-xs text-ink-2">
                        <summary class="cursor-pointer">{{ __('Contact research notes') }}</summary>
                        <p class="mt-2 whitespace-pre-line">{{ $p->route }}</p>
                        <p class="mt-2 whitespace-pre-line">{{ $p->contact_notes }}</p>
                    </details>
                @endif
            </div>
        </div>

        <div class="card">
            <h2 class="section-title">{{ __('The company') }}</h2>
            @if($p->why) <p class="mt-3 text-sm">{{ $p->why }}</p> @endif
            <dl class="mt-4 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[9rem_1fr]">
                <dt class="text-muted">{{ __('Head office') }}</dt><dd>{{ $p->hq ?? '—' }}</dd>
                <dt class="text-muted">{{ __('Builds') }}</dt><dd>{{ implode(', ', $p->types) }}@if($p->product) — {{ $p->product }}@endif</dd>
                <dt class="text-muted">{{ __('Typical size') }}</dt><dd>{{ $p->size ?? '—' }}</dd>
                <dt class="text-muted">{{ __('Style') }}</dt><dd>{{ $p->style ?? '—' }}</dd>
                <dt class="text-muted">{{ __('Architect they use') }}</dt><dd>{{ $p->architect ?? '—' }}</dd>
                <dt class="text-muted">{{ __('Phased land') }}</dt><dd>{{ $p->phased ?? '—' }}</dd>
                <dt class="text-muted">{{ __('Research confidence') }}</dt><dd>{{ $p->confidence ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        @if($p->projects->isNotEmpty())
            <h2 class="section-title">{{ __('Projects (:count)', ['count' => $p->projects->count()]) }}</h2>
            <p class="section-note">{{ __('From the regional deck and the project map. Images are each project page\'s own share image.') }}</p>
            <div class="mt-4 grid gap-px border border-[#e7e9ea] bg-[#e7e9ea] sm:grid-cols-2 xl:grid-cols-3">
                @foreach($p->projects as $project)
                    @php
                        $units = $project->units && preg_match('/^[\d\s–-]+$/u', $project->units) ? "{$project->units} ".__('units') : $project->units;
                        $map = $project->lat !== null
                            ? 'https://www.google.com/maps/search/?api=1&query='.($project->exact ? "{$project->lat},{$project->lon}" : urlencode("{$project->name} {$project->where_text}"))
                            : null;
                    @endphp
                    <div class="bg-white">
                        @if($project->img)
                            <img src="{{ route('outreach.images', $project->img) }}" alt="" loading="lazy" class="h-32 w-full object-cover">
                        @else
                            <div class="flex h-32 items-center justify-center bg-rule text-2xl text-muted"><i class="ph ph-buildings"></i></div>
                        @endif
                        <div class="space-y-1 p-3 text-sm">
                            <p class="font-medium">{!! $link($project->url, $project->name ?? '—') !!} @if($project->hero) <span class="chip is-on">{{ __('flagship') }}</span> @endif</p>
                            <p class="text-xs text-muted">{{ collect([$project->where_text, $units])->filter()->implode(' · ') }}</p>
                            @if($project->away) <p class="text-xs text-muted">{{ $project->away }}</p> @endif
                            @if($map) <p class="text-xs"><a href="{{ $map }}" target="_blank" rel="noopener" class="underline">{{ __('Map') }}</a></p> @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <h2 class="section-title">{{ __('Recent projects') }}</h2>
            <p class="section-note">{{ __('As listed in the research. This developer isn\'t in a regional deck, so projects aren\'t individually linked or mapped.') }}</p>
            @if($p->recent)
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                    @foreach($p->recent as $recent) <li>{{ $recent }}</li> @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-muted">{{ __('None recorded.') }}</p>
            @endif
        @endif
    </div>

    @if($p->sources)
        <div class="card">
            <h2 class="section-title">{{ __('Where the research found them') }} @if($p->passes) <span class="text-sm text-muted">({{ $p->passes }})</span> @endif</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach($p->sources as $source) <li>{!! $link($source) !!}</li> @endforeach
            </ul>
        </div>
    @endif
</div>
