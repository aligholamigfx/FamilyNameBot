<?php
// ============================================
// API برای دریافت آمارهای واقعی‌وقت
// ============================================

session_start();

// بررسی ورود
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config.php';
require_once '../Database.php';

$db = new Database();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'all';

// دریافت تمام آمارها
if ($action === 'all' || $action === 'dashboard') {
    $stats = [
        'total_users' => $db->count('users'),
        'active_users' => $db->count('users', "games_played > 0"),
        'new_users_7days' => $db->count('users', "created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        'top_rank_users' => $db->count('users', "rank_id >= 5"),
        
        'total_games' => $db->count('games', "status = 'finished'"),
        'active_games' => $db->count('games', "status = 'active' OR status = 'waiting'"),
        'today_games' => $db->count('games', "DATE(created_at) = CURDATE()"),
        
        'total_revenue' => $db->selectOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'] ?? 0,
        'today_revenue' => $db->selectOne("SELECT SUM(amount) as total FROM payments WHERE DATE(completed_at) = CURDATE() AND status = 'completed'")['total'] ?? 0,
        'pending_payments' => $db->count('payments', "status = 'pending'"),
        
        'total_coins_distributed' => $db->selectOne("SELECT SUM(total_cost) as total FROM purchases")['total'] ?? 0,
        'total_words' => $db->count('words', "is_active = 1"),
        'total_shop_items' => $db->count('shop_items', "is_active = 1"),
        
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($stats);
}

// آمار کاربران
elseif ($action === 'users') {
    $stats = [
        'total' => $db->count('users'),
        'by_rank' => $db->select("SELECT rank_id, COUNT(*) as count FROM users GROUP BY rank_id ORDER BY rank_id ASC"),
        'top_players' => $db->select("SELECT id, first_name, username, total_xp, games_won FROM users ORDER BY total_xp DESC LIMIT 10")
    ];
    
    echo json_encode($stats);
}

// آمار بازی‌ها
elseif ($action === 'games') {
    $stats = [
        'total_games' => $db->count('games', "status = 'finished'"),
        'active_games' => $db->count('games', "status = 'active' OR status = 'waiting'"),
        'by_type' => $db->select("SELECT type, COUNT(*) as count FROM games WHERE status = 'finished' GROUP BY type"),
        'today' => $db->count('games', "DATE(created_at) = CURDATE()"),
        'this_week' => $db->count('games', "created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'finished'"),
        'this_month' => $db->count('games', "created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) AND status = 'finished'")
    ];
    
    echo json_encode($stats);
}

// آمار پرداخت‌ها
elseif ($action === 'payments') {
    $stats = [
        'total_revenue' => $db->selectOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'] ?? 0,
        'total_transactions' => $db->count('payments', "status = 'completed'"),
        'pending' => $db->count('payments', "status = 'pending'"),
        'failed' => $db->count('payments', "status = 'failed'"),
        'today_revenue' => $db->selectOne("SELECT SUM(amount) as total FROM payments WHERE DATE(completed_at) = CURDATE() AND status = 'completed'")['total'] ?? 0,
        'today_transactions' => $db->count('payments', "DATE(created_at) = CURDATE()"),
        'by_currency' => $db->select("SELECT currency, COUNT(*) as count, SUM(amount) as total FROM payments WHERE status = 'completed' GROUP BY currency"),
        'average_payment' => $db->selectOne("SELECT AVG(amount) as avg FROM payments WHERE status = 'completed'")['avg'] ?? 0
    ];
    
    echo json_encode($stats);
}

// آمار فروشگاه
elseif ($action === 'shop') {
    $stats = [
        'total_purchases' => $db->count('purchases'),
        'total_revenue' => $db->selectOne("SELECT SUM(total_cost) as total FROM purchases")['total'] ?? 0,
        'top_items' => $db->select("SELECT si.name, COUNT(p.id) as purchases, SUM(p.total_cost) as revenue FROM purchases p JOIN shop_items si ON p.item_id = si.id GROUP BY p.item_id ORDER BY purchases DESC LIMIT 10"),
        'by_category' => $db->select("SELECT si.category, COUNT(p.id) as purchases, SUM(p.total_cost) as revenue FROM purchases p JOIN shop_items si ON p.item_id = si.id GROUP BY si.category"),
        'today' => $db->count('purchases', "DATE(purchased_at) = CURDATE()"),
        'today_revenue' => $db->selectOne("SELECT SUM(total_cost) as total FROM purchases WHERE DATE(purchased_at) = CURDATE()")['total'] ?? 0
    ];
    
    echo json_encode($stats);
}

// آمار کلمات
elseif ($action === 'words') {
    $stats = [
        'total' => $db->count('words', "is_active = 1"),
        'deleted' => $db->count('words', "is_active = 0"),
        'by_category' => $db->select("SELECT category, COUNT(*) as count FROM words WHERE is_active = 1 GROUP BY category"),
        'by_difficulty' => $db->select("SELECT difficulty, COUNT(*) as count FROM words WHERE is_active = 1 GROUP BY difficulty")
    ];
    
    echo json_encode($stats);
}

// آمار نمودار درآمد
elseif ($action === 'revenue_chart') {
    $days = intval($_GET['days'] ?? 30);
    
    $stats = $db->select("
        SELECT DATE(completed_at) as date, COUNT(*) as count, SUM(amount) as total 
        FROM payments 
        WHERE status = 'completed' AND completed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(completed_at) 
        ORDER BY date ASC
    ", "i", [$days]);
    
    echo json_encode($stats);
}

// آمار نمودار کاربران
elseif ($action === 'users_chart') {
    $days = intval($_GET['days'] ?? 30);
    
    $stats = $db->select("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ", "i", [$days]);
    
    echo json_encode($stats);
}

// آمار نمودار بازی‌ها
elseif ($action === 'games_chart') {
    $days = intval($_GET['days'] ?? 30);
    
    $stats = $db->select("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM games 
        WHERE status = 'finished' AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ", "i", [$days]);
    
    echo json_encode($stats);
}

// آمار فعالیت
elseif ($action === 'activity') {
    $stats = [
        'online_users' => $db->count('users', "updated_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"),
        'last_hour_games' => $db->count('games', "created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"),
        'last_hour_payments' => $db->count('payments', "created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"),
        'recent_activity' => $db->select("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 20")
    ];
    
    echo json_encode($stats);
}

// خطای کمان ناشناخته
else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}

?>