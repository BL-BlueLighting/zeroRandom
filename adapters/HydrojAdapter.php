<?php
/**
 * OIManka - HydroOJ Adapter
 *
 * Connects to HydroOJ via its REST API (MongoDB backend).
 * HydroOJ 使用 MongoDB，通过 HTTP API 获取数据。
 */

class HydrojAdapter implements AdapterInterface {

    private ?string $configError = null;

    public function getName(): string { return 'hydroj'; }
    public function getDisplayName(): string { return 'HydroOJ'; }
    public function getDescription(): string {
        return '通过 API 连接 HydroOJ，将题目映射为股票，AC数量转为代币。';
    }

    private function getConfig(): array {
        $url = platform_config('hydroj', 'oj_url');
        if (!$url) {
            $this->configError = 'HydroOJ 尚未配置。请在管理后台设置 OJ 网站地址。';
            throw new RuntimeException($this->configError);
        }
        return [
            'oj_url' => rtrim($url, '/'),
            'api_token' => platform_config('hydroj', 'api_token', ''),
            'category_source' => platform_config('hydroj', 'category_source', ''),
        ];
    }

    /**
     * HTTP GET request to HydroOJ API.
     */
    private function apiGet(string $path): ?array {
        $c = $this->getConfig();
        $url = $c['oj_url'] . $path;
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "Accept: application/json\r\n" . ($c['api_token'] ? "Authorization: Bearer {$c['api_token']}\r\n" : ''),
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        if ($result === false) {
            $this->configError = "HydroOJ API 请求失败: {$url}";
            return null;
        }
        $data = json_decode($result, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Fetch all problems via paginated API.
     */
    public function fetchStocks(array $options = []): array {
        $limit = $options['limit'] ?? 99999;
        $stocks = [];
        $page = 1;
        $pageSize = min(100, $limit);

        while (count($stocks) < $limit) {
            $data = $this->apiGet("/api/problem?page={$page}&size={$pageSize}");
            if (!$data) break;

            $problems = $data['data']['documents'] ?? $data['documents'] ?? $data['data'] ?? [];
            if (empty($problems)) break;

            foreach ($problems as $p) {
                $pid = $p['pid'] ?? $p['_id'] ?? 0;
                $title = $p['title'] ?? 'Unknown';
                if (!$pid) continue;

                $stocks[] = [
                    'symbol' => 'P' . $pid,
                    'name' => $title,
                    'adapter_key' => (string)$pid,
                    'category' => $p['domain'] ?? '未分类',
                    'ac_count' => (int)($p['nAccept'] ?? $p['ac_count'] ?? 0),
                    'submit_count' => max((int)($p['nSubmit'] ?? $p['submit_count'] ?? 1), 1),
                    'metadata' => [
                        'problem_id' => (int)$pid,
                        'domain' => $p['domain'] ?? '',
                    ],
                ];
            }

            if (count($problems) < $pageSize) break;
            $page++;
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

    /**
     * Fetch user data by UID or username via API.
     */
    public function fetchUserData(string $platformUserId): ?array {
        $data = $this->apiGet("/api/user/{$platformUserId}");
        if (!$data) {
            // Try as username lookup
            $data = $this->apiGet("/api/user?username=" . urlencode($platformUserId));
            if ($data && !empty($data['data']['documents'] ?? [])) {
                $u = $data['data']['documents'][0];
            } else {
                return null;
            }
        } else {
            $u = $data['data'] ?? $data['user'] ?? $data;
        }

        $uid = $u['uid'] ?? $u['_id'] ?? 0;
        $uname = $u['uname'] ?? $u['username'] ?? '';

        if (!$uid) return null;

        // Try to get submission stats
        $ac = (int)($u['nAccept'] ?? $u['ac_count'] ?? 0);
        $submit = (int)($u['nSubmit'] ?? $u['submit_count'] ?? 0);

        // If stats not in profile, fetch from record
        if ($submit === 0) {
            $recData = $this->apiGet("/api/record?uid={$uid}&limit=1&count=true");
            if ($recData) {
                $submit = (int)($recData['count'] ?? 0);
                // Get AC count separately
                $acData = $this->apiGet("/api/record?uid={$uid}&status=0&limit=1&count=true");
                if ($acData) {
                    $ac = (int)($acData['count'] ?? 0);
                }
            }
        }

        return [
            'platform_user_id' => (string)$uid,
            'username' => $uname,
            'total_ac' => $ac,
            'total_submit' => $submit,
            'metadata' => ['uid' => (int)$uid],
        ];
    }

    public function resolveUser(string $identifier): ?array {
        return $this->fetchUserData($identifier);
    }

    /**
     * Verify password via HydroOJ login API.
     */
    public function verifyPassword(string $userId, string $password): bool {
        $c = $this->getConfig();
        $url = $c['oj_url'] . '/api/login';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 10,
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode([
                    'uid' => (int)$userId,
                    'password' => $password,
                ]),
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        if ($result === false) return false;
        $data = json_decode($result, true);
        return isset($data['token']) || isset($data['data']['token']);
    }

    /**
     * Send station message via HydroOJ API (if supported).
     */
    public function sendMail(string $toUserId, string $title, string $content): bool {
        $c = $this->getConfig();
        // HydroOJ may support POST /api/message or similar
        $url = $c['oj_url'] . '/api/message';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 10,
                'header' => "Content-Type: application/json\r\n" . ($c['api_token'] ? "Authorization: Bearer {$c['api_token']}\r\n" : ''),
                'content' => json_encode([
                    'recipient' => (int)$toUserId,
                    'title' => $title,
                    'content' => $content,
                ]),
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        return $result !== false;
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
                VALUES (?, ?, ?, 'hydroj', ?, 'common', 1000, ?, ?, ?, ?, 0, 0, ?, 1)
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
                    $stockId = $localDb->query("SELECT id FROM stocks WHERE symbol = '{$s['symbol']}'")->fetchColumn();
                }
                $acRatio = $s['submit_count'] > 0 ? $s['ac_count'] / $s['submit_count'] : 0;
                $priceStmt->execute([$stockId, $price, round($acRatio, 4), $s['submit_count'], $s['ac_count']]);
                $count++;
            }

            $localDb->exec("
                UPDATE stocks SET price_change_pct = CASE WHEN prev_price > 0 THEN ROUND(((current_price - prev_price) / prev_price) * 100, 2) ELSE 0 END,
                market_cap = ROUND(current_price * circulating_supply, 2)
                WHERE adapter_name = 'hydroj'
            ");

            GachaEngine::recalculateRarityByHeat();

            $localDb->prepare("INSERT INTO sync_logs (adapter_name, status, items_synced, started_at, finished_at) VALUES ('hydroj', 'success', ?, ?, ?)")
                ->execute([$count, $startTime, date('Y-m-d H:i:s')]);

            return ['adapter' => 'hydroj', 'status' => 'success', 'items_synced' => $count];
        } catch (Exception $e) {
            $localDb->prepare("INSERT INTO sync_logs (adapter_name, status, items_synced, error_message, started_at, finished_at) VALUES ('hydroj', 'failed', 0, ?, ?, ?)")
                ->execute([$e->getMessage(), $startTime, date('Y-m-d H:i:s')]);
            return ['adapter' => 'hydroj', 'status' => 'failed', 'error' => $e->getMessage(), 'items_synced' => 0];
        }
    }

    public function syncUser(string $localUsername): bool {
        $localDb = Database::getInstance();
        $userStmt = $localDb->prepare("SELECT * FROM users WHERE username = ?");
        $userStmt->execute([$localUsername]);
        $localUser = $userStmt->fetch();
        if (!$localUser) return false;

        $bindStmt = $localDb->prepare("SELECT * FROM user_hustoj_bindings WHERE user_id = ?");
        $bindStmt->execute([$localUser['id']]);
        $binding = $bindStmt->fetch();
        if (!$binding) return false;

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
            $data = $this->apiGet('/api/problem?size=1');
            return $data !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getConfigFields(): array {
        return [
            ['key' => 'oj_url', 'label' => 'OJ 网站地址', 'type' => 'text', 'required' => true],
            ['key' => 'api_token', 'label' => 'API Token (可选)', 'type' => 'password', 'required' => false],
            ['key' => 'category_source', 'label' => '域 (可选)', 'type' => 'text', 'required' => false],
        ];
    }

    public function getConfigError(): ?string {
        return $this->configError;
    }
}
