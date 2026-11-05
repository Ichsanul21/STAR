import Chart from 'chart.js/auto';

const root = document.querySelector('[data-scurve]');

if (root) {
    let series = null;

    try {
        series = JSON.parse(root.dataset.series || 'null');
    } catch (error) {
        series = null;
    }

    const canvas = document.getElementById('scurveChart');

    if (canvas && series?.labels?.length) {
        const styles = getComputedStyle(document.documentElement);
        const brand = styles.getPropertyValue('--brand').trim() || '#0a2540';
        const chart2 = styles.getPropertyValue('--chart-2').trim() || '#4f7cac';
        const success = styles.getPropertyValue('--success').trim() || '#15803d';
        const muted = styles.getPropertyValue('--muted').trim() || '#64748b';

        const context = canvas.getContext('2d');
        const fill = context.createLinearGradient(0, 0, 0, 320);
        fill.addColorStop(0, 'rgba(21, 128, 61, 0.22)');
        fill.addColorStop(1, 'rgba(21, 128, 61, 0.01)');

        Chart.defaults.font.family = "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif";
        Chart.defaults.color = muted;
        Chart.defaults.font.size = 11;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: series.labels,
                datasets: [
                    {
                        label: 'Planned',
                        data: series.planned,
                        borderColor: chart2,
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0.35,
                        fill: false,
                    },
                    {
                        label: 'Actual',
                        data: series.actual,
                        borderColor: success,
                        backgroundColor: fill,
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0.35,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${Number(context.parsed.y).toFixed(1)}%`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(100,116,139,0.12)' },
                        ticks: { callback: (value) => `${value}%` },
                    },
                },
            },
            plugins: [
                {
                    id: 'curveAnchor',
                    afterDraw(chart) {
                        const meta = chart.getDatasetMeta(1);
                        const last = meta.data[meta.data.length - 1];

                        if (!last) return;

                        const { ctx } = chart;
                        ctx.save();
                        ctx.fillStyle = brand;
                        ctx.beginPath();
                        ctx.arc(last.x, last.y, 4, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.restore();
                    },
                },
            ],
        });
    }
}
