<?php
// ============================================
// کلاس مدیریت دستیابی‌ها
// ============================================

class AchievementManager {
    private $db;
    private $userManager;
    
    public function __construct(Database $db, UserManager $userManager) {
        $this->db = $db;
        $this->userManager = $userManager;
    }
    
    /**
     * تعریف دستیابی‌های موجود
     */
    public function getAllAchievements() {
        return [
            1 => [
                'name' => 'شروع‌کننده',
                'description' => 'اولین بازی‌ات را شروع کن',
                'icon' => '🎮',
                'type' => 'games_played',
                'requirement' => 1,
                'reward_points' => 10
            ],
            2 => [
                'name' => 'شیطان‌برتر',
                'description' => '10 بازی برتری داشته باش',
                'icon' => '🔥',
                'type' => 'games_won',
                'requirement' => 10,
                'reward_points' => 50
            ],
            3 => [
                'name' => 'امپراطور',
                'description' => '50 بازی برتری داشته باش',
                'icon' => '👑',
                'type' => 'games_won',
                'requirement' => 50,
                'reward_points' => 200
            ],
            4 => [
                'name' => 'دونده‌ی چاپ‌تخت',
                'description' => '100 بازی انجام داده باش',
                'icon' => '🏃',
                'type' => 'games_played',
                'requirement' => 100,
                'reward_points' => 100
            ],
            5 => [
                'name' => 'سیکل‌زن خستگی‌ناپذیر',
                'description' => '500 بازی انجام داده باش',
                'icon' => '🚴',
                'type' => 'games_played',
                'requirement' => 500,
                'reward_points' => 300
            ],
            6 => [
                'name' => 'کسب‌و‌کار خوب',
                'description' => '1000 سکه خرج کن',
                'icon' => '💰',
                'type' => 'coins_spent',
                'requirement' => 1000,
                'reward_points' => 150
            ],
            7 => [
                'name' => 'ستاره‌ی درخشان',
                'description' => 'به رتبه ستاره‌ی درخشان برسی',
                'icon' => '⭐',
                'type' => 'rank_reached',
                'requirement' => 7,
                'reward_points' => 500
            ],
            8 => [
                'name' => 'جمع‌کننده‌ی سکه',
                'description' => '5000 سکه جمع کن',
                'icon' => '🪙',
                'type' => 'total_coins',
                'requirement' => 5000,
                'reward_points' => 200
            ],
            9 => [
                'name' => 'شانسی‌پرور',
                'description' => 'نسبت برد 50% یا بیشتر داشته باش',
                'icon' => '🎲',
                'type' => 'win_rate',
                'requirement' => 50,
                'reward_points' => 250
            ],
            10 => [
                'name' => 'لیاقت‌مند بعد از 24 ساعت',
                'description' => '7 روز متوالی بازی کن',
                'icon' => '📅',
                'type' => 'consecutive_days',
                'requirement' => 7,
                'reward_points' => 150
            ]
        ];
    }
    
    /**
     * دریافت دستیابی‌های کاربر
     */
    public function getUserAchievements($userId) {
        $achievements = $this->db->select(
            "SELECT ua.*, a.name, a.icon, a.description, a.reward_points
             FROM user_achievements ua
             JOIN achievements a ON ua.achievement_id = a.id
             WHERE ua.user_id = ?
             ORDER BY ua.unlocked_at DESC",
            "i",
            [$userId]
        );
        
        return $achievements ?: [];
    }
    
    /**
     * بررسی و اختطال دستیابی‌ها
     */
    public function checkAndUnlockAchievements($userId) {
        $user = $this->userManager->getUser($userId);
        if (!$user) {
            return [];
        }
        
        $unlockedAchievements = [];
        $allAchievements = $this->getAllAchievements();
        
        $userAchievements = $this->db->select(
            "SELECT achievement_id FROM user_achievements WHERE user_id = ?",
            "i",
            [$userId]
        );
        $unlockedIds = array_map(fn($a) => $a['achievement_id'], $userAchievements);
        
        foreach ($allAchievements as $id => $achievement) {
            // اگر قبلاً بدست آمده بود نادیده گیری کن
            if (in_array($id, $unlockedIds)) {
                continue;
            }
            
            $isUnlocked = false;
            
            switch ($achievement['type']) {
                case 'games_played':
                    $isUnlocked = $user['games_played'] >= $achievement['requirement'];
                    break;
                    
                case 'games_won':
                    $isUnlocked = $user['games_won'] >= $achievement['requirement'];
                    break;
                    
                case 'rank_reached':
                    $isUnlocked = $user['rank_id'] >= $achievement['requirement'];
                    break;
                    
                case 'coins_spent':
                    $spent = $this->getCoinsSpent($userId);
                    $isUnlocked = $spent >= $achievement['requirement'];
                    break;
                    
                case 'total_coins':
                    $total = $user['premium_coins'] + $user['free_coins'];
                    $isUnlocked = $total >= $achievement['requirement'];
                    break;
                    
                case 'win_rate':
                    if ($user['games_played'] >= 10) {
                        $winRate = ($user['games_won'] / $user['games_played']) * 100;
                        $isUnlocked = $winRate >= $achievement['requirement'];
                    }
                    break;
                    
                case 'consecutive_days':
                    $days = $this->getConsecutiveDays($userId);
                    $isUnlocked = $days >= $achievement['requirement'];
                    break;
            }
            
            if ($isUnlocked) {
                $this->unlockAchievement($userId, $id);
                $unlockedAchievements[] = [
                    'id' => $id,
                    'achievement' => $achievement
                ];
                
                // اضافه کردن XP پاداش
                $this->userManager->addXP($userId, $achievement['reward_points']);
            }
        }
        
        return $unlockedAchievements;
    }
    
