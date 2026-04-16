export function renderLeaderboard(elementId, data) {
    const el = document.getElementById(elementId);
    const existing = echarts.getInstanceByDom(el);

    el.style.height = Math.max(300, data.length * 28) + 'px';

    const chart = existing || echarts.init(el, 'dark', { backgroundColor: 'transparent' });
    if (!existing) window.addEventListener('resize', () => chart.resize());

    const names = data.map(d => d.display_name);
    const hours = data.map(d => (d.total_minutes / 60).toFixed(1));

    chart.setOption({
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
                const d = data[params[0].dataIndex];
                return `<b>${d.display_name}</b><br/>
                    ${(d.total_minutes / 60).toFixed(1)}h total<br/>
                    ${d.visit_count} visits<br/>
                    Avg ${(d.total_minutes / d.visit_count).toFixed(0)} min/visit`;
            }
        },
        grid: { left: 170, right: 55, bottom: 24, top: 8 },
        xAxis: { type: 'value', axisLabel: { formatter: '{value}h' } },
        yAxis: {
            type: 'category',
            data: names,
            axisLabel: { interval: 0, fontSize: 12, width: 160, overflow: 'truncate', ellipsis: '…' }
        },
        series: [{
            type: 'bar',
            data: hours,
            barMaxWidth: 20,
            itemStyle: { color: '#6366f1', borderRadius: [0, 4, 4, 0] },
            label: { show: true, position: 'right', formatter: '{c}h', color: '#a5b4fc', fontSize: 11 }
        }]
    }, true);

    chart.resize();
}
