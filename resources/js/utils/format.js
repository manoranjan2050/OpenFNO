const inrFormatter = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
});

export function inr(value) {
    if (value === null || value === undefined || value === '') return '—';
    return inrFormatter.format(Number(value));
}

export function pnlClass(value) {
    const n = Number(value);
    if (value === null || value === undefined || Number.isNaN(n)) return 'text-gray-400';
    if (n > 0) return 'text-green-500';
    if (n < 0) return 'text-red-500';
    return 'text-gray-400';
}

export function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Kolkata',
    });
}

export function fmtDateTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Kolkata',
    });
}

/** ISO string → value usable in <input type="datetime-local"> (local time). */
export function toLocalInput(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Describes a leg like "SELL 25500 CE ×1" */
export function legLabel(leg) {
    const strike = leg.instrument_type === 'FUT' ? 'FUT' : `${Number(leg.strike)} ${leg.instrument_type}`;
    return `${leg.side} ${strike} ×${leg.lots}`;
}
