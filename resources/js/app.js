import Chart from 'chart.js/auto';

Chart.defaults.font.family = "'Helvetica Neue', Helvetica, Arial, system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = '#5c6873';
Chart.defaults.maintainAspectRatio = false;

const compact = (value) => {
    const absolute = Math.abs(value);
    const sign = value < 0 ? '−' : '';

    if (absolute >= 1e6) {
        return `${sign}$${(absolute / 1e6).toFixed(2)}M`;
    }

    if (absolute >= 1e3) {
        return `${sign}$${Math.round(absolute / 1e3)}k`;
    }

    return `${sign}$${Math.round(absolute)}`;
};

const grid = { color: '#f0f1f2' };

const charts = {
    activity: ({ labels, payments, hours }) => ({
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Payments',
                    data: payments,
                    backgroundColor: 'rgba(0,38,62,.08)',
                    borderColor: 'rgba(0,38,62,.25)',
                    borderWidth: 1,
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: 'Hours',
                    data: hours,
                    borderColor: '#a5573c',
                    backgroundColor: '#a5573c',
                    pointRadius: 2,
                    tension: 0,
                    yAxisID: 'hours',
                },
            ],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' } },
            scales: {
                x: { grid: { display: false } },
                y: { grid, border: { display: false }, ticks: { callback: compact }, title: { display: true, text: 'Payments' } },
                hours: { position: 'right', grid: { display: false }, border: { display: false }, title: { display: true, text: 'Hours' } },
            },
        },
    }),

    overview: ({ labels, actual, runRate, cost, profit, projected = [], projectedCost = [] }) => ({
        data: {
            labels,
            datasets: [
                { type: 'line', label: 'Profit', data: profit, borderColor: '#2f8a67', backgroundColor: '#2f8a67', borderWidth: 2, pointRadius: 3, tension: 0, order: 0 },
                ...(projected.some((value) => value !== null)
                    ? [{ type: 'line', label: 'Projected billing', data: projected, borderColor: '#00263E', backgroundColor: '#fff', borderDash: [5, 4], borderWidth: 1.5, pointRadius: 4, pointBorderWidth: 1.5, showLine: true, spanGaps: true, tension: 0, order: 0 }]
                    : []),
                ...(projectedCost.some((value) => value !== null)
                    ? [{ type: 'line', label: 'Projected cost', data: projectedCost, borderColor: '#c4694a', backgroundColor: '#fff', borderDash: [5, 4], borderWidth: 1.5, pointRadius: 4, pointBorderWidth: 1.5, spanGaps: true, tension: 0, order: 0 }]
                    : []),
                { type: 'bar', label: 'Billed', data: actual, backgroundColor: '#00263E', stack: 'billed', maxBarThickness: 26, order: 1 },
                { type: 'bar', label: 'Run rate', data: runRate, backgroundColor: '#dfe4e9', stack: 'billed', maxBarThickness: 26, order: 1 },
                { type: 'bar', label: 'Cost', data: cost, backgroundColor: '#c4694a', stack: 'cost', maxBarThickness: 26, order: 1 },
            ],
        },
        options: {
            onClick: (event, elements, chart) => emitClick(chart, elements, actual.map((value) => value !== null)),
            onHover: (event, elements, chart) => pointerOver(event, elements, chart, actual.map((value) => value !== null)),
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (item) => (item.raw === null ? null : `${item.dataset.label}: ${compact(item.raw)}`) } },
            },
            scales: {
                x: { grid: { display: false }, stacked: true },
                y: { grid, border: { display: false }, ticks: { callback: compact } },
            },
        },
    }),

    ring: ({ labels, values, colors }) => ({
        type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] },
        options: {
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (item) => `${item.label}: ${compact(item.raw)}` } },
            },
        },
    }),

    cumulative: ({ labels, billed, contract }) => ({
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Billed to date',
                    data: billed,
                    borderColor: '#3f7d4f',
                    backgroundColor: 'rgba(47,138,103,.10)',
                    fill: true,
                    pointRadius: 0,
                    tension: 0.15,
                },
                {
                    label: 'Contract',
                    data: labels.map(() => contract),
                    borderColor: '#85754E',
                    borderDash: [4, 4],
                    borderWidth: 1,
                    pointRadius: 0,
                },
            ],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' } },
            scales: {
                x: { grid: { display: false } },
                y: { grid, border: { display: false }, ticks: { callback: compact } },
            },
        },
    }),
};

// A chart still animating when wire:navigate swaps the page would draw into a removed canvas.
document.addEventListener('livewire:navigating', () => {
    Object.values(Chart.instances).forEach((chart) => chart.destroy());
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('chart', (kind, payload) => ({
        instance: null,

        init() {
            this.render(payload);
        },

        render(next) {
            this.instance?.destroy();
            this.instance = new Chart(this.$refs.canvas, charts[kind](next));
        },

        destroy() {
            this.instance?.destroy();
        },
    }));
});

