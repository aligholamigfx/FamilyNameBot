<?php
// ============================================
// کلاس مدیریت بازی‌ها
// ============================================

class GameManager {
    private $db;
    private $telegram;
    private $userManager;

    public function __construct(Database $db, TelegramAPI $telegram, UserManager $userManager) {
        $this->db = $db;
        $this->telegram = $telegram;
        $this->userManager = $userManager;
    }

    /**
     * ایجاد بازی تک‌نفره
     */
    public function createSinglePlayerGame($userId) {
        $gameId = uniqid('game_');
        $words = $this->getRandomWords(5);

        $this->db->insert('games', [
            'game_id' => $gameId,
            'type' => 'single',
            'creator_id' => $userId,
            'status' => 'active',
            'words' => json_encode($words),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->db->insert('game_players', [
            'game_id' => $gameId,
            'user_id' => $userId,
            'joined_at' => date('Y-m-d H:i:s')
        ]);

        return ['game_id' => $gameId, 'words' => $words];
    }

    /**
     * پایان دادن به بازی تک‌نفره
     */
    public function finishSinglePlayerGame($gameId, $userId, $score) {
        $game = $this->getGame($gameId);
        if (!$game) {
            return false;
        }

        $baseReward = $score * BASE_COIN_MULTIPLIER;
        $xpReward = floor($score * 1.5);
        $coinBonus = floor($score / 10);

        if ($coinBonus > 0) {
            $this->userManager->addCoins($userId, $coinBonus, 'free');
        }

        $newRank = $this->userManager->addXP($userId, $xpReward);

        $this->db->update('games',
            ['status' => 'finished', 'winner_id' => $userId, 'total_prize' => $baseReward],
            "game_id = ?", "s", [$gameId]
        );

        $this->db->update('game_players',
            ['score' => $score, 'is_winner' => 1, 'finished_at' => date('Y-m-d H:i:s')],
            "game_id = ? AND user_id = ?", "si", [$gameId, $userId]
        );

        $this->userManager->updateGameStats($userId, true);

        return [
            'score' => $score,
            'coins' => $coinBonus,
            'xp' => $xpReward,
            'rank_up' => $newRank
        ];
    }

    /**
     * دریافت آمار بازی
     */
    public function getGameStats($userId) {
        return $this->db->selectOne(
            "SELECT
                COUNT(*) as total_games,
                SUM(is_winner) as wins,
                AVG(score) as avg_score,
                MAX(score) as best_score
             FROM game_players WHERE user_id = ?",
            "i",
            [$userId]
        );
    }

    /**
     * دریافت کلمات تصادفی
     */
    private function getRandomWords($count) {
        $words = $this->db->select(
            "SELECT word FROM words WHERE is_active = 1 ORDER BY RAND() LIMIT ?",
            "i",
            [$count]
        );

        return array_map(function($w) { return $w['word']; }, $words);
    }

    /**
     * بررسی و به‌روزرسانی وضعیت بازی‌های قدیمی
     */
    public function cleanupOldGames() {
        return $this->db->delete(
            'games',
            "status = 'waiting' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }

    /**
     * دریافت اطلاعات بازی
     */
    public function getGame($gameId) {
        return $this->db->selectOne("SELECT * FROM games WHERE game_id = ?", "s", [$gameId]);
    }

    /**
     * دریافت بازیکنان یک بازی
     */
    public function getGamePlayers($gameId) {
        return $this->db->select(
            "SELECT gp.*, u.first_name, u.username
             FROM game_players gp
             JOIN users u ON gp.user_id = u.user_id
             WHERE gp.game_id = ?
             ORDER BY gp.score DESC",
            "s",
            [$gameId]
        );
    }
}
