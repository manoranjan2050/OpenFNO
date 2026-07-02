/**
 * Golden-value tests for the payoff engine, checked against hand-calculated
 * numbers. Run with: npm run test:engine
 * (Plain node script for now; migrates to vitest when payoff-engine/ becomes
 * a standalone package in Phase 2.)
 */
import { analyzeExpiryPayoff, payoffAtExpiry } from '../../resources/js/payoff/engine.js';

const checks = [];

// Iron Condor, 2 lots × 75. Net credit/unit = 92.50 + 88.75 − 41.20 − 39.60 = 100.45
const ic = [
    { instrument_type: 'CE', strike: 25800, side: 'SELL', lots: 2, lot_size: 75, entry_price: 92.5 },
    { instrument_type: 'CE', strike: 26000, side: 'BUY', lots: 2, lot_size: 75, entry_price: 41.2 },
    { instrument_type: 'PE', strike: 25200, side: 'SELL', lots: 2, lot_size: 75, entry_price: 88.75 },
    { instrument_type: 'PE', strike: 25000, side: 'BUY', lots: 2, lot_size: 75, entry_price: 39.6 },
];
const r = analyzeExpiryPayoff(ic);
checks.push(
    ['IC max profit = credit × qty', r.maxProfit, 15067.5],
    ['IC max loss = (width − credit) × qty', r.maxLoss, -14932.5],
    ['IC has two breakevens', r.breakevens.length, 2],
    ['IC lower BE = short put − credit', r.breakevens[0], 25099.55],
    ['IC upper BE = short call + credit', r.breakevens[1], 25900.45],
    ['IC payoff between short strikes', payoffAtExpiry(ic, 25500), 15067.5],
    ['IC payoff below long put', payoffAtExpiry(ic, 24000), -14932.5],
    ['IC payoff above long call', payoffAtExpiry(ic, 27000), -14932.5],
);

// Long straddle: unlimited upside, loss bounded by total premium
const straddle = [
    { instrument_type: 'CE', strike: 25000, side: 'BUY', lots: 1, lot_size: 75, entry_price: 200 },
    { instrument_type: 'PE', strike: 25000, side: 'BUY', lots: 1, lot_size: 75, entry_price: 180 },
];
const s = analyzeExpiryPayoff(straddle);
checks.push(
    ['Straddle max profit unlimited', s.maxProfit, 'unlimited'],
    ['Straddle max loss = premium × qty', s.maxLoss, -28500],
    ['Straddle lower BE', s.breakevens[0], 24620],
    ['Straddle upper BE', s.breakevens[1], 25380],
);

// Short future: profit bounded at spot = 0, loss unlimited
const fut = [{ instrument_type: 'FUT', side: 'SELL', lots: 1, lot_size: 75, entry_price: 25480 }];
const f = analyzeExpiryPayoff(fut);
checks.push(
    ['Short FUT max profit bounded (spot floor 0)', f.maxProfit, 25480 * 75],
    ['Short FUT max loss unlimited', f.maxLoss, 'unlimited'],
    ['Short FUT payoff below entry', payoffAtExpiry(fut, 25400), 6000],
);

// Incomplete legs are ignored; nothing computable → null
checks.push(
    ['Incomplete legs → null analysis', analyzeExpiryPayoff([{ instrument_type: 'CE', strike: '', side: 'BUY', lots: 1, lot_size: 75, entry_price: '' }]), null],
);

let failed = 0;
for (const [name, got, want] of checks) {
    const ok = Object.is(got, want);
    if (!ok) failed++;
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}: got ${got}, want ${want}`);
}
console.log(failed === 0 ? `\nALL ${checks.length} PASS` : `\n${failed} FAILED`);
process.exit(failed === 0 ? 0 : 1);
