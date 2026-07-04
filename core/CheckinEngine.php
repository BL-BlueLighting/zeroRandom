<?php
class CheckinEngine {
    const DAILY_REWARD = 100;

    public static function canCheckin(int $userId): bool {
        $db = Database::getInstance();
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT id FROM daily_checkins WHERE user_id = ? AND checkin_date = ?");
        $stmt->execute([$userId, $today]);
        return !$stmt->fetch();
    }

    public static function checkin(int $userId): array {
        if (!self::canCheckin($userId)) {
            return ['success' => false, 'message' => '今天已经签到过了！'];
        }
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO daily_checkins (user_id, checkin_date) VALUES (?, ?)")
                ->execute([$userId, date('Y-m-d')]);
            $stmt = $db->prepare("UPDATE users SET token_balance = token_balance + ?, total_earned = total_earned + ? WHERE id = ?");
            $stmt->execute([self::DAILY_REWARD, self::DAILY_REWARD, $userId]);
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, total_amount, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, 'checkin', self::DAILY_REWARD, '每日签到']);
            $db->commit();
            return ['success' => true, 'message' => '签到成功！获得 ' . self::DAILY_REWARD . ' 枚代币。'];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => '签到失败: ' . $e->getMessage()];
        }
    }

    public static function getCheckinStats(int $userId): array {
        $db = Database::getInstance();
        $total = $db->prepare("SELECT COUNT(*) FROM daily_checkins WHERE user_id = ?");
        $total->execute([$userId]);
        $streak = 0;
        $d = new DateTime();
        while (true) {
            $stmt = $db->prepare("SELECT id FROM daily_checkins WHERE user_id = ? AND checkin_date = ?");
            $stmt->execute([$userId, $d->format('Y-m-d')]);
            if ($stmt->fetch()) { $streak++; $d->modify('-1 day'); }
            else break;
        }
        return ['total' => (int)$total->fetchColumn(), 'streak' => $streak];
    }
}
