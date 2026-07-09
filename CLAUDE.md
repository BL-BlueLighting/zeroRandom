# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

zeroRandom (OIManka) — 股票交易 × 扭蛋抽卡 × OJ 积分联动的 Web 小游戏。PHP + SQLite + 无框架。

## Key Architecture

### Request Flow
```
URL (e.g. /market.php) → Standalone PHP file → loads bootstrap (config + core + session) → loads template
```
- **无路由** — 每个页面是独立 `.php` 文件（不是通过 index.php 路由）
- **模板** — PHP 文件直接包含 `templates/layout/header.php` 和 `templates/layout/footer.php`

### Layer System (Kaleidoscope 天·界)
- `$_SESSION['layer']` — `'default'` 或 `'kaleidoscope'`
- `is_kaleidoscope()` — helpers.php 中的辅助函数
- 天界模式：白主题、独立 `kaleidoscope_balance`、只显示 fake stocks（`adapter_name='fake'`）
- 检测入口：`helpers.php`, `init_check.php`

### Core Engine Classes (`core/`)
| Class | Responsibility |
|-------|--------------|
| `Database` | SQLite PDO singleton, migrations via `CREATE TABLE IF NOT EXISTS` + `ALTER TABLE` column migrations |
| `Session` | Session wrapper, flash messages, auth helpers |
| `TokenSystem` | Token balance: `getBalance`, `add`, `spend`, `transfer` + kaleidoscope variants |
| `StockEngine` | Stock queries, price history, market summary, rarity recalculation |
| `TradingEngine` | Buy/sell logic, portfolio, withdraw profits |
| `GachaEngine` | Gacha pulls, rarity weights, pity system, pool pulls |
| `PoolEngine` | Card pool CRUD, limited edition pools, split pools |
| `MarketEngine` | P2P card market listings, buy/cancel |
| `CheckinEngine` | Daily check-in logic |
| `QuestEngine` | Quest/achievement progress tracking |
| `AutoJob` | Periodic tasks: pool expiry, online tracking, auto price refresh, holdings limit |

### Adapters (`adapters/`)
- `AdapterInterface` — contract for platform adapters
- `HustojAdapter` — MySQL-based HustOJ connection
- `HydrojAdapter` — HTTP API-based HydroOJ connection (currently removed)
- `manager.php` — `AdapterManager` registry/factory

### Database
- SQLite (`data/oimanka.db`), WAL mode
- Migrations in `Database::migrate()` — creates tables + runs `ALTER TABLE ADD COLUMN`
- `setup.php` — standalone migration tool with table/column structure detection

## Key Patterns

### Number Formatting (`nf()`)
- Defined in `helpers.php`, loaded via `init_check.php`
- Three modes: `wan` (万/亿), `4digit` (1,2345), `3digit` (1,234)
- Stored in `$_SESSION['number_style']`

### Flash Messages
```php
Session::flash('success', '消息内容');
// Displayed automatically in header.php
```

### Templates
- All templates in `templates/`, layouts in `templates/layout/`
- Template variables set in the entry PHP file, then `include` the template

## Commands

### Deploy
```bash
bash deploy.sh   # Uses sshpass, excludes config.php & data/
```

### Database Migration
```bash
# Browser: visit /setup.php, enter key from config.php
# CLI: php setup.php verifysetup, then refresh /setup.php
```

### Common Gotchas
- `config.php` is excluded from deploy — new constants must also go in `helpers.php`
- SQLite doesn't support nested transactions — use direct SQL instead of `TokenSystem::add()`/`spend()` within transactions
- Stock queries auto-filter to `adapter_name='fake'` when `$_SESSION['layer'] === 'kaleidoscope'`