/*
 * Month End and Extras. A click on a month is raised as a
 * bubbling "chart-click" DOM event carrying the month's index, so the page can
 * hand it to Livewire with x-on:chart-click.
 */
const moneyWhole = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD', currencyDisplay: 'narrowSymbol', maximumFractionDigits: 0 });

const compactFine = (value) => {
    const absolute = Math.abs(value);
    const sign = value < 0 ? '−' : '';

    if (absolute >= 1e6) {
        return `${sign}$${(absolute / 1e6).toFixed(2)}M`;
    }

    if (absolute >= 1e3) {
        return `${sign}$${(absolute / 1e3).toFixed(absolute >= 1e4 ? 0 : 1)}k`;
    }

    return `${sign}$${Math.round(absolute)}`;
};

const emitClick = (chart, elements, clickable) => {
    const index = elements[0]?.index;

    if (index === undefined || (clickable && !clickable[index])) {
        return;
    }

    chart.canvas.dispatchEvent(new CustomEvent('chart-click', { bubbles: true, detail: { index } }));
};

const pointerOver = (event, elements, chart, clickable) => {
    const index = elements[0]?.index;

    chart.canvas.style.cursor = index !== undefined && (!clickable || clickable[index]) ? 'pointer' : 'default';
};

const revenueLabels = { actual: 'Billed', runrate: 'At this pace' };

Object.assign(charts, {
    monthEndRevenue: ({ labels, revenue, bases, prior, selected, clickable, spent }) => ({
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue,
                    borderColor: '#00263E',
                    borderWidth: 2.5,
                    tension: 0,
                    segment: {
                        borderDash: (context) => (bases[context.p1DataIndex] !== 'actual' ? [5, 4] : undefined),
                        borderColor: (context) => (bases[context.p1DataIndex] !== 'actual' ? '#a9bccb' : '#00263E'),
                    },
                    pointRadius: revenue.map((value, index) => (index === selected ? 6 : 3)),
                    pointHoverRadius: 7,
                    pointBackgroundColor: revenue.map((value, index) => (index === selected ? '#0b3a5a' : bases[index] === 'actual' ? '#00263E' : '#fff')),
                    pointBorderColor: revenue.map((value, index) => (index === selected ? '#0b3a5a' : bases[index] === 'actual' ? '#00263E' : '#a9bccb')),
                },
                {
                    label: 'Prior year',
                    data: prior,
                    borderColor: '#5c6873',
                    borderWidth: 1.5,
                    borderDash: [4, 3],
                    pointRadius: 0,
                    spanGaps: true,
                },
                // Managers and admins only: staff time at cost, behind the lines.
                ...(spent
                    ? [
                          {
                              type: 'bar',
                              label: 'Spent',
                              data: spent,
                              backgroundColor: spent.map((value, index) => (index === selected ? '#d9a896' : '#efd9d0')),
                              borderRadius: 3,
                              maxBarThickness: 22,
                              order: 3,
                          },
                      ]
                    : []),
            ],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            onClick: (event, elements, chart) => emitClick(chart, elements, clickable),
            onHover: (event, elements, chart) => pointerOver(event, elements, chart, clickable),
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (item) => {
                            if (item.raw === null) {
                                return null;
                            }

                            const label = item.datasetIndex === 0 ? (revenueLabels[bases[item.dataIndex]] ?? 'Billed') : item.dataset.label;

                            return `${label}: ${moneyWhole.format(item.raw)}`;
                        },
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#5c6873', font: { size: 11 } } },
                y: { beginAtZero: true, grid, border: { display: false }, ticks: { maxTicksLimit: 6, callback: compact } },
            },
        },
    }),

    extrasBars: ({ labels, titles, values, selected, metric, after }) => ({
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    data: values,
                    backgroundColor: values.map((value, index) => (index === selected ? '#00263E' : '#a9bccb')),
                    borderRadius: 4,
                    borderSkipped: 'start',
                    maxBarThickness: 24,
                },
            ],
        },
        options: {
            onClick: (event, elements, chart) => emitClick(chart, elements),
            onHover: (event, elements, chart) => pointerOver(event, elements, chart),
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => titles[items[0].dataIndex],
                        label: (item) => (metric === 'money' ? moneyWhole.format(item.raw) : `${item.raw.toLocaleString('en-CA', { maximumFractionDigits: 2 })} h`),
                        afterLabel: (item) => after[item.dataIndex],
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#5c6873', font: { size: 11 }, autoSkip: true } },
                y: { grid, border: { display: false }, ticks: { maxTicksLimit: 5, callback: (value) => (metric === 'money' ? compactFine(value) : value.toLocaleString('en-CA')) } },
            },
        },
    }),
});

