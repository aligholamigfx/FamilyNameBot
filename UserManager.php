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
        $exists = $this->db->selectOne("SELECT id FROM users WHERE user_id = ?", "i", [$userId]);

        if ($exists) {
            return false;
        }

        return $this->db->insert('users', [
            'user_id' => $userId,
            'username' => $username ?? '',
            'first_name' => $firstName,
            'last_name' => $lastName ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * دریافت کاربر
     */
    public function getUser($userId) {
        return $this->db->selectOne("SELECT * FROM users WHERE user_id = ?", "i", [$userId]);
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
        $column = $type === 'premium' ? 'premium_coins' : 'free_coins';
        return $this->db->incrementColumn('users', $column, $amount, "user_id = ?", "i", [$userId]);
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

        $amountFromPremium = min($amount, $user['premium_coins']);
        $amountFromFree = $amount - $amountFromPremium;

        $newPremium = $user['premium_coins'] - $amountFromPremium;
        $newFree = $user['free_coins'] - $amountFromFree;

        return $this->db->update('users',
            ['premium_coins' => $newPremium, 'free_coins' => $newFree],
            "user_id = ?", "i", [$userId]
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
        $rankChanged = $newRank !== (int)$user['rank_id'];

        $this->db->update('users',
            ['total_xp' => $newXP, 'rank_id' => $newRank],
            "user_id = ?", "i", [$userId]
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
        $allowed_types = ['xp' => 'total_xp', 'wins' => 'games_won'];
        $orderBy = $allowed_types[$type] ?? 'total_xp';

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
        $progressPercent = $xpDiff > 0 ? ($userProgress / $xpDiff) * 100 : 0;

        return [
            'current_rank' => $currentRank['name'],
            'current_icon' => $currentRank['icon'],
            'next_rank' => $nextRank['name'],
            'next_icon' => $nextRank['icon'],
            'current_xp' => $user['total_xp'],
            'next_rank_xp' => $nextRank['min_xp'],
            'progress_percent' => round(min($progressPercent, 100)),
            'xp_needed' => max(0, $nextRank['min_xp'] - $user['total_xp'])
        ];
    }

    /**
     * بروزرسانی آمار بازی
     */
    public function updateGameStats($userId, $won = false) {
        $updates = ['games_played' => 'games_played + 1'];
        if ($won) {
            $updates['games_won'] = 'games_won + 1';
        }

        $setClauses = [];
        foreach ($updates as $col => $expr) {
            $setClauses[] = "$col = $expr";
        }
        $setClause = implode(', ', $setClauses);

        $sql = "UPDATE users SET $setClause WHERE user_id = ?";
        return $this->db->query($sql, "i", [$userId]);
    }
}
