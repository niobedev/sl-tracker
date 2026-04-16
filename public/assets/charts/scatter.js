export function renderScatter(elementId, data) {
    const el = document.getElementById(elementId);
    const chart = echarts.getInstanceByDom(el) || echarts.init(el, 'dark', { backgroundColor: 'transparent' });
    if (!echarts.getInstanceByDom(el)) window.addEventListener('resize', () => chart.resize());

    const maxTotal = Math.max(...data.map(d => parseFloat(d.total_minutes)), 1);

    chart.setOption({
        tooltip: {
            trigger: 'item',
            formatter: p => {
                const d = data[p.dataIndex];
                return `<b>${d.display_name}</b><br/>Visits: ${d.visit_count}<br/>Avg: ${parseFloat(d.avg_minutes).toFixed(0)} min<br/>Total: ${(d.total_minutes/60).toFixed(1)}h`;
            }
        },
        grid: { left: '4%', right: '4%', bottom: '8%', containLabel: true },
        xAxis: { type: 'value', name: 'Visit Count' },
        yAxis: { type: 'value', name: 'Avg Duration (min)' },
        series: [{ type: 'scatter', data: data.map(d => ({ value: [d.visit_count, parseFloat(d.avg_minutes).toFixed(1)], symbolSize: Math.max(8, Math.sqrt(parseFloat(d.total_minutes)/maxTotal)*40), itemStyle: { color: '#6366f1', opacity: 0.75 } })), emphasis: { focus: 'self', itemStyle: { color: '#818cf8' } } }]
    }, true);
}
