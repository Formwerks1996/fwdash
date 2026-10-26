<div
    class="relative w-full max-w-sm"
    x-data="{
        query: '',
        items: null,
        results: [],
        selected: 0,
        open: false,
        async load() {
            if (this.items) {
                return;
            }

            const response = await fetch(@js(route('quickJump')), { headers: { Accept: 'application/json' } });
            this.items = await response.json();
            this.search();
        },
        search() {
            const q = this.query.trim().toLowerCase().replace(/^#/, '');
            this.selected = 0;
            this.open = q !== '';

            if (! q || ! this.items) {
                this.results = [];

                return;
            }

            const weight = { Page: 15, Job: 5, Developer: 0 };

            this.results = this.items
                .map((item) => {
                    const at = item.text.indexOf(q);

                    if (at === -1) {
                        return null;
                    }

                    const start = at === 0 ? 100 : /[\s#]/.test(item.text[at - 1]) ? 60 : 20;

                    return { ...item, score: start + weight[item.kind] - Math.min(item.text.length, 60) / 10 };
                })
                .filter(Boolean)
                .sort((a, b) => b.score - a.score)
                .slice(0, 8);
        },
        go(item) {
            if (! item) {
                return;
            }

            this.query = '';
            this.open = false;
            this.$refs.input.blur();
            Livewire.navigate(item.url);
        },
        focus() {
            this.$refs.input.focus();
            this.$refs.input.select();
        },
    }"
    x-on:keydown.window.meta.k.prevent="focus()"
    x-on:keydown.window.ctrl.k.prevent="focus()"
    x-on:keydown.window.slash="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && ! document.activeElement.isContentEditable) { $event.preventDefault(); focus() }"
>
    <i class="ph ph-magnifying-glass absolute top-1/2 left-3.5 -translate-y-1/2 text-muted"></i>
    <input
        x-ref="input"
        x-model="query"
        x-on:focus="load()"
        x-on:input="load().then(() => search())"
        x-on:blur="setTimeout(() => open = false, 120)"
        x-on:keydown.arrow-down.prevent="selected = (selected + 1) % Math.max(results.length, 1)"
        x-on:keydown.arrow-up.prevent="selected = (selected - 1 + results.length) % Math.max(results.length, 1)"
        x-on:keydown.enter.prevent="go(results[selected])"
        x-on:keydown.escape="query = ''; open = false; $el.blur()"
        role="combobox"
        aria-autocomplete="list"
        aria-controls="quick-jump-list"
        :aria-expanded="open"
        class="w-full rounded-[20px] border border-line bg-[#f9f9f9] py-2 pr-4 pl-9 text-sm focus:border-navy focus:ring-1 focus:ring-navy focus:outline-none"
        placeholder="{{ __('Jump to a page, job or developer  ⌘K') }}"
    >

    <div x-show="open" x-cloak id="quick-jump-list" class="absolute top-full right-0 left-0 z-40 mt-2 border border-line border-t-2 border-t-gold bg-white shadow-lg">
        <template x-for="(item, index) in results" :key="item.url">
            <button type="button" class="grid w-full grid-cols-[5.5rem_1fr] items-baseline gap-2 px-3 py-2 text-left" :class="index === selected && 'bg-gold-soft'" x-on:mousedown.prevent="go(item)" x-on:mouseenter="selected = index">
                <span class="text-[0.65rem] font-medium tracking-[0.12em] text-gold-text uppercase" x-text="item.kind"></span>
                <span class="min-w-0">
                    <span class="block truncate text-sm text-ink" x-text="item.label"></span>
                    <span class="block truncate text-xs text-muted" x-show="item.sub" x-text="item.sub"></span>
                </span>
            </button>
        </template>
        <p x-show="! results.length" class="px-3 py-3 text-sm text-muted">{{ __('No page, job or developer matches') }} “<span x-text="query"></span>”.</p>
    </div>
</div>
