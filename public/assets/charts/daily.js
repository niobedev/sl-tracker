export function renderDaily(elementId, data) {
    const el = document.getElementById(elementId);
    const chart = echarts.getInstanceByDom(el) || echarts.init(el, 'dark', { backgroundColor: 'transparent' });
    if (!echarts.getInstanceByDom(el)) window.addEventListener('resize', () => chart.resize());

    chart.setOption({
        tooltip: { trigger: 'axis' },
        legend: { data: ['Unique Visitors', 'Total Visits'], top: 0, textStyle: { color: '#9ca3af' } },
        grid: { left: '2%', right: '2%', bottom: '10%', top: '40px', containLabel: true },
        dataZoom: [{ type: 'slider', bottom: 0, height: 20 }],
        xAxis: { type: 'category', data: data.map(d => d.day), axisLabel: { rotate: 45, fontSize: 10 } },
        yAxis: { type: 'value' },
        series: [
            { name: 'Unique Visitors', type: 'line', data: data.map(d => parseInt(d.visitors)), smooth: true, symbol: 'none', itemStyle: { color: '#818cf8' }, areaStyle: { color: { type: 'linear', x:0,y:0,x2:0,y2:1, colorStops: [{offset:0,color:'rgba(99,102,241,0.3)'},{offset:1,color:'rgba(99,102,241,0)'}] } } },
            { name: 'Total Visits', type: 'line', data: data.map(d => parseInt(d.visits)), smooth: true, symbol: 'none', itemStyle: { color: '#34d399' }, lineStyle: { type: 'dashed' } }
        ]
    }, true);
}
