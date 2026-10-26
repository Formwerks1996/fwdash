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
