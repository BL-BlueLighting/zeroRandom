<?php
/**
 * zero Random - HustOJ Adapter
 *
 * Connects to a HustOJ MySQL database.
 * All config read from DB (set via admin panel).
 *
 * Mapping:
 *   problem table → stocks
 *   solution table → AC/submit counts
 *   users table → token earnings (1 AC = 10 tokens)
 */

class HustojAdapter implements AdapterInterface {

    private ?PDO $connection = null;
    private ?string $configError = null;

    public function getName(): string { return 'hustoj'; }
    public function getDisplayName(): string { return 'HustOJ'; }
    public function getDescription(): string {
        return '连接 HustOJ 判题系统数据库，将题目映射为股票，AC数量转为代币。';
    }

    /**
     * Read config from DB.
     */
    private function getConfig(): array {
        $host = platform_config('hustoj', 'db_host');
        if (!$host) {
            $this->configError = 'HustOJ 尚未配置。请在管理后台设置数据库连接信息。';
            throw new RuntimeException($this->configError);
        }
        return [
            'host' => $host,
            'port' => (int)platform_config('hustoj', 'db_port', '3306'),
            'dbname' => platform_config('hustoj', 'db_name', 'jol'),
            'user' => platform_config('hustoj', 'db_user', 'root'),
            'pass' => platform_config('hustoj', 'db_pass', ''),
        ];
    }

