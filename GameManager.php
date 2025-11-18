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
            'score' => '0',
            'joined_at' => date('Y-m-d H:i:s')
        ]);
        
        return ['game_id' => $gameId, 'words' => $words];
    }
    
    /**
     * ایجاد بازی چند‌نفره
     */
    public function createMultiplayerGame($userId, $groupId = null) {
        $gameId = uniqid('game_');
        $type = $groupId ? 'group' : 'multi';
        
        $this->db->insert('games', [
            'game_id' => $gameId,
            'type' => $type,
            'creator_id' => $userId,
            'group_id' => $groupId,
            'status' => 'waiting',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->db->insert('game_players', [
            'game_id' => $gameId,
            'user_id' => $userId,
            'score' => '0',
            'joined_at' => date('Y-m-d H:i:s')
        ]);
        
        return $gameId;
    }
    
    /**
     * پیوستن به بازی
     */
    public function joinGame($gameId, $userId) {
        $game = $this->db->selectOne(
            "SELECT * FROM games WHERE game_id = ?",
            "s",
            [$gameId]
        );
        
        if (!$game || $game['status'] !== 'waiting') {
            return false;
        }
        
        $playerCount = $this->db->selectOne(
            "SELECT COUNT(*) as count FROM game_players WHERE game_id = ?",
            "s",
            [$gameId]
        );
        
        if ($playerCount['count'] >= MAX_PLAYERS_GROUP) {
            return false;
        }
        
        // بررسی آیا کاربر قبلاً در این بازی است
        $exists = $this->db->selectOne(
            "SELECT id FROM game_players WHERE game_id = ? AND user_id = ?",
            "si",
            [$gameId, $userId]
        );
        
        if ($exists) {
            return false;
        }
        
        return $this->db->insert('game_players', [
            'game_id' => $gameId,
            'user_id' => $userId,
            'score' => '0',
            'joined_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * پایان دادن به بازی تک‌نفره
     */
    public function finishSinglePlayerGame($gameId, $userId, $score) {
        $game = $this->db->selectOne(
            "SELECT * FROM games WHERE game_id = ?",
            "s",
            [$gameId]
        );
        
        if (!$game) {
            return false;
        }
        
        // محاسبه پاداش
        $baseReward = $score * BASE_COIN_MULTIPLIER;
        $xpReward = floor($score * 2);
        $coinBonus = floor($score / 10);
        
        // اضافه کردن سکه‌های رایگان
        $this->userManager->addCoins($userId, $coinBonus, 'free');
        
        // اضافه کردن XP و بررسی رتبه‌آپ
        $newRank = $this->userManager->addXP($userId, $xpReward);
        
        // ثبت نتیجه بازی
        $this->db->update('games',
            ['status' => 'finished', 'winner_id' => $userId, 'total_prize' => $baseReward],
            "game_id = '$gameId'"
        );
        
        $this->db->update('game_players',
            ['score' => $score, 'is_winner' => '1', 'finished_at' => date('Y-m-d H:i:s')],
            "game_id = '$gameId' AND user_id = $userId"
        );
        
        // بروزرسانی آمار کاربر
        $user = $this->userManager->getUser($userId);
        $this->db->update('users',
            ['games_played' => $user['games_played'] + 1, 'games_won' => $user['games_won'] + 1],
            "user_id = $userId"
        );
        
        return [
            'score' => $score,
            'coins' => $coinBonus,
            'xp' => $xpReward,
            'rank_up' => $newRank
        ];
    }
    
    /**
     * پایان دادن به بازی چند‌نفره
     */
    public function finishMultiplayerGame($gameId, $winnerId, $scores = []) {
        $game = $this->db->selectOne(
            "SELECT * FROM games WHERE game_id = ?",
            "s",
            [$gameId]
        );
        
        if (!$game) {
            return false;
        }
        
        // ثبت نتیجه
        $this->db->update('games',
            ['status' => 'finished', 'winner_id' => $winnerId],
            "game_id = '$gameId'"
        );
        
        // بروزرسانی نتایج برای تمام بازیکنان
        $players = $this->db->select(
            "SELECT user_id FROM game_players WHERE game_id = ?",
            "s",
            [$gameId]
        );
        
        foreach ($players as $player) {
            $userId = $player['user_id'];
            $score = $scores[$userId] ?? 0;
            $isWinner = $userId === $winnerId ? 1 : 0;
            
            // محاسبه پاداش
            $xpReward = $isWinner ? WIN_POINTS * 3 : LOSS_POINTS;
            $coinBonus = $isWinner ? floor($score / 5) : 0;
            
            // اضافه کردن پاداش
            if ($coinBonus > 0) {
                $this->userManager->addCoins($userId, $coinBonus, 'free');
            }
            $this->userManager->addXP($userId, $xpReward);
            
            // بروزرسانی آمار
            $user = $this->userManager->getUser($userId);
            if ($isWinner) {
                $this->db->update('users',
                    ['games_played' => $user['games_played'] + 1, 'games_won' => $user['games_won'] + 1],
                    "user_id = $userId"
                );
            } else {
                $this->db->update('users',
                    ['games_played' => $user['games_played'] + 1],
                    "user_id = $userId"
                );
            }
            
            // بروزرسانی نتیجه بازی
            $this->db->update('game_players',
                ['score' => $score, 'is_winner' => $isWinner, 'finished_at' => date('Y-m-d H:i:s')],
                "game_id = '$gameId' AND user_id = $userId"
            );
        }
        
        return true;
    }
    
    /**
     * دریافت آمار بازی
     */
    public function getGameStats($userId) {
        $stats = $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_games,
                SUM(CASE WHEN is_winner = 1 THEN 1 ELSE 0 END) as wins,
                AVG(score) as avg_score,
                MAX(score) as best_score
             FROM game_players WHERE user_id = ?",
            "i",
            [$userId]
        );
        
        return $stats;
    }
    
    /**
     * دریافت بازی‌های فعال کاربر
     */
    public function getUserActiveGames($userId) {
        return $this->db->select(
            "SELECT g.*, COUNT(gp.id) as player_count 
             FROM games g
             LEFT JOIN game_players gp ON g.game_id = gp.game_id
             WHERE g.creator_id = ? AND g.status = 'waiting'
             GROUP BY g.game_id
             ORDER BY g.created_at DESC
             LIMIT 10",
            "i",
            [$userId]
        );
    }
    
    /**
     * دریافت تاریخچه بازی‌های کاربر
     */
    public function getUserGameHistory($userId, $limit = 20) {
        return $this->db->select(
            "SELECT g.*, gp.score, gp.is_winner
             FROM games g
             JOIN game_players gp ON g.game_id = gp.game_id
             WHERE gp.user_id = ? AND g.status = 'finished'
             ORDER BY g.finished_at DESC
             LIMIT ?",
            "ii",
            [$userId, $limit]
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
        
        return array_map(fn($w) => $w['word'], $words);
    }
    
    /**
     * بررسی و به‌روزرسانی وضعیت بازی‌های قدیمی
     */
    public function cleanupOldGames() {
        // حذف بازی‌هایی که بیش از 24 ساعت منتظر باقی‌مانده‌اند
        $this->db->delete(
            'games',
            "status = 'waiting' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        return true;
    }
    
    /**
     * دریافت اطلاعات بازی
     */
    public function getGame($gameId) {
        return $this->db->selectOne(
            "SELECT * FROM games WHERE game_id = ?",
            "s",
            [$gameId]
        );
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
    
    /**
     * شمارش بازی‌های فعال
     */
    public function getActiveGamesCount() {
        return $this->db->count('games', "status = 'active' OR status = 'waiting'");
    }
    
    /**
     * کل بازی‌های انجام‌شده
     */
    public function getTotalGames() {
        return $this->db->count('games', "status = 'finished'");
    }
}

?>