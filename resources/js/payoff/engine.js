/**
 * OpenFNO payoff engine — pure functions, no framework imports.
 * Will be extracted into the standalone `payoff-engine/` TypeScript package
 * in Phase 2; keep it dependency-free.
 *
 * A leg: { instrument_type: 'CE'|'PE'|'FUT', strike, side: 'BUY'|'SELL',
 *          lots, lot_size, entry_price }
 * Quantities: qty = lots × lot_size. P&L is in rupees.
 */

/** Expiry P&L of a single leg at underlying price `spot`. */
export function legPayoffAtExpiry(leg, spot) {
    const qty = leg.lots * leg.lot_size;
    const dir = leg.side === 'BUY' ? 1 : -1;
    const entry = Number(leg.entry_price);

    if (leg.instrument_type === 'FUT') {
        return (spot - entry) * dir * qty;
    }

    const strike = Number(leg.strike);
    const intrinsic =
        leg.instrument_type === 'CE'
            ? Math.max(spot - strike, 0)
            : Math.max(strike - spot, 0);

    return (intrinsic - entry) * dir * qty;
}

/** Total expiry P&L of all legs at `spot`. */
export function payoffAtExpiry(legs, spot) {
    return legs.reduce((sum, leg) => sum + legPayoffAtExpiry(leg, spot), 0);
}

/** True when the leg has the fields the math needs. */
export function isComputableLeg(leg) {
    if (!leg) return false;
    const hasPrice = leg.entry_price !== '' && leg.entry_price !== null && !Number.isNaN(Number(leg.entry_price));
    const hasQty = Number(leg.lots) >= 1 && Number(leg.lot_size) >= 1;
    if (!hasPrice || !hasQty) return false;
    if (leg.instrument_type === 'FUT') return true;
    return leg.strike !== '' && leg.strike !== null && Number(leg.strike) > 0;
}

/**
 * Price range that keeps every strike (or futures entry) in frame
 * with breathing room on both sides.
 */
export function defaultRange(legs) {
    const refs = legs.map((l) =>
        l.instrument_type === 'FUT' ? Number(l.entry_price) : Number(l.strike),
    );
    const lo = Math.min(...refs);
    const hi = Math.max(...refs);
    const span = Math.max(hi - lo, hi * 0.05);
    return { min: Math.max(lo - span * 0.6, 0), max: hi + span * 0.6 };
}

/**
 * Full expiry payoff analysis.
 * Returns { curve: [{x, y}], breakevens: number[],
 *           maxProfit: number|'unlimited', maxLoss: number|'unlimited' }
 */
export function analyzeExpiryPayoff(legs, { min, max, steps = 240 } = {}) {
    const computable = legs.filter(isComputableLeg);
    if (computable.length === 0) return null;

    if (min === undefined || max === undefined) {
        ({ min, max } = defaultRange(computable));
    }

    const step = (max - min) / steps;
    const curve = [];
    for (let i = 0; i <= steps; i++) {
        const x = min + i * step;
        curve.push({ x: round2(x), y: round2(payoffAtExpiry(computable, x)) });
    }

    // Breakevens: sign changes on the curve, refined by linear interpolation.
    // The curve is piecewise linear between strikes, so interpolation between
    // sample points is exact once the samples straddle the root.
    const breakevens = [];
    for (let i = 1; i < curve.length; i++) {
        const a = curve[i - 1];
        const b = curve[i];
        if (a.y === 0) breakevens.push(a.x);
        if (a.y * b.y < 0) {
            breakevens.push(round2(a.x - (a.y * (b.x - a.x)) / (b.y - a.y)));
        }
    }

    // Beyond the highest strike the payoff is linear and spot is unbounded,
    // so a nonzero right-edge slope means unlimited profit or loss. On the
    // left, spot is floored at 0: the true extreme is payoff at spot = 0.
    const last = curve[curve.length - 1];
    const prev = curve[curve.length - 2];
    const rightSlope = (last.y - prev.y) / (last.x - prev.x);
    const atZero = round2(payoffAtExpiry(computable, 0));

    const ys = [...curve.map((p) => p.y), atZero];
    let maxProfit = Math.max(...ys);
    let maxLoss = Math.min(...ys);

    if (rightSlope > 1e-9) maxProfit = 'unlimited';
    if (rightSlope < -1e-9) maxLoss = 'unlimited';

    return {
        curve,
        breakevens: dedupe(breakevens),
        maxProfit: maxProfit === 'unlimited' ? maxProfit : round2(maxProfit),
        maxLoss: maxLoss === 'unlimited' ? maxLoss : round2(maxLoss),
    };
}

function round2(n) {
    return Math.round(n * 100) / 100;
}

function dedupe(values, tolerance = 0.5) {
    const out = [];
    for (const v of values.sort((a, b) => a - b)) {
        if (out.length === 0 || Math.abs(v - out[out.length - 1]) > tolerance) {
            out.push(v);
        }
    }
    return out;
}
