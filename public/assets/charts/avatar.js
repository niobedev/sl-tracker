// Per-avatar charts (used on avatar profile page)
export function renderAvatarHourly(elementId, data) {
    const chart = echarts.init(document.getElementById(elementId), 'dark', { backgroundColor: 'transparent' });

    const counts = Array(24).fill(0);
    data.forEach(d => { counts[parseInt(d.hour)] = parseInt(d.count); });
    const hours = Array.from({ length: 24 }, (_, i) => `${i}:00`);

    chart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: '2%', right: '2%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: hours, axisLabel: { rotate: 45, fontSize: 10 } },
        yAxis: { type: 'value', name: 'Visits' },
        series: [{
            type: 'bar',
            data: counts,
            itemStyle: { color: '#6366f1', borderRadius: [4, 4, 0, 0] }
        }]
    });

    window.addEventListener('resize', () => chart.resize());
}

export function renderAvatarHistory(elementId, data) {
    const chart = echarts.init(document.getElementById(elementId), 'dark', { backgroundColor: 'transparent' });

    const dates = data.map(d => new Date(d.joined_at * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }));
    const durations = data.map(d => parseFloat(d.duration_minutes).toFixed(1));

    chart.setOption({
        tooltip: {
            trigger: 'axis',
            formatter: (params) => {
                return `${params[0].name}<br/><b>${params[0].value} min</b>`;
            }
        },
        grid: { left: '2%', right: '2%', bottom: '10%', containLabel: true },
        dataZoom: [{ type: 'slider', bottom: 0, height: 20 }],
        xAxis: { type: 'category', data: dates, axisLabel: { rotate: 45, fontSize: 10 } },
        yAxis: { type: 'value', name: 'Duration (min)' },
        series: [{
            type: 'bar',
            data: durations,
            itemStyle: { color: '#818cf8', borderRadius: [4, 4, 0, 0] }
        }]
    });

    window.addEventListener('resize', () => chart.resize());
}