    /**
     * اختطال کردن دستیابی
     */
    private function unlockAchievement($userId, $achievementId) {
        return $this->db->insert('user_achievements', [
            'user_id' => $userId,
            'achievement_id' => $achievementId,
            'unlocked_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * محاسبه سکه‌های خرج‌شده
     */
    private function getCoinsSpent($userId) {
        $result = $this->db->selectOne(
            "SELECT SUM(total_cost) as spent FROM purchases WHERE user_id = ?",
            "i",
            [$userId]
        );
        
        return $result['spent'] ?? 0;
    }
    
    /**
     * محاسبه روز‌های متوالی بازی
     */
    private function getConsecutiveDays($userId) {
        $dates = $this->db->select(
            "SELECT DISTINCT DATE(joined_at) as game_date 
             FROM game_players 
             WHERE user_id = ? 
             ORDER BY game_date DESC 
             LIMIT 30",
            "i",
            [$userId]
        );
        
        if (empty($dates)) {
            return 0;
        }
        
        $consecutive = 1;
        
        for ($i = 0; $i < count($dates) - 1; $i++) {
            $current = new DateTime($dates[$i]['game_date']);
            $next = new DateTime($dates[$i + 1]['game_date']);
            
            $diff = $current->diff($next)->days;
            
            if ($diff === 1) {
                $consecutive++;
            } else {
                break;
            }
        }
        
        return $consecutive;
    }
    
    /**
     * درصد تکمیل دستیابی‌ها
     */
    public function getAchievementProgress($userId) {
        $unlockedCount = $this->db->selectOne(
            "SELECT COUNT(*) as count FROM user_achievements WHERE user_id = ?",
            "i",
            [$userId]
        )['count'];
        
        $totalAchievements = count($this->getAllAchievements());
        $percentage = ($unlockedCount / $totalAchievements) * 100;
        
        return [
            'unlocked' => $unlockedCount,
            'total' => $totalAchievements,
            'percentage' => round($percentage, 1)
        ];
    }
    
    /**
     * دریافت دستیابی‌های تکمیل‌نشده
     */
    public function getUnlockedAchievements($userId) {
        $unlocked = $this->db->select(
            "SELECT achievement_id FROM user_achievements WHERE user_id = ?",
            "i",
            [$userId]
        );
        
        return array_column($unlocked, 'achievement_id');
    }
    
    /**
     * دریافت دستیابی‌های نزدیک کاربر
     */
    public function getNearAchievements($userId) {
        $user = $this->userManager->getUser($userId);
        $unlockedIds = $this->getUnlockedAchievements($userId);
        $allAchievements = $this->getAllAchievements();
        
        $near = [];
        
        foreach ($allAchievements as $id => $achievement) {
            if (in_array($id, $unlockedIds)) {
                continue;
            }
            
            $progress = 0;
            
            switch ($achievement['type']) {
                case 'games_played':
                    $progress = min(($user['games_played'] / $achievement['requirement']) * 100, 100);
                    break;
                case 'games_won':
                    $progress = min(($user['games_won'] / $achievement['requirement']) * 100, 100);
                    break;
            }
            
            if ($progress >= 50) {
                $near[] = [
                    'achievement' => $achievement,
                    'progress' => $progress,
                    'id' => $id
                ];
            }
        }
        
        usort($near, fn($a, $b) => $b['progress'] <=> $a['progress']);
        
        return array_slice($near, 0, 5);
    }
    
    /**
     * کل XP از دستیابی‌ها
     */
    public function getTotalAchievementXP($userId) {
        $achievements = $this->getUserAchievements($userId);
        return array_sum(array_column($achievements, 'reward_points'));
    }
}

?>