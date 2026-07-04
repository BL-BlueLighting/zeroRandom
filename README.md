# 🎰 zeroRandom — OIManka

> 股票交易 × 扭蛋抽卡 × OJ 积分联动

zeroRandom（又名 OIManka）是一个结合 **股票模拟交易**、**扭蛋抽卡** 和 **OJ 做题积分** 的 Web 小游戏。绑定 HustOJ 账号后，AC 题目自动转化为代币，题目本身变成可交易的股票，让刷题的过程更有趣。

---

## ✨ 功能

| 模块 | 说明 |
|------|------|
| 📈 **股市** | OJ 题目映射为股票，AC/提交比率驱动股价，支持买入/卖出 |
| 🎲 **扭蛋抽卡** | 消耗代币抽取随机卡片（单抽/十连/百连），有保底机制 |
| 🎯 **卡池系统** | 多个卡池可配置，支持常驻池、节日限定绝版池 |
| 🔄 **卡牌市场** | P2P 卡牌交易，玩家间自由买卖 |
| 📦 **持仓 & 展示台** | 管理持有的卡牌，放置到展示台享受被动收益 |
| 💰 **提现** | 将持仓盈利提取为代币 |
| 📅 **每日签到** | 每日领取代币奖励 |
| 🎯 **任务 & 成就** | 可配置的每日任务和成就系统 |
| 🔗 **OJ 联动** | 支持 HustOJ / HydroOJ，自动同步 AC 数据，1 AC = 10 代币 |
| ⭐ **绝版卡牌** | 节日限定卡池，过期后变为绝版，100% 上涨 + 高增幅 |
| 👤 **个人主页** | 展示持有卡片和成就 |
| 🏆 **排行榜** | 按总资产排名 |
| 💸 **转账** | 玩家间代币转账 |

---

## 🚀 部署

### 环境要求

- PHP ≥ 7.4
- SQLite3 + `pdo_sqlite` 扩展
- （可选）MySQL + `pdo_mysql`（用于连接 HustOJ）

### 安装

```bash
# 克隆仓库
git clone https://github.com/BL-BlueLighting/zeroRandom.git
cd zeroRandom

# 配置
vim config.php   # 修改 APP_URL、DB_INIT_KEY 等

# 访问安装页面
# 浏览器打开 http://your-domain/setup.php
# 输入 DB_INIT_KEY 初始化数据库
```

### OJ 配置

支持 **HustOJ** 和 **HydroOJ** 两种 OJ 平台，二者仅可启用一个。
在管理后台填入对应平台的 MySQL 连接信息，即可同步题目作为股票。

---

## ⚙️ 配置

`config.php` 中的主要配置项：

| 配置 | 默认值 | 说明 |
|------|--------|------|
| `APP_URL` | `http://localhost:8000` | 部署的根 URL |
| `DB_INIT_KEY` | `oimankaconfigkey` | 数据库初始化密钥 |
| `STARTER_TOKENS` | `100` | 新用户起始代币 |
| `TOKENS_PER_AC` | `10` | 每个 AC 转化代币数 |
| `GACHA_SINGLE_COST` | `10` | 单抽消耗代币 |

---

## 📁 项目结构

```
├── index.php            # 首页
├── config.php           # 配置
├── bootstrap.php        # 公共加载
├── init_check.php       # 数据库初始化检测
├── login.php/register.php/logout.php  # 认证
├── market*.php          # 股市
├── gacha*.php           # 抽卡
├── portfolio*.php       # 持仓
├── card_market*.php     # 卡牌市场
├── quests.php           # 任务
├── checkin.php          # 签到
├── profile.php          # 个人主页
├── ranking.php          # 排行榜
├── transfer.php         # 转账
├── admin*.php           # 管理后台
├── bind*.php            # OJ 绑定
├── pool_manager.php     # 卡池管理
├── pool_edit.php        # 卡池选题
├── setup.php            # 数据库安装/迁移
├── help.php             # 帮助中心
│
├── core/                # 核心引擎
│   ├── Database.php     # SQLite 数据库层
│   ├── Session.php      # 会话管理
│   ├── TokenSystem.php  # 代币系统
│   ├── StockEngine.php  # 股票引擎
│   ├── TradingEngine.php# 交易引擎
│   ├── GachaEngine.php  # 抽卡引擎
│   ├── PoolEngine.php   # 卡池引擎
│   ├── MarketEngine.php # 卡牌市场引擎
│   ├── CheckinEngine.php# 签到引擎
│   └── QuestEngine.php  # 任务引擎
│
├── adapters/            # 平台适配器
│   ├── HustojAdapter.php # HustOJ 适配器
│   └── HydrojAdapter.php # HydroOJ 适配器
│
├── templates/           # 页面模板
│   └── layout/          # 布局组件
│
├── assets/              # 静态资源
│   └── css/style.css    # 样式
│
└── data/                # SQLite 数据库文件（自动生成）
```

---

## 📜 许可

Copyright © 2024-2026 BL.BlueLighting

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
