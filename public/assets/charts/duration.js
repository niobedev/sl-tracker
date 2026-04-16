const BUCKET_ORDER = ['< 5 min', '5-15 min', '15-30 min', '30-60 min', '1-2 hrs', '2+ hrs'];
const COLORS = ['#312e81', '#4338ca', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe'];

export function renderDurationDist(elementId, data) {
    const el = document.getElementById(elementId);
    const chart = echarts.getInstanceByDom(el) || echarts.init(el, 'dark', { backgroundColor: 'transparent' });
    if (!echarts.getInstanceByDom(el)) window.addEventListener('resize', () => chart.resize());

    const sorted = BUCKET_ORDER
        .map((name, i) => ({ name, value: parseInt(data.find(d => d.bucket === name)?.count ?? 0), itemStyle: { color: COLORS[i] } }))
        .filter(d => d.value > 0);

    chart.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} visits ({d}%)' },
        legend: { orient: 'vertical', right: '5%', top: 'center', textStyle: { color: '#9ca3af' } },
        series: [{ type: 'pie', radius: ['40%', '70%'], center: ['40%', '50%'], data: sorted, label: { show: false }, emphasis: { label: { show: true, fontSize: 14, fontWeight: 'bold' } } }]
    }, true);
}
