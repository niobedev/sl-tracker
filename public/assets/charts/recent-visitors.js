export function initRecentVisitors(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const periods = [
        { key: 'today',     label: 'Today' },
        { key: 'yesterday', label: 'Yesterday' },
        { key: 'week',      label: 'Last week' },
        { key: 'month',     label: 'Last month' },
        { key: 'year',      label: 'This year' },
        { key: 'all',       label: 'All time' },
    ];

    let active = 'today';
    let currentData = [];

    // Tabs
    const tabs = document.createElement('div');
    tabs.className = 'flex gap-2 mb-3 flex-wrap';
    periods.forEach(p => {
        const btn = document.createElement('button');
        btn.dataset.period = p.key;
        btn.textContent = p.label;
        btn.className = tabClass(p.key === active);
        btn.addEventListener('click', () => {
            active = p.key;
            searchInput.value = '';
            tabs.querySelectorAll('button').forEach(b => {
                b.className = tabClass(b.dataset.period === active);
            });
            load(p.key);
        });
        tabs.appendChild(btn);
    });
    container.appendChild(tabs);

    // Search input
    const searchWrap = document.createElement('div');
    searchWrap.className = 'relative mb-3';
    searchWrap.innerHTML = `
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
        </svg>
        <input id="${containerId}-search" type="text" placeholder="Search by name…"
            class="w-full pl-9 pr-4 py-1.5 text-sm rounded-lg bg-gray-700/60 border border-gray-600 text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">`;
    container.appendChild(searchWrap);

    const searchInput = searchWrap.querySelector('input');
    searchInput.addEventListener('input', () => applyFilter());

    // Summary + list
    const summary = document.createElement('p');
    summary.className = 'text-xs text-gray-500 mb-2';
    container.appendChild(summary);

    const listWrap = document.createElement('div');
    listWrap.className = 'overflow-y-auto';
    listWrap.style.maxHeight = '240px';
    container.appendChild(listWrap);

    function tabClass(isActive) {
        return 'px-3 py-1 rounded-lg text-sm font-medium transition-colors ' +
            (isActive ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600');
    }

    function load(period) {
        listWrap.innerHTML = '<p class="text-gray-500 text-sm py-2">Loading…</p>';
        summary.textContent = '';
        fetch(`/api/recent-visitors?period=${period}`)
            .then(r => {
                if (!r.ok) {
                    throw new Error(`HTTP error! status: ${r.status}`);
                }
                return r.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Failed to parse response as JSON');
                    }
                });
            })
            .then(data => { currentData = data; applyFilter(); })
            .catch(error => {
                console.error('Failed to load recent visitors:', error);
                listWrap.innerHTML = `<p class="text-red-400 text-sm">Failed to load: ${error.message}</p>`;
            });
    }

    function applyFilter() {
        const q = searchInput.value.trim().toLowerCase();
        const filtered = q
            ? currentData.filter(v => v.display_name.toLowerCase().includes(q))
            : currentData;
        render(filtered, q);
    }

    function render(data, query = '') {
        const total = currentData.length;
        const showing = data.length;
        summary.textContent = query
            ? `${showing} of ${total} visitor${total !== 1 ? 's' : ''}`
            : `${total} unique visitor${total !== 1 ? 's' : ''}`;

        if (!data.length) {
            listWrap.innerHTML = query
                ? '<p class="text-gray-500 text-sm italic py-2">No matches found.</p>'
                : '<p class="text-gray-500 text-sm italic py-2">No visitors in this period.</p>';
            return;
        }

        const table = document.createElement('table');
        table.className = 'w-full text-sm';
        table.innerHTML = `
            <thead>
                <tr class="text-left text-xs text-gray-500 border-b border-gray-700 sticky top-0 bg-gray-800">
                    <th class="pb-2 font-medium">Name</th>
                    <th class="pb-2 font-medium text-right">Visits</th>
                    <th class="pb-2 font-medium text-right">Total time</th>
                    <th class="pb-2 font-medium text-right">Last seen</th>
                </tr>
            </thead>`;

        const tbody = document.createElement('tbody');
        data.forEach(v => {
            const tr = document.createElement('tr');
            tr.className = 'border-b border-gray-700/50 hover:bg-gray-700/30 transition-colors';
            const name = query ? highlight(escHtml(v.display_name), query) : escHtml(v.display_name);
            tr.innerHTML = `
                <td class="py-2 pr-4">
                    <a href="/avatar/${v.avatar_key}" class="text-indigo-400 hover:underline font-medium">${name}</a>
                </td>
                <td class="py-2 text-right text-gray-300">${v.visit_count}</td>
                <td class="py-2 text-right text-gray-300">${formatDuration(v.total_minutes)}</td>
                <td class="py-2 text-right text-gray-400 text-xs whitespace-nowrap">${formatTime(v.last_join)}</td>`;
            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        listWrap.innerHTML = '';
        listWrap.appendChild(table);
    }

    function highlight(text, query) {
        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-indigo-500/40 text-white rounded px-0.5">$1</mark>');
    }

    function formatDuration(minutes) {
        if (!minutes || minutes <= 0) return '—';
        const m = Math.round(parseFloat(minutes));
        if (m < 60) return `${m}m`;
        const h = Math.floor(m / 60);
        const rem = m % 60;
        return rem > 0 ? `${h}h ${rem}m` : `${h}h`;
    }

    function formatTime(ts) {
        if (!ts) return '—';
        const d = new Date(ts * 1000);
        return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function escHtml(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    load(active);
}
