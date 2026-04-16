const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export function renderWeekday(elementId, data) {
    const el = document.getElementById(elementId);
    const chart = echarts.getInstanceByDom(el) || echarts.init(el, 'dark', { backgroundColor: 'transparent' });
    if (!echarts.getInstanceByDom(el)) window.addEventListener('resize', () => chart.resize());

    const counts = Array(7).fill(0);
    data.forEach(d => { counts[parseInt(d.weekday)] = parseInt(d.visits); });

    chart.setOption({
        tooltip: {
            trigger: 'axis', axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const d = data.find(r => parseInt(r.weekday) === params[0].dataIndex);
                return `<b>${params[0].name}</b><br/>${params[0].value} visits<br/>${d?.visitors ?? 0} unique visitors`;
            }
        },
        grid: { left: '2%', right: '2%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: DAY_NAMES },
        yAxis: { type: 'value', name: 'Visits' },
        series: [{ type: 'bar', data: counts, itemStyle: { color: '#6366f1', borderRadius: [4,4,0,0] } }]
    }, true);
}
