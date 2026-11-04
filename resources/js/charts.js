import Chart from 'chart.js/auto';

const root = document.querySelector('[data-dashboard-chart]');

if (root) {
    const styles = getComputedStyle(document.documentElement);
    const brand = styles.getPropertyValue('--brand').trim() || '#0a2540';
    const brandSoft = styles.getPropertyValue('--brand-soft').trim() || '#e9eff7';
    const muted = styles.getPropertyValue('--muted').trim() || '#64748b';
    const success = styles.getPropertyValue('--success').trim() || '#15803d';
    const warning = styles.getPropertyValue('--warning').trim() || '#b45309';
    const danger = styles.getPropertyValue('--danger').trim() || '#b91c1c';
    const chart2 = styles.getPropertyValue('--chart-2').trim() || '#4f7cac';

    const read = (name) => {
        try {
            return JSON.parse(root.dataset[name] || '[]');
        } catch (error) {
            return [];
        }
    };

    const performance = read('performance');
    const taskStats = read('taskStats');

    Chart.defaults.font.family = "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif";
    Chart.defaults.color = muted;
    Chart.defaults.font.size = 11;

    const performanceCanvas = document.getElementById('performanceChart');
    if (performanceCanvas && performance.length) {
        const barLabels = {
            id: 'barLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                ctx.save();
                ctx.font = "700 9px 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif";
                ctx.textAlign = 'center';

                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    dataset.data.forEach((value, index) => {
                        const bar = meta.data[index];
                        if (!bar) return;
                        ctx.fillStyle = datasetIndex === 0 ? brand : chart2;
                        ctx.fillText(`${Math.round(Number(value) || 0)}%`, bar.x, bar.y - 4);
                    });
                });

                ctx.restore();
            },
        };

        new Chart(performanceCanvas, {
            type: 'bar',
            data: {
                labels: performance.map((row) => row.name),
                datasets: [
                    {
                        label: 'Progress',
                        data: performance.map((row) => row.progress),
                        backgroundColor: brand,
                        borderRadius: 5,
                        maxBarThickness: 22,
                    },
                    {
                        label: 'Serapan budget',
                        data: performance.map((row) => Math.min(100, row.absorption)),
                        backgroundColor: chart2,
                        borderRadius: 5,
                        maxBarThickness: 22,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 16 } },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${context.parsed.y}%`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 0,
                            autoSkip: false,
                            callback: function (value) {
                                const label = this.getLabelForValue(value);

                                return label.length > 15 ? `${label.slice(0, 14)}…` : label;
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(100,116,139,0.12)' },
                        ticks: { callback: (value) => `${value}%` },
                    },
                },
            },
            plugins: [barLabels],
        });
    }

    const statusCanvas = document.getElementById('taskStatusChart');
    if (statusCanvas) {
        const labels = {
            todo: 'Todo',
            in_progress: 'Berjalan',
            review: 'Review',
            blocked: 'Blocked',
            done: 'Selesai',
        };
        const colors = {
            todo: brandSoft,
            in_progress: brand,
            review: warning,
            blocked: danger,
            done: success,
        };
        const keys = Object.keys(labels).filter((key) => Number(taskStats[key] || 0) > 0);
        const values = keys.map((key) => Number(taskStats[key] || 0));

        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: keys.map((key) => labels[key]),
                datasets: [{
                    data: values,
                    backgroundColor: keys.map((key) => colors[key]),
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true },
                },
            },
        });
    }
}
