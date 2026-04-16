export function initLiveVisitors(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Map of avatar_key → joined_at (Date)
    let visitors = new Map();
    let timerInterval = null;

    const badge = document.createElement('div');
    badge.className = 'flex items-center gap-2 mb-3';
    badge.innerHTML = `
        <span class="relative flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
        </span>
        <span id="live-count" class="text-sm font-medium text-green-400">0 online</span>`;
    container.appendChild(badge);

    const listEl = document.createElement('div');
    listEl.className = 'space-y-1 overflow-y-auto';
    listEl.style.maxHeight = '260px';
    container.appendChild(listEl);

    function fetchAndUpdate() {
        fetch('/api/live-visitors')
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                visitors = new Map(data.map(v => [v.avatar_key, { display_name: v.display_name, joined_at: new Date(v.joined_at * 1000) }]));
                renderList();
                document.getElementById('live-count').textContent = `${visitors.size} online`;
            })
            .catch(err => console.error('Failed to fetch live visitors:', err));
    }

    function renderList() {
        listEl.innerHTML = '';

        if (visitors.size === 0) {
            listEl.innerHTML = '<p class="text-gray-500 text-sm italic">No one is currently online.</p>';
            return;
        }

        // Sort by joined_at ascending (longest first)
        const sorted = [...visitors.entries()].sort((a, b) => a[1].joined_at - b[1].joined_at);

        sorted.forEach(([key, v]) => {
            const row = document.createElement('div');
            row.dataset.key = key;
            row.className = 'flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-gray-700/40 transition-colors';
            row.innerHTML = `
                <a href="/avatar/${key}" class="text-indigo-400 hover:underline text-sm font-medium truncate max-w-[60%]">
                    ${escHtml(v.display_name)}
                </a>
                <span class="timer text-xs font-mono text-green-400 shrink-0 ml-2" data-since="${v.joined_at.getTime()}"></span>`;
            listEl.appendChild(row);
        });

        updateTimers();
    }

    function updateTimers() {
        const now = Date.now();
        listEl.querySelectorAll('.timer').forEach(el => {
            const elapsed = Math.floor((now - parseInt(el.dataset.since)) / 1000);
            el.textContent = formatElapsed(elapsed);
        });
    }

    function formatElapsed(seconds) {
        if (seconds < 0) seconds = 0;
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) return `${h}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
        if (m > 0) return `${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
        return `${String(s).padStart(2,'0')}s`;
    }

    function escHtml(str) {
        return (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Initial load
    fetchAndUpdate();

    // Refresh visitor list every 10 seconds
    setInterval(fetchAndUpdate, 10_000);

    // Tick timers every second
    timerInterval = setInterval(updateTimers, 1000);

    return { refresh: fetchAndUpdate };
}