    private function connect(): PDO {
        if ($this->connection === null) {
            $c = $this->getConfig();
            try {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $c['host'], $c['port'], $c['dbname']);
                $this->connection = new PDO($dsn, $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]);
                $this->configError = null;
            } catch (PDOException $e) {
                $this->configError = "HustOJ 连接失败: " . $e->getMessage();
                throw new RuntimeException($this->configError);
            }
        }
        return $this->connection;
    }

    public function fetchStocks(array $options = []): array {
        $db = $this->connect();
        $limit = $options['limit'] ?? 200;
        $offset = $options['offset'] ?? 0;
        $category = $options['category'] ?? platform_config('hustoj', 'category_source', '');

        $where = "p.defunct = 'N'";
        $params = [];

        if (!empty($category)) {
            $where .= ' AND p.source = ?';
            $params[] = $category;
        }

        $sql = "
            SELECT p.problem_id, p.title, p.source, p.accepted, p.submit,
                   CASE WHEN p.submit > 0 THEN ROUND(p.accepted / p.submit, 4) ELSE 0 END AS ac_ratio
            FROM problem p
            WHERE {$where}
            ORDER BY p.problem_id ASC
            LIMIT ? OFFSET ?
        ";
        $stmt = $db->prepare($sql);
        $pi = 1;
        foreach ($params as $p) $stmt->bindValue($pi++, $p);
        $stmt->bindValue($pi++, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue($pi, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        $stocks = [];
        while ($row = $stmt->fetch()) {
            $stocks[] = [
                'symbol' => 'P' . $row['problem_id'],
                'name' => $row['title'],
                'adapter_key' => (string)$row['problem_id'],
                'category' => $row['source'] ?: '未分类',
                'ac_count' => (int)$row['accepted'],
                'submit_count' => (int)$row['submit'],
                'metadata' => [
                    'problem_id' => (int)$row['problem_id'],
                    'source' => $row['source'],
                    'ac_ratio' => (float)$row['ac_ratio'],
                ],
            ];
        }
        return $stocks;
    }

    public function calculatePrice(array $stockData): float {
        $acCount = max((int)($stockData['ac_count'] ?? 0), 0);
        $submitCount = max((int)($stockData['submit_count'] ?? 1), 1);
        $acRatio = $acCount / $submitCount;
        $basePrice = 10.0;
        $difficultyFactor = 1.0 + (1.0 - $acRatio) * 3.0;
        $popularityFactor = 0.5 + $acRatio * 1.5;
        return round(max($basePrice * $difficultyFactor * $popularityFactor * 5.0, 1.0), 2);
    }

    public function calculateTokens(array $platformUserData): float {
        return (int)($platformUserData['total_ac'] ?? 0) * TOKENS_PER_AC;
    }

    public function fetchUserData(string $platformUserId): ?array {
        $db = $this->connect();
        $sql = "
            SELECT u.user_id, u.nick,
                   COUNT(DISTINCT s.solution_id) AS total_submit,
                   COUNT(DISTINCT CASE WHEN s.result = 4 THEN s.solution_id END) AS total_ac
            FROM users u
            LEFT JOIN solution s ON s.user_id = u.user_id
            WHERE u.user_id = ?
            GROUP BY u.user_id, u.nick
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$platformUserId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return [
            'platform_user_id' => (string)$row['user_id'],
            'username' => $row['nick'],
            'total_ac' => (int)$row['total_ac'],
            'total_submit' => (int)$row['total_submit'],
            'metadata' => ['user_id' => (int)$row['user_id']],
        ];
    }

    public function resolveUser(string $identifier): ?array {
        $db = $this->connect();
        if (is_numeric($identifier)) {
            $stmt = $db->prepare("SELECT user_id, nick FROM users WHERE user_id = ?");
            $stmt->execute([(int)$identifier]);
        } else {
            $stmt = $db->prepare("SELECT user_id, nick FROM users WHERE nick = ?");
            $stmt->execute([$identifier]);
        }
        $row = $stmt->fetch();
        return $row ? $this->fetchUserData((string)$row['user_id']) : null;
    }

    public function syncStocks(): array {
        $localDb = Database::getInstance();
        $startTime = date('Y-m-d H:i:s');

        try {
            $platformStocks = $this->fetchStocks(['limit' => 99999]);
            $count = 0;

            $upsertStmt = $localDb->prepare("
                INSERT INTO stocks
                    (symbol, name, adapter_key, adapter_name, category, rarity,
                     total_supply, circulating_supply, base_price, current_price, prev_price,
                     volume_24h, market_cap, metadata, is_active)
                VALUES (?, ?, ?, 'hustoj', ?, 'common', 1000, ?, ?, ?, ?, 0, 0, ?, 1)
                ON CONFLICT(symbol) DO UPDATE SET
                    name = excluded.name, category = excluded.category,
                    base_price = excluded.base_price, current_price = excluded.current_price,
                    prev_price = stocks.current_price, metadata = excluded.metadata,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $priceStmt = $localDb->prepare("
                INSERT INTO stock_prices (stock_id, price, ac_ratio, submit_count, ac_count)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($platformStocks as $s) {
                $price = $this->calculatePrice($s);
                $circSupply = min(1000, $s['submit_count']);
                $metadata = json_encode($s['metadata'], JSON_UNESCAPED_UNICODE);
                $upsertStmt->execute([
                    $s['symbol'], $s['name'], $s['adapter_key'], $s['category'],
                    $circSupply, $price, $price, $metadata,
                ]);
                $stockId = $localDb->lastInsertId();
                if ($stockId == 0) {
                    $stockId = $localDb->prepare("SELECT id FROM stocks WHERE symbol = ?")->execute([$s['symbol']]) ? $localDb->query("SELECT id FROM stocks WHERE symbol = '{$s['symbol']}'")->fetchColumn() : 0;
                }
                $acRatio = $s['submit_count'] > 0 ? $s['ac_count'] / $s['submit_count'] : 0;
                $priceStmt->execute([$stockId, $price, round($acRatio, 4), $s['submit_count'], $s['ac_count']]);
                $count++;
            }

            $localDb->exec("
                UPDATE stocks SET price_change_pct = CASE WHEN prev_price > 0 THEN ROUND(((current_price - prev_price) / prev_price) * 100, 2) ELSE 0 END,
                market_cap = ROUND(current_price * circulating_supply, 2)
                WHERE adapter_name = 'hustoj'
            ");

            GachaEngine::recalculateRarityByHeat();

            $localDb->prepare("INSERT INTO sync_logs (adapter_name, status, items_synced, started_at, finished_at) VALUES ('hustoj', 'success', ?, ?, ?)")
                ->execute([$count, $startTime, date('Y-m-d H:i:s')]);

            return ['adapter' => 'hustoj', 'status' => 'success', 'items_synced' => $count];
        } catch (Exception $e) {
            $localDb->prepare("INSERT INTO sync_logs (adapter_name, status, items_synced, error_message, started_at, finished_at) VALUES ('hustoj', 'failed', 0, ?, ?, ?)")
                ->execute([$e->getMessage(), $startTime, date('Y-m-d H:i:s')]);
            return ['adapter' => 'hustoj', 'status' => 'failed', 'error' => $e->getMessage(), 'items_synced' => 0];
        }
    }

    public function syncUser(string $localUsername): bool {
        $localDb = Database::getInstance();
        $userStmt = $localDb->prepare("SELECT * FROM users WHERE username = ?");
        $userStmt->execute([$localUsername]);
        $localUser = $userStmt->fetch();
        if (!$localUser) return false;

        // Check binding
        $bindStmt = $localDb->prepare("SELECT * FROM user_hustoj_bindings WHERE user_id = ?");
        $bindStmt->execute([$localUser['id']]);
        $binding = $bindStmt->fetch();
        if (!$binding) return false;

        // Check cooldown
        $lastSync = strtotime($binding['last_synced_at'] ?? '2000-01-01');
        if (time() - $lastSync < SYNC_COOLDOWN_MINUTES * 60) return false;

        $platformData = $this->fetchUserData($binding['oj_user_id']);
        if (!$platformData) return false;

        $newAc = $platformData['total_ac'];
        $oldAc = (int)$binding['total_ac'];
        $diffAc = $newAc - $oldAc;

        if ($diffAc > 0) {
            $tokens = $diffAc * TOKENS_PER_AC;
            TokenSystem::add($localUser['id'], $tokens, 'reward');
        }

        $localDb->prepare("UPDATE user_hustoj_bindings SET total_ac = ?, last_synced_at = datetime('now') WHERE user_id = ?")
            ->execute([$newAc, $localUser['id']]);
        return true;
    }

    public function testConnection(): bool {
        try {
            $c = $this->getConfig();
            if (empty($c['host'])) return false;
            $db = $this->connect();
            $db->query("SELECT 1 FROM problem LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verify password against HUSTOJ users table.
     * HUSTOJ typically stores passwords as MD5 hashes.
     */
    public function verifyPassword(string $userId, string $password): bool {
        $db = $this->connect();
        $stmt = $db->prepare("SELECT `password` FROM `users` WHERE `user_id` = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) return false;
        function pwCheck($password, $saved)
        {
            $svd=base64_decode($saved);
            $salt=substr($svd,20);
            $password=md5($password);
            $hash = base64_encode( sha1(($password) . $salt, true) . $salt );
            if (hash_equals($hash,$saved)) return True;
            else return False;
        }
        $pwd = $row['password'];
        return pwCheck($password, $pwd);
    }

    /**
     * Send a station message (站内信) to a HUSTOJ user.
     *
     * Inserts a message into HUSTOJ's `mail` table so it appears
     * in the user's inbox on the OJ platform.
     *
     * @param string $toUserId HUSTOJ user_id (numeric)
     * @param string $title Message title
     * @param string $content Message body
     * @return bool Success
     */
    public function sendMail(string $toUserId, string $title, string $content): bool {
        $db = $this->connect();
        $stmt = $db->prepare("
            INSERT INTO `mail` (`to_user`, `from_user`, `title`, `content`, `new_mail`, `reply`, `in_date`, `defunct`)
            VALUES (?, 'OIManka', ?, ?, 1, 0, NOW(), 'N')
        ");
        return $stmt->execute([$toUserId, $title, $content]);
    }

    public function getConfigFields(): array {
        return [
            ['key' => 'db_host', 'label' => 'MySQL 主机', 'type' => 'text', 'required' => true],
            ['key' => 'db_port', 'label' => '端口', 'type' => 'number', 'required' => true],
            ['key' => 'db_name', 'label' => '数据库名', 'type' => 'text', 'required' => true],
            ['key' => 'db_user', 'label' => '用户名', 'type' => 'text', 'required' => true],
            ['key' => 'db_pass', 'label' => '密码', 'type' => 'password', 'required' => false],
            ['key' => 'oj_url', 'label' => 'OJ 网站地址', 'type' => 'text', 'required' => false],
            ['key' => 'category_source', 'label' => '题源分类 (可选)', 'type' => 'text', 'required' => false],
        ];
    }

    public function getConfigError(): ?string {
        return $this->configError;
    }
}
