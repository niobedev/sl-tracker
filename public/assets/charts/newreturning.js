export function renderNewReturning(elementId, data) {
    const el = document.getElementById(elementId);
    const chart = echarts.getInstanceByDom(el) || echarts.init(el, 'dark', { backgroundColor: 'transparent' });
    if (!echarts.getInstanceByDom(el)) window.addEventListener('resize', () => chart.resize());

    chart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: { data: ['New', 'Returning'], top: 0, textStyle: { color: '#9ca3af' } },
        grid: { left: '2%', right: '2%', bottom: '10%', top: '40px', containLabel: true },
        dataZoom: [{ type: 'slider', bottom: 0, height: 20 }],
        xAxis: { type: 'category', data: data.map(d => d.week), axisLabel: { rotate: 45, fontSize: 10 } },
        yAxis: { type: 'value' },
        series: [
            { name: 'New',       type: 'bar', stack: 'total', data: data.map(d => d.new_visitors),       itemStyle: { color: '#6366f1' } },
            { name: 'Returning', type: 'bar', stack: 'total', data: data.map(d => d.returning_visitors), itemStyle: { color: '#34d399' } }
        ]
    }, true);
}
