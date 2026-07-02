# OpenFNO — Claude Code Instructions

Open-source, self-hosted Opstra alternative: F&O trading journal, strategy builder, payoff charts, Greeks/IV analytics, and broker integrations for Indian markets (NSE).

## Stack
- Laravel 12 (PHP 8.4), MariaDB, Redis, Laravel Reverb for WebSocket push
- Vue 3 + Tailwind CSS + Vite frontend; ApexCharts for payoff/P&L charts
- `payoff-engine/` is a standalone pure-TypeScript package (Black-Scholes, Greeks, payoff math) with vitest tests — no framework imports allowed there
- REST API under `/api/v1`, Sanctum auth; every UI feature must be API-first

## Hard rules
- Never hardcode lot sizes, expiry weekdays, or holiday dates — they live in `instruments` and `expiry_calendar` tables
- Copy `lot_size` onto trade legs at entry time; historical P&L must never change when NSE revises lot sizes
- Margin comes from broker basket-margin APIs, never a home-grown SPAN implementation
- No NSE website scraping — broker APIs or manual/CSV entry only
- Money/prices: DECIMAL in DB, never float; timezone IST at edges, UTC in DB
- Dark mode is the default theme

## Reference
- Full design: `docs/ARCHITECTURE.md`
- Sibling projects with reusable Laravel 12 + Vue 3 patterns: `../OpenVyapar ERP`, `../OpenRetail ERP`
