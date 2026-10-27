@php
    $changelog = config('changelog');
    $latest = $changelog[0] ?? null;
    $sections = [
        'start' => __('Signing in'),
        'around' => __('Finding your way'),
        'pages' => __('What each page is for'),
        'figures' => __('Getting your figures in'),
        'data' => __('Where the numbers come from'),
        'trouble' => __('When something goes wrong'),
        'versions' => __('Version history'),
    ];
    $lastImport = \App\Models\WorkbookImport::latest()->first();
@endphp

<x-layouts::app :title="__('Help')">
    <div>
        <h1 class="page-title">{{ __('Help') }}</h1>
        <p class="page-subtitle">{{ __('What each page is for, how to get your figures in, and what to do when something goes wrong.') }}</p>
        @if($latest)
            <p class="mt-1 text-xs text-muted">{{ __('Version :version', ['version' => $latest['version']]) }} · {{ \Carbon\CarbonImmutable::parse($latest['date'])->format('F j, Y') }}</p>
        @endif
    </div>

    <div class="mt-10 grid gap-10 lg:grid-cols-[13rem_1fr]">
        <nav class="top-8 self-start border-l border-gold-line pl-4 text-sm lg:sticky" aria-label="{{ __('On this page') }}">
            <p class="nav-label !mt-0">{{ __('On this page') }}</p>
            <ul class="mt-2 space-y-1.5">
                @foreach($sections as $id => $title)
                    <li><a href="#{{ $id }}" class="text-ink-2 hover:text-navy">{{ $title }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="max-w-[70ch] space-y-12 text-[0.95rem] leading-relaxed text-ink">
            <section id="start">
                <h2 class="font-heading text-2xl text-navy">{{ $sections['start'] }}</h2>
                <ol class="mt-3 list-decimal space-y-1.5 pl-5">
                    <li>{{ __('Open') }} <a href="{{ config('app.url') }}" class="text-navy underline">{{ config('app.url') }}</a> {{ __('in any browser and bookmark it.') }}</li>
                    <li>{{ __('Choose Sign in with Microsoft and use your Formwerks account.') }}</li>
                    <li>{{ __('Your role decides which pages and buttons you see. New people start as Staff; ask an admin if you need more.') }}</li>
                </ol>
                <p class="note mt-4"><b>{{ __('Changes save as you go.') }}</b> {{ __('A cell saves when you leave it or press Enter. There is no Save button on the tables.') }}</p>
            </section>

            <section id="around">
                <h2 class="font-heading text-2xl text-navy">{{ $sections['around'] }}</h2>
                <dl class="mt-3 space-y-3">
                    <div><dt class="font-medium">{{ __('Jump to anything') }}</dt><dd class="text-ink-2">{{ __('Press ⌘K (or /) and type a page name, a job number, a project or a developer. Arrow keys to choose, Enter to open.') }}</dd></div>
                    <div><dt class="font-medium">{{ __('Links that stay put') }}</dt><dd class="text-ink-2">{{ __('Every page, filter and project has its own address. Copy it and send it; it opens the same page for whoever you send it to.') }}</dd></div>
                    <div><dt class="font-medium">{{ __('Sticky notes') }}</dt><dd class="text-ink-2">{{ __('Notes left on a page show there; drag one by its top bar. The note icon at the bottom right lists every note. Notes are shared with everyone who signs in.') }}</dd></div>
                    <div><dt class="font-medium">{{ __('New Pursuit') }}</dt><dd class="text-ink-2">{{ __('The button at the top adds a pursuit to the Pipeline from any page.') }}</dd></div>
                </dl>
            </section>

            <section id="pages">
                <h2 class="font-heading text-2xl text-navy">{{ $sections['pages'] }}</h2>
                <p class="mt-2 text-ink-2">{{ __('Click a name to open the page. Editable marks pages where you can change the figures.') }}</p>
                @foreach(\App\Support\Navigation::for(auth()->user()) as $group => $pages)
                    <h3 class="mt-6 border-b border-gold-line pb-1 text-xs font-medium tracking-[0.14em] text-gold-text uppercase">{{ __($group) }}</h3>
                    <dl class="mt-3 space-y-2">
                        @foreach($pages as $page)
                            <div class="grid gap-x-4 sm:grid-cols-[11rem_1fr]">
                                <dt>
                                    <a href="{{ route($page['route']) }}" class="font-medium text-navy hover:underline" wire:navigate>{{ __($page['label']) }}</a>
                                    @if($page['editable']) <span class="ml-1 text-[0.65rem] tracking-[0.1em] text-gold-text uppercase">{{ __('Editable') }}</span> @endif
                                </dt>
                                <dd class="text-ink-2">{{ __($page['help']) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endforeach
            </section>

            <section id="figures">
                <h2 class="font-heading text-2xl text-navy">{{ $sections['figures'] }}</h2>
                <ol class="mt-3 list-decimal space-y-1.5 pl-5">
                    <li>{{ __('Hours, and anything still kept in Excel, come in through Upload workbook (admins): upload Dashboard Data.xlsx and read the check at the top of the page.') }}</li>
                    <li>{{ __('The month’s billing is entered on Month End: pick the month, type each phase’s percentage or the CA draw, and add the hourly lines.') }}</li>
                    <li>{{ __('Payments and contract changes are made on each project’s page.') }}</li>
                    <li>{{ __('When the month is invoiced, close it on Month End so nobody changes it by accident. An admin or manager can reopen it.') }}</li>
                </ol>
            </section>

            <section id="data">
                <h2 class="font-heading text-2xl text-navy">{{ $sections['data'] }}</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5">
                    <li>{{ __('The dashboard’s database is the record: contracts and fee phases, what was billed each month, payments and consultants.') }}</li>
                    <li>
                        {{ __('Hours come only from the workbook’s Hours sheet, so they are as current as the last upload') }}@if($lastImport) — {{ __('last uploaded :date', ['date' => $lastImport->created_at->timezone(config('app.display_timezone'))->format('M j, Y g:i a')]) }}@endif.
                    </li>
                    <li>{{ __('The workbook is an input. Changes made in the dashboard are not written back to Excel.') }}</li>
                    <li>{{ __('A closed billing month refuses changes. The change log records who changed what, and what it was before.') }}</li>
                </ul>
            </section>

            <section id="trouble">
                <h2 class="font-heading text-2xl text-navy">{{ $sections['trouble'] }}</h2>
                <dl class="mt-3 space-y-3">
                    <div><dt class="font-medium">{{ __('I can’t sign in, or a page says I’m not allowed') }}</dt><dd class="text-ink-2">{{ __('Sign in with your @formwerks.ca Microsoft account. If a page is missing, your role doesn’t include it: ask an admin.') }}</dd></div>
                    <div><dt class="font-medium">{{ __('A change won’t save and says the period is closed') }}</dt><dd class="text-ink-2">{{ __('That month has been invoiced and closed. A manager or admin can reopen it on Month End.') }}</dd></div>
                    <div><dt class="font-medium">{{ __('A project shows no hours, or fewer than expected') }}</dt><dd class="text-ink-2">{{ __('Its job number in the workbook’s Hours sheet doesn’t match the project. Fix the job number in Excel and upload again.') }}</dd></div>
                    <div><dt class="font-medium">{{ __('The numbers look old') }}</dt><dd class="text-ink-2">{{ __('Hours are as of the last upload (see above). Billing is live.') }}</dd></div>
                    <div><dt class="font-medium">{{ __('An upload was rejected or shows warnings') }}</dt><dd class="text-ink-2">{{ __('The upload page lists each row it couldn’t place and each figure that differs from the workbook. Fix those in Excel and upload again.') }}</dd></div>
                </dl>
                <p class="mt-4 text-ink-2">{{ __('Anything else: ask Nathan.') }}</p>
            </section>

            <section id="versions">
                <h2 class="font-heading text-2xl text-navy">{{ $sections['versions'] }}</h2>
                @foreach($changelog as $entry)
                    <h3 class="mt-4 font-medium">{{ __('Version :version', ['version' => $entry['version']]) }} <span class="text-sm font-normal text-muted">{{ \Carbon\CarbonImmutable::parse($entry['date'])->format('F j, Y') }}</span></h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-ink-2">
                        @foreach($entry['changes'] as $change) <li>{{ $change }}</li> @endforeach
                    </ul>
                @endforeach
            </section>
        </div>
    </div>
</x-layouts::app>
