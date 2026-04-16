import { initRecentVisitors } from './charts/recent-visitors.js';
import { initLiveVisitors }   from './charts/live-visitors.js';
import { renderLeaderboard }  from './charts/leaderboard.js';
import { renderHeatmap }      from './charts/heatmap.js';
import { renderHourly }       from './charts/hourly.js';
import { renderWeekday }      from './charts/weekday.js';
import { renderDaily }        from './charts/daily.js';
import { renderDurationDist } from './charts/duration.js';
import { renderNewReturning } from './charts/newreturning.js';
import { renderScatter }      from './charts/scatter.js';

// Charts that auto-refresh: [url, renderer, elementId]
const CHARTS = [
    ['/api/leaderboard',          renderLeaderboard,  'chart-leaderboard'],
    ['/api/heatmap',              renderHeatmap,      'chart-heatmap'],
    ['/api/hourly',               renderHourly,       'chart-hourly'],
    ['/api/weekday',              renderWeekday,      'chart-weekday'],
    ['/api/daily',                renderDaily,        'chart-daily'],
    ['/api/duration-distribution',renderDurationDist, 'chart-duration'],
    ['/api/new-vs-returning',     renderNewReturning, 'chart-newreturning'],
    ['/api/frequency-vs-duration',renderScatter,      'chart-scatter'],
];

function loadAllCharts() {
    CHARTS.forEach(([url, renderer, elementId]) => {
        fetch(url)
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => renderer(elementId, data))
            .catch(err => console.error(`Failed to load ${url}:`, err));
    });
}

// Init widgets
initRecentVisitors('recent-visitors');
const liveVisitors = initLiveVisitors('live-visitors');

// Initial chart load
loadAllCharts();

// Full chart refresh every 60s
setInterval(() => {
    loadAllCharts();
    liveVisitors.refresh();
}, 60_000);
