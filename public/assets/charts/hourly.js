export function renderHourly(elementId, data) {
    const el = document.getElementById(elementId);
    const chart = echarts.getInstanceByDom(el) || echarts.init(el, 'dark', { backgroundColor: 'transparent' });
    if (!echarts.getInstanceByDom(el)) window.addEventListener('resize', () => chart.resize());

    const counts = Array(24).fill(0);
    data.forEach(d => { counts[parseInt(d.hour)] = parseInt(d.count); });
    const max = Math.max(...counts);

    chart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        grid: { left: '2%', right: '2%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: Array.from({length:24},(_,i)=>`${i}:00`), axisLabel: { rotate: 45, fontSize: 11 } },
        yAxis: { type: 'value', name: 'Visits' },
        series: [{ type: 'bar', data: counts.map(v => ({ value: v, itemStyle: { color: v === max ? '#818cf8' : '#4338ca', borderRadius: [4,4,0,0] } })) }]
    }, true);
}