/*
 * Timesheets' add-row line. Search projects (yours first, then by type) or
 * pick office or time off; a project then asks for its phase. The chosen row
 * goes to Livewire's addRow through newRow.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('rowPicker', ({ groups, categories, phases }) => ({
        categories,
        open: false,
        query: '',
        projectQuery: '',
        project: null,
        cursor: 0,
        busy: false,

        init() {
            this.$watch('cursor', () => this.$nextTick(() => this.$refs.list.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' })));
        },

        get sections() {
            const terms = this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
            const matches = (text) => terms.every((term) => text.toLowerCase().includes(term));

            if (this.project) {
                return [
                    {
                        label: '',
                        items: phases
                            .filter((phase) => matches(`${phase.code} ${phase.name}`))
                            .map((phase) => ({ key: `phase:${phase.code}`, name: phase.name, meta: phase.code, phase: phase.code })),
                    },
                ];
            }

            const sections = groups
                .filter((group) => terms.length || !group.inactive)
                .map((group) => ({
                    label: group.label,
                    items: group.items
                        .filter((item) => matches(`${item.name} ${item.job} ${item.type}`))
                        .map((item) => ({ ...item, key: `project:${item.job}` })),
                }));

            if (terms.length) {
                sections.push({
                    label: 'Office & time off',
                    items: categories
                        .filter((category) => matches(category.label))
                        .map((category) => ({ key: `category:${category.value}`, name: category.label, meta: '', category: category.value })),
                });
            }

            return sections.filter((section) => section.items.length);
        },

        get options() {
            return this.sections.flatMap((section) => section.items);
        },

        start() {
            this.$refs.search.scrollIntoView({ block: 'center', behavior: 'smooth' });
            this.$refs.search.focus({ preventScroll: true });
            this.open = true;
        },

        move(step) {
            this.open = true;
            this.cursor = Math.min(Math.max(this.cursor + step, 0), this.options.length - 1);
        },

        choose(option) {
            if (option.category) {
                return this.add({ category: option.category });
            }

            if (this.project) {
                return this.add({ category: 'project', job_number: this.project.job, phase_code: option.phase });
            }

            this.project = option;
            this.projectQuery = this.query;
            this.query = '';
            this.cursor = 0;
            this.$refs.search.focus();
        },

        back() {
            this.project = null;
            this.query = this.projectQuery;
            this.cursor = 0;
            this.$refs.search.focus();
        },

        close(blur = false) {
            this.open = false;
            this.project = null;
            this.query = '';
            this.cursor = 0;

            if (blur) {
                this.$refs.search.blur();
            }
        },

        async add(row) {
            this.busy = true;
            this.$wire.newRow = { category: 'project', job_number: '', phase_code: '', ...row };

            try {
                await this.$wire.addRow();
            } finally {
                this.busy = false;
                this.close(true);
            }
        },
    }));
});

/*
 * Leads & PMs: the person menu behind each lead or PM. The lists live on the
 * table (leads, team); the current person is read from data-current, which
 * Livewire keeps up to date after each change.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('assignee', (job, role, list) => ({
        open: false,
        query: '',
        cursor: 0,
        current: '',
        position: '',

        get options() {
            const terms = this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);

            return this[list].filter((person) => terms.every((term) => `${person.label} ${person.name} ${person.hint}`.toLowerCase().includes(term)));
        },

        toggle() {
            if (this.open) {
                return this.close();
            }

            this.current = this.$root.dataset.current;
            this.query = '';
            this.cursor = Math.max(this.options.findIndex((person) => person.name === this.current), 0);
            this.place();
            this.open = true;
            this.$nextTick(() => this.$refs.search.focus({ preventScroll: true }));
        },

        // Fixed to the trigger, so the table's scroll box can't clip it, and kept there on scroll; opens upward near the bottom of the window.
        place() {
            const trigger = this.$refs.trigger.getBoundingClientRect();
            const left = Math.min(trigger.left, window.innerWidth - 272);

            this.position = window.innerHeight - trigger.bottom < 340
                ? `left: ${left}px; bottom: ${window.innerHeight - trigger.top + 4}px`
                : `left: ${left}px; top: ${trigger.bottom + 4}px`;
        },

        close(refocus = false) {
            this.open = false;

            if (refocus) {
                this.$refs.trigger.focus();
            }
        },

        choose(name) {
            this.close(true);

            if (name !== this.current) {
                this.$wire.setRole(job, role, name);
            }
        },
    }));
});
