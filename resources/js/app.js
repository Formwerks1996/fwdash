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

    revenue: ({ labels, actual, runRate, prior }) => ({
        data: {
            labels,
            datasets: [
                { type: 'bar', label: 'Billed', data: actual, backgroundColor: '#00263E', stack: 'revenue' },
                { type: 'bar', label: 'Run rate', data: runRate, backgroundColor: '#dfe4e9', stack: 'revenue' },
                {
                    type: 'line',
                    label: 'Prior year',
                    data: prior,
                    borderColor: '#5c6873',
                    borderDash: [4, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    spanGaps: true,
                },
            ],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (item) => (item.raw === null ? null : `${item.dataset.label}: ${compact(item.raw)}`) } },
            },
            scales: {
                x: { grid: { display: false }, stacked: true },
                y: { grid, border: { display: false }, stacked: true, ticks: { callback: compact } },
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
 * Month End, Extras and Monthly Targets. A click on a month is raised as a
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
    monthEndRevenue: ({ labels, revenue, bases, prior, selected, clickable }) => ({
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

    targets: ({ labels, titles, shown, estimate, hit, target }) => ({
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Invoiced',
                    data: shown,
                    backgroundColor: shown.map((value, index) => (estimate[index] ? '#d6e1ea' : hit[index] ? '#3f7d4f' : '#00263E')),
                    borderRadius: 4,
                    maxBarThickness: 34,
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Target',
                    data: target,
                    borderColor: '#0f2233',
                    backgroundColor: '#0f2233',
                    borderWidth: 2,
                    borderDash: [5, 4],
                    pointRadius: 3,
                    spanGaps: true,
                    order: 1,
                },
            ],
        },
        options: {
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => titles[items[0].dataIndex],
                        label: (item) => {
                            if (item.raw === null) {
                                return null;
                            }

                            const label = item.datasetIndex === 0 ? (estimate[item.dataIndex] ? 'Invoiced (estimate)' : 'Invoiced') : 'Target';

                            return `${label}: ${moneyWhole.format(item.raw)}`;
                        },
                    },
                },
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid, border: { display: false }, ticks: { maxTicksLimit: 6, callback: compactFine } },
            },
        },
    }),
});
