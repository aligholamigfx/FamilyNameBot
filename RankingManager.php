<?php
// ============================================
// کلاس سیستم رتبه‌بندی
// ============================================

class RankingManager {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * جدول رتبه‌بندی جهانی
     */
    public function getGlobalRanking($limit = 100, $offset = 0) {
        $result = $this->db->select(
            "SELECT 
                @row := @row + 1 as rank_position,
                user_id,
                username,
                first_name,
                rank_id,
                total_xp,
                games_won,
                games_played,
                premium_coins,
                free_coins
             FROM users, (SELECT @row := ?) as init
             ORDER BY total_xp DESC
             LIMIT ? OFFSET ?",
            "iii",
            [$offset, $limit, $offset]
        );
        
        return $result;
    }
    
    /**
     * دریافت رتبه کاربر
     */
    public function getUserRank($userId) {
        $result = $this->db->selectOne(
            "SELECT COUNT(*) as rank FROM users 
             WHERE total_xp > (SELECT total_xp FROM users WHERE user_id = ?)",
            "i",
            [$userId]
        );
        
        return ($result['rank'] ?? 0) + 1;
    }
    
    /**
     * کاربران نزدیک شما
     */
    public function getNearbyUsers($userId, $range = 5) {
        $user = $this->db->selectOne(
            "SELECT total_xp FROM users WHERE user_id = ?",
            "i",
            [$userId]
        );
        
        if (!$user) {
            return [];
        }
        
        return $this->db->select(
            "SELECT user_id, username, first_name, total_xp, rank_id, games_won
             FROM users
             WHERE ABS(total_xp - ?) <= ?
             ORDER BY total_xp DESC
             LIMIT 20",
            "ii",
            [$user['total_xp'], $range * 50]
        );
    }
    
    /**
     * رتبه‌بندی براساس فیلتر
     */
    public function getRankingByFilter($filter = 'xp', $limit = 50) {
        $orderBy = match($filter) {
            'wins' => 'games_won DESC',
            'games' => 'games_played DESC',
            'coins' => '(premium_coins + free_coins) DESC',
            'rank' => 'rank_id DESC, total_xp DESC',
            default => 'total_xp DESC'
        };
        
        return $this->db->select(
            "SELECT user_id, username, first_name, rank_id, total_xp, games_won, 
                    games_played, premium_coins, free_coins
             FROM users
             ORDER BY $orderBy
             LIMIT ?",
            "i",
            [$limit]
        );
    }
    
    /**
     * بهترین بازیکنان هفتگی
     */
    public function getWeeklyTopPlayers($limit = 20) {
        return $this->db->select(
            "SELECT 
                u.user_id,
                u.username,
                u.first_name,
                u.rank_id,
                COUNT(gp.id) as games_this_week,
                SUM(CASE WHEN gp.is_winner = 1 THEN 1 ELSE 0 END) as wins_this_week,
                AVG(gp.score) as avg_score
             FROM users u
             LEFT JOIN game_players gp ON u.user_id = gp.user_id 
                AND gp.joined_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY u.user_id
             HAVING games_this_week > 0
             ORDER BY wins_this_week DESC, avg_score DESC
             LIMIT ?",
            "i",
            [$limit]
        );
    }
    
    /**
     * بهترین بازیکنان ماهانه
     */
    public function getMonthlyTopPlayers($limit = 20) {
        return $this->db->select(
            "SELECT 
                u.user_id,
                u.username,
                u.first_name,
                u.rank_id,
                COUNT(gp.id) as games_this_month,
                SUM(CASE WHEN gp.is_winner = 1 THEN 1 ELSE 0 END) as wins_this_month,
                MAX(gp.score) as best_score
             FROM users u
             LEFT JOIN game_players gp ON u.user_id = gp.user_id 
                AND gp.joined_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY u.user_id
             HAVING games_this_month > 0
             ORDER BY wins_this_month DESC, best_score DESC
             LIMIT ?",
            "i",
            [$limit]
        );
    }
    
    /**
     * بهترین بازیکنان امسال
     */
    public function getYearlyTopPlayers($limit = 20) {
        return $this->db->select(
            "SELECT 
                u.user_id,
                u.username,
                u.first_name,
                u.rank_id,
                COUNT(gp.id) as games_this_year,
                SUM(CASE WHEN gp.is_winner = 1 THEN 1 ELSE 0 END) as wins_this_year,
                MAX(gp.score) as best_score
             FROM users u
             LEFT JOIN game_players gp ON u.user_id = gp.user_id 
                AND YEAR(gp.joined_at) = YEAR(NOW())
             GROUP BY u.user_id
             HAVING games_this_year > 0
             ORDER BY wins_this_year DESC, best_score DESC
             LIMIT ?",
            "i",
            [$limit]
        );
    }
    
    /**
     * آمار کلی
     */
    public function getCompletionStats() {
        return $this->db->selectOne(
            "SELECT 
                COUNT(*) as total_players,
                AVG(games_played) as avg_games,
                MAX(total_xp) as highest_xp,
                AVG(total_xp) as avg_xp
             FROM users"
        );
    }
    
    /**
     * بازیکنان براساس رتبه
     */
    public function getPlayersByRank($rankId, $limit = 50) {
        return $this->db->select(
            "SELECT user_id, username, first_name, total_xp, games_won, games_played
             FROM users
             WHERE rank_id = ?
             ORDER BY total_xp DESC
             LIMIT ?",
            "ii",
            [$rankId, $limit]
        );
    }
    
    /**
     * تعداد بازیکنان هر رتبه
     */
    public function getPlayersCountByRank() {
        return $this->db->select(
            "SELECT rank_id, COUNT(*) as count
             FROM users
             GROUP BY rank_id
             ORDER BY rank_id ASC"
        );
    }
    
    /**
     * میانگین XP برای هر رتبه
     */
    public function getAverageXPByRank() {
        return $this->db->select(
            "SELECT rank_id, AVG(total_xp) as avg_xp, MIN(total_xp) as min_xp, MAX(total_xp) as max_xp
             FROM users
             GROUP BY rank_id
             ORDER BY rank_id ASC"
        );
    }
    
    /**
     * بازیکنان جدید
     */
    public function getNewPlayers($days = 7, $limit = 20) {
        return $this->db->select(
            "SELECT user_id, username, first_name, rank_id, total_xp, created_at
             FROM users
             WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY created_at DESC
             LIMIT ?",
            "ii",
            [$days, $limit]
        );
    }
    
    /**
     * بازیکنان غیرفعال
     */
    public function getInactivePlayers($days = 30, $limit = 20) {
        return $this->db->select(
            "SELECT u.user_id, u.username, u.first_name, u.rank_id, u.total_xp, 
                    MAX(g.finished_at) as last_game_date
             FROM users u
             LEFT JOIN game_players gp ON u.user_id = gp.user_id
             LEFT JOIN games g ON gp.game_id = g.game_id
             WHERE MAX(g.finished_at) < DATE_SUB(NOW(), INTERVAL ? DAY) OR g.finished_at IS NULL
             GROUP BY u.user_id
             ORDER BY last_game_date DESC
             LIMIT ?",
            "ii",
            [$days, $limit]
        );
    }
}

?>