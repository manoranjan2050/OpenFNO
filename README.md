# OpenFNO

Open-source, self-hosted F&O analytics and trading journal — an Opstra-style dashboard for Indian options traders.

> Working name: **OpenFNO** (alternative candidate: OptionForge). Rename is a find-and-replace away.

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 12 (PHP 8.4) |
| Frontend | Vue 3 + Tailwind CSS + Vite (Inertia or SPA + REST) |
| Database | MariaDB / MySQL |
| Cache / Queues | Redis |
| Real-time push | Laravel Reverb (WebSocket) → Vue |
| Market data | Zerodha Kite Connect + Kite Ticker (later: Dhan, Fyers) |
| Charts | ApexCharts (payoff, P&L), ECharts for option-chain heatmaps if needed |
| Payoff engine | Pure TypeScript library (`payoff-engine/`), zero framework deps, unit-tested |
| Auth | Laravel Breeze + Sanctum tokens for the REST API |
| Deployment | Ubuntu VPS (Hostinger), nginx + php-fpm + supervisor |

## Why Laravel 12 (not plain PHP 8.4)

You already run OpenRetail and OpenVyapar on Laravel 12 + Vue 3 + MariaDB. Reusing that stack means shared patterns for auth, migrations, queues, API resources, and Docker setup — and code you can lift directly (user accounts, settings, file uploads for trade screenshots).

## Build Phases

1. **Phase 1 — Core Trading Journal**: auth, F&O trade entries, multi-leg positions, open/closed trades, P&L tracking, notes + screenshots.
2. **Phase 2 — Strategy Builder**: unlimited CE/PE buy/sell legs, saved templates, one-click Iron Condor / Straddle / Strangle / spreads, live payoff recalculation.
3. **Phase 3 — Payoff Charts**: expiry payoff, T+0 curve (Black-Scholes), break-even lines, max profit/loss, interactive strike markers.
4. **Phase 4 — Advanced Analytics**: Greeks, IV analysis (IV rank/percentile), probability of profit, margin estimation, performance dashboards.
5. **Phase 5 — Broker Integration**: Zerodha Kite first (auth, positions auto-import, live LTP via Ticker), then Dhan and Fyers behind a common `BrokerAdapter` interface.
6. **Phase 6 — Paper Trading** *(added)*: virtual execution against live prices so users can test strategies risk-free. Huge community-adoption feature and cheap once Phase 5 exists.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full design, directory layout, database schema plan, and India-market gotchas.

## Status

**Phase 1 complete + payoff charts** (2026-07-02):

- Trade journal: multi-leg CE/PE/FUT trades, open/close lifecycle with frozen realized P&L, notes, tags, screenshot uploads
- Dashboard: realized P&L, win rate, avg win/loss, equity curve, P&L by underlying
- Payoff engine: expiry payoff, breakevens, max profit/loss — charted on trade pages and as a live preview in the trade form
- 13 REST endpoints under `/api/v1` (Sanctum), covered by feature tests

### Run locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # demo login: demo@openfno.local / password
php artisan storage:link
npm run build                     # or `npm run dev` for HMR
php artisan serve --port=8090
```

> Serving on a port other than 8000? Set `SANCTUM_STATEFUL_DOMAINS` in `.env`
> (e.g. `localhost:8090,127.0.0.1:8090`) or the SPA's API calls return 401.

### Tests

```bash
php artisan test        # backend (34 tests)
npm run test:engine     # payoff math golden values (16 checks)
```
