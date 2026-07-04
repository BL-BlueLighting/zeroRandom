<?php
class QuestEngine {

    // Update progress for a user on a specific condition type
    public static function updateProgress(int $userId, string $conditionType, float $amount = 1): void {
        $db = Database::getInstance();
        $quests = $db->prepare("
            SELECT qc.* FROM quest_config qc
            WHERE qc.condition_type = ? AND qc.is_active = 1
        ");
        $quests->execute([$conditionType]);
        while ($q = $quests->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO user_quests (user_id, quest_id, progress, completed)
                VALUES (?, ?, ?, 0)
                ON CONFLICT(user_id, quest_id) DO UPDATE SET
                    progress = MIN(progress + ?, ?)
            ");
            $stmt->execute([$userId, $q['id'], $amount, $q['condition_value'], $amount, $q['condition_value']]);

            // Check completion
            $uq = $db->prepare("SELECT * FROM user_quests WHERE user_id = ? AND quest_id = ?");
            $uq->execute([$userId, $q['id']]);
            $row = $uq->fetch();
            if ($row && !$row['completed'] && $row['progress'] >= $q['condition_value']) {
                $db->prepare("UPDATE user_quests SET completed = 1, completed_at = datetime('now') WHERE user_id = ? AND quest_id = ?")
                    ->execute([$userId, $q['id']]);
                TokenSystem::add($userId, $q['reward_tokens'], 'quest_reward');
            }
        }
    }

    public static function getUserQuests(int $userId, string $type = 'daily'): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT qc.*, COALESCE(uq.progress, 0) as progress, COALESCE(uq.completed, 0) as completed
            FROM quest_config qc
            LEFT JOIN user_quests uq ON qc.id = uq.quest_id AND uq.user_id = ?
            WHERE qc.type = ? AND qc.is_active = 1
            ORDER BY qc.id ASC
        ");
        $stmt->execute([$userId, $type]);
        return $stmt->fetchAll();
    }

    public static function getAllConfig(): array {
        $db = Database::getInstance();
        return $db->query("SELECT * FROM quest_config ORDER BY type ASC, id ASC")->fetchAll();
    }

    public static function addQuest(string $type, string $name, string $desc, string $condType, float $condVal, float $reward): void {
        $db = Database::getInstance();
        $db->prepare("INSERT INTO quest_config (type, name, description, condition_type, condition_value, reward_tokens) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$type, $name, $desc, $condType, $condVal, $reward]);
    }

    public static function deleteQuest(int $id): void {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM user_quests WHERE quest_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM quest_config WHERE id = ?")->execute([$id]);
    }

    public static function toggleQuest(int $id): void {
        $db = Database::getInstance();
        $db->exec("UPDATE quest_config SET is_active = CASE WHEN is_active THEN 0 ELSE 1 END WHERE id = {$id}");
    }
}
