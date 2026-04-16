/**
 * Converts all <time data-local> elements from UTC ISO 8601 (datetime attribute)
 * to the browser's local timezone on page load.
 *
 * Usage in Twig:
 *   <time datetime="{{ someUtcDateTime|date('Y-m-d\\TH:i:s\\Z') }}" data-local>
 *       {{ someUtcDateTime|date('Y-m-d H:i') }} UTC
 *   </time>
 */
export function localiseTimeTags(root = document) {
    root.querySelectorAll('time[data-local]').forEach(el => {
        const iso = el.getAttribute('datetime');
        if (!iso) return;
        const d = new Date(iso);
        if (isNaN(d)) return;
        el.textContent = d.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    });
}

document.addEventListener('DOMContentLoaded', () => localiseTimeTags());
