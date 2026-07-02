# OpenFNO — Architecture & Design Notes

## 1. Repository Layout

Single Laravel 12 repo with Vue 3 via Vite (same shape as OpenVyapar), plus a standalone TypeScript payoff engine:

```
openfno/
├── app/
│   ├── Http/Controllers/Api/       # REST controllers (versioned: /api/v1)
│   ├── Models/                     # Trade, Position, Leg, Strategy, Instrument, ...
│   ├── Services/
│   │   ├── Brokers/                # BrokerAdapter interface + KiteAdapter, DhanAdapter, FyersAdapter
│   │   ├── MarketData/             # quote cache, option-chain snapshots, IV history collector
│   │   └── Margin/                 # broker basket-margin API wrapper (NOT home-grown SPAN)
│   └── Jobs/                       # nightly instrument sync, EOD snapshots, IV capture
├── database/migrations/
├── resources/js/                   # Vue 3 SPA
│   ├── pages/
│   ├── components/
│   │   ├── PayoffChart.vue
│   │   ├── OptionChain.vue
│   │   ├── StrategyBuilder.vue
│   │   ├── GreeksPanel.vue
│   │   └── TradeJournal/
│   └── services/                   # API client, WebSocket client
├── payoff-engine/                  # pure TypeScript, its own package.json + vitest
│   ├── src/
│   │   ├── black-scholes.ts        # pricing + implied vol (Newton-Raphson)
│   │   ├── greeks.ts               # delta, gamma, theta, vega, rho
│   │   ├── payoff.ts               # expiry payoff for arbitrary leg sets
│   │   ├── t0.ts                   # T+0 curve via BS repricing
│   │   ├── breakeven.ts            # root-finding on the payoff curve
│   │   └── strategies/             # iron-condor, straddle, strangle, spreads (leg templates)
│   └── tests/                      # golden-value tests against known BS results
└── docs/
```

**Why the engine is a separate TS package:** it runs in the browser (instant recalculation as the user drags strikes — no API round-trip), it's trivially unit-testable against textbook Black-Scholes values, and it can later power a mobile app unchanged. The backend never computes payoffs; it stores legs and serves market data.

## 2. Database Schema (core tables)

- `instruments` — contract master synced nightly from the broker instrument dump: symbol, underlying, expiry, strike, option_type, **lot_size**, tick_size, exchange token. *Lot sizes change; never hardcode them.*
- `expiry_calendar` — trading holidays + expiry dates per underlying. **NSE has changed weekly expiry days multiple times — keep this in data, not code.**
- `trades` — journal entry: underlying, opened_at, closed_at, status, notes (markdown), tags.
- `trade_legs` — belongs to trade: instrument_id, side (BUY/SELL), qty (in lots), entry_price, exit_price, entry/exit timestamps. Multi-leg = many rows.
- `trade_attachments` — screenshots (Laravel filesystem, works on VPS disk or S3).
- `strategies` — saved templates: name, leg definitions as relative rules (e.g. "sell 1× ~0.2Δ CE, buy 1× +200 CE") stored as JSON.
- `option_chain_snapshots` — EOD (and optionally intraday) chain per underlying/expiry: strike, LTP, OI, IV, volume. Feeds IV history and post-trade analysis.
- `iv_history` — daily ATM IV per underlying. **Needed for IV rank/percentile — you cannot backfill this from free sources, so the collector job must ship in Phase 1 even though the UI that uses it is Phase 4.**
- `broker_accounts` — encrypted API credentials per user, provider enum.
- `users` — from Breeze.

## 3. Market Data Flow

```
Kite Ticker (WebSocket) ──> Laravel queue worker ──> Redis (latest LTP per token)
                                                        │
                              Laravel Reverb <──────────┘ (throttled ~1s broadcasts)
                                    │
                              Vue frontend (subscribes only to tokens on screen)
```

- One ticker connection per server (Kite allows 3,000 tokens/connection), not per user.
- Redis holds "current price" state; the DB is never written on tick.
- Frontend subscribes/unsubscribes channels as the user changes underlying/expiry — keeps broadcast volume tiny.
- Nightly jobs: instrument dump sync, EOD chain snapshot, IV history append, expiry-day settlement of open paper trades.

## 4. Design Decisions & India-Market Gotchas

1. **Margin: call the broker, don't implement SPAN.** Zerodha's basket margin API gives accurate combined margin with hedge benefit. Home-grown SPAN is a permanent maintenance tax and will always be slightly wrong.
2. **NSE scraping is a trap.** The NSE website actively blocks scrapers and changes cookies/endpoints. All market data comes from broker APIs. For no-broker users, provide manual entry + CSV import as the fallback, not scraping.
3. **T+0 curve needs an IV per leg.** Use each leg's current market IV (from chain snapshot / live quote), not one flat IV — otherwise T+0 lines are visibly wrong on skewed strikes. Let the user override IV and days-to-expiry with sliders ("what-if" mode).
4. **Use `qty` in lots, price in rupees, P&L computed as `(exit − entry) × lot_size × lots`.** Store lot_size **on the trade leg at entry time** (copy from instrument), because the instrument's lot size can change later and must not silently rewrite historical P&L.
5. **Timezone:** everything IST (`Asia/Kolkata`) at the edges, UTC in the DB. Expiry cutoff is 15:30 IST.
6. **API-first:** every screen consumes `/api/v1/...` (Sanctum-authenticated). The future mobile app gets the API for free, and it's what makes the project attractive as open source.
7. **Dark mode default.** Traders live in dark mode; Tailwind `dark:` variants from day one, not retrofitted.
8. **Probability of profit** = risk-neutral probability from the lognormal distribution implied by ATM IV — document clearly in the UI that it's model-based, not a promise.
9. **Charts:** ApexCharts for payoff/P&L. If the option-chain OI visualization gets heavy (100+ strikes live-updating), switch that one component to ECharts — don't switch everything.
10. **Licensing:** MIT or AGPL-3.0. AGPL protects against closed-source SaaS clones of your work; MIT maximizes adoption. Decide before the first public release.

## 5. Broker Adapter Interface

```php
interface BrokerAdapter {
    public function loginUrl(): string;
    public function exchangeToken(string $requestToken): BrokerSession;
    public function instruments(): iterable;         // contract master dump
    public function positions(): array;               // for auto-import
    public function quote(array $tokens): array;      // LTP, OI, IV where available
    public function basketMargin(array $legs): MarginResult;
}
```

Kite first (best docs, WebSocket, basket margin). Dhan and Fyers implement the same interface later. Broker credentials encrypted at rest with Laravel's `encrypted` cast.

## 6. Phase Ordering Rationale

Journal (Phase 1) before builder/charts because it works with **zero market data** — pure CRUD, shippable fast, immediately useful. The IV-history collector job also starts here so Phase 4 has months of data waiting. Payoff engine (Phases 2–3) is pure math, fully testable offline with hardcoded chains. Broker integration (Phase 5) last because it has the most external friction (API keys, rate limits, TOTP login flows).

## 7. Testing Strategy

- `payoff-engine`: vitest golden tests — BS prices/Greeks vs. published values, breakevens of known strategies (e.g. short straddle breakevens = strike ± total premium).
- Laravel: Pest feature tests for API endpoints; fake broker adapter for integration tests.
- One end-to-end smoke: create trade → add 4 legs → payoff endpoint returns correct max profit/loss for a known iron condor.
