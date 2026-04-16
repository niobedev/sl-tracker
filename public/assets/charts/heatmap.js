const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export function renderHeatmap(elementId, data) {
    const el = document.getElementById(elementId);
    const chart = echarts.getInstanceByDom(el) || echarts.init(el, 'dark', { backgroundColor: 'transparent' });
    if (!echarts.getInstanceByDom(el)) window.addEventListener('resize', () => chart.resize());

    const matrix = {};
    data.forEach(row => { matrix[`${row.weekday}-${row.hour}`] = parseInt(row.count); });

    const values = [];
    for (let d = 0; d < 7; d++)
        for (let h = 0; h < 24; h++)
            values.push([h, d, matrix[`${d}-${h}`] ?? 0]);

    const max = Math.max(...values.map(v => v[2]), 1);

    chart.setOption({
        tooltip: {
            position: 'top',
            formatter: p => `${DAYS[p.data[1]]} ${p.data[0]}:00<br/><b>${p.data[2]} join events</b>`
        },
        grid: { left: '4%', right: '4%', top: '10%', bottom: '10%', containLabel: true },
        xAxis: { type: 'category', data: Array.from({length:24},(_,i)=>`${i}:00`), splitArea: { show: true }, axisLabel: { interval: 2, fontSize: 11 } },
        yAxis: { type: 'category', data: DAYS, splitArea: { show: true } },
        visualMap: {
            min: 0, max, calculable: true, orient: 'horizontal', left: 'center', bottom: 0,
            inRange: { color: ['#1e1b4b', '#4338ca', '#818cf8', '#c7d2fe'] }
        },
        series: [{ type: 'heatmap', data: values, emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(99,102,241,0.5)' } } }]
    }, true);
}
