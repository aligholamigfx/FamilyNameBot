<?php
// ============================================
// کلاس مدیریت کاربران
// ============================================

class UserManager {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * ثبت کاربر جدید
     */
    public function registerUser($userId, $username, $firstName, $lastName = '') {
        $exists = $this->db->selectOne(
            "SELECT id FROM users WHERE user_id = ?",
            "i",
            [$userId]
        );
        
        if ($exists) {
            return false;
        }
        
        return $this->db->insert('users', [
            'user_id' => $userId,
            'username' => $username ?? '',
            'first_name' => $firstName,
            'last_name' => $lastName ?? '',
            'balance' => '0',
            'premium_coins' => '0',
            'free_coins' => '0',
            'total_xp' => '0',
            'rank_id' => '1',
            'level' => '1',
            'games_played' => '0',
            'games_won' => '0',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * دریافت کاربر
     */
    public function getUser($userId) {
        return $this->db->selectOne(
            "SELECT * FROM users WHERE user_id = ?",
            "i",
            [$userId]
        );
    }
    
    /**
     * دریافت پروفایل کاربر
     */
    public function getUserProfile($userId) {
        $user = $this->getUser($userId);
        if (!$user) {
            return null;
        }
        
        $user['rank'] = RANKS[$user['rank_id']] ?? RANKS[1];
        $user['total_coins'] = $user['premium_coins'] + $user['free_coins'];
        $user['win_rate'] = $user['games_played'] > 0 
            ? round(($user['games_won'] / $user['games_played']) * 100, 1) 
            : 0;
        
        return $user;
    }
    
    /**
     * اضافه کردن سکه
     */
    public function addCoins($userId, $amount, $type = 'premium') {
        $user = $this->getUser($userId);
        if (!$user) {
            return false;
        }
        
        $column = $type === 'premium' ? 'premium_coins' : 'free_coins';
        $newAmount = $user[$column] + $amount;
        
        return $this->db->update('users',
            [$column => $newAmount],
            "user_id = $userId"
        );
    }
    
    /**
     * خرج کردن سکه
     */
    public function spendCoins($userId, $amount) {
        $user = $this->getUser($userId);
        if (!$user) {
            return false;
        }
        
        $total = $user['premium_coins'] + $user['free_coins'];
        if ($total < $amount) {
            return false;
        }
        
        // ابتدا پریمیوم خرج می‌شود
        $amountFromPremium = min($amount, $user['premium_coins']);
        $amountFromFree = $amount - $amountFromPremium;
        
        $newPremium = $user['premium_coins'] - $amountFromPremium;
        $newFree = $user['free_coins'] - $amountFromFree;
        
        return $this->db->update('users',
            ['premium_coins' => $newPremium, 'free_coins' => $newFree],
            "user_id = $userId"
        );
    }
    
    /**
     * اضافه کردن XP
     */
    public function addXP($userId, $amount) {
        $user = $this->getUser($userId);
        if (!$user) {
            return false;
        }
        
        $newXP = $user['total_xp'] + $amount;
        $newRank = $this->calculateRank($newXP);
        $rankChanged = $newRank !== $user['rank_id'];
        
        $this->db->update('users',
            ['total_xp' => $newXP, 'rank_id' => $newRank],
            "user_id = $userId"
        );
        
        return $rankChanged ? $newRank : false;
    }
    
    /**
     * محاسبه رتبه
     */
    private function calculateRank($xp) {
        $rank = 1;
        foreach (RANKS as $id => $info) {
            if ($xp >= $info['min_xp']) {
                $rank = $id;
            } else {
                break;
            }
        }
        return $rank;
    }
    
    /**
     * جدول رتبه‌بندی
     */
    public function getLeaderboard($limit = 10, $type = 'xp') {
        $orderBy = $type === 'xp' ? 'total_xp' : 'games_won';
        return $this->db->select(
            "SELECT user_id, username, first_name, total_xp, rank_id, games_won, games_played 
             FROM users 
             ORDER BY $orderBy DESC 
             LIMIT ?",
            "i",
            [$limit]
        );
    }
    
    /**
     * بهترین بازیکنان
     */
    public function getTopPlayers($limit = 5) {
        return $this->db->select(
            "SELECT user_id, username, first_name, rank_id, total_xp, games_won 
             FROM users 
             WHERE rank_id >= 5 
             ORDER BY total_xp DESC 
             LIMIT ?",
            "i",
            [$limit]
        );
    }
    
    /**
     * پیشرفت رتبه
     */
    public function getRankProgress($userId) {
        $user = $this->getUser($userId);
        if (!$user) {
            return null;
        }
        
        $currentRank = RANKS[$user['rank_id']];
        $nextRankId = $user['rank_id'] + 1;
        
        if (!isset(RANKS[$nextRankId])) {
            return [
                'current_rank' => $currentRank['name'],
                'current_icon' => $currentRank['icon'],
                'current_xp' => $user['total_xp'],
                'max_level' => true
            ];
        }
        
        $nextRank = RANKS[$nextRankId];
        $xpDiff = $nextRank['min_xp'] - $currentRank['min_xp'];
        $userProgress = $user['total_xp'] - $currentRank['min_xp'];
        $progressPercent = ($userProgress / $xpDiff) * 100;
        
        return [
            'current_rank' => $currentRank['name'],
            'current_icon' => $currentRank['icon'],
            'next_rank' => $nextRank['name'],
            'next_icon' => $nextRank['icon'],
            'current_xp' => $user['total_xp'],
            'next_rank_xp' => $nextRank['min_xp'],
            'progress_percent' => min($progressPercent, 100),
            'xp_needed' => $nextRank['min_xp'] - $user['total_xp']
        ];
    }
    
    /**
     * بروزرسانی آمار بازی
     */
    public function updateGameStats($userId, $won = false, $score = 0) {
        $user = $this->getUser($userId);
        if (!$user) {
            return false;
        }
        
        $newGamesPlayed = $user['games_played'] + 1;
        $newGamesWon = $user['games_won'] + ($won ? 1 : 0);
        
        return $this->db->update('users',
            ['games_played' => $newGamesPlayed, 'games_won' => $newGamesWon],
            "user_id = $userId"
        );
    }
    
    /**
     * افزایش سطح
     */
    public function levelUp($userId) {
        $user = $this->getUser($userId);
        if (!$user) {
            return false;
        }
        
        $newLevel = $user['level'] + 1;
        return $this->db->update('users',
            ['level' => $newLevel],
            "user_id = $userId"
        );
    }
    
    /**
     * دریافت موجودی
     */
    public function getBalance($userId) {
        $user = $this->getUser($userId);
        if (!$user) {
            return 0;
        }
        
        return $user['premium_coins'] + $user['free_coins'];
    }
    
    /**
     * بروزرسانی نام کاربری
     */
    public function updateUsername($userId, $username) {
        return $this->db->update('users',
            ['username' => $username],
            "user_id = $userId"
        );
    }
    
    /**
     * دریافت تمام کاربران
     */
    public function getAllUsers($limit = 100, $offset = 0) {
        return $this->db->select(
            "SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?",
            "ii",
            [$limit, $offset]
        );
    }
    
    /**
     * شمارش کل کاربران
     */
    public function getTotalUsers() {
        return $this->db->count('users');
    }
}

?>