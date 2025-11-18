<?php
// ============================================
// مدیریت کاربران
// ============================================

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';
require_once '../Database.php';

$db = new Database();
$message = '';
$error = '';

// جستجو و فیلتر
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// ساخت query
$whereClause = "1=1";
if (!empty($search)) {
    $whereClause .= " AND (first_name LIKE '%" . $db->escape($search) . "%' OR username LIKE '%" . $db->escape($search) . "%')";
}

// دریافت کاربران
$users = $db->select("SELECT * FROM users WHERE $whereClause ORDER BY $sort $order LIMIT 100");

// آمار کاربران
$totalUsers = $db->count('users');
$activeUsers = $db->count('users', "games_played > 0");
$newUsers7Days = $db->count('users', "created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$topRankUsers = $db->count('users', "rank_id >= 5");

// دریافت کاربر برای مشاهده جزئیات
$selectedUser = null;
if (isset($_GET['view_user'])) {
    $userId = (int)$_GET['view_user'];
    $selectedUser = $db->selectOne("SELECT * FROM users WHERE id = ?", "i", [$userId]);
}

// به‌روزرسانی کاربر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_user') {
        $userId = (int)$_POST['user_id'];
        $newRank = (int)($_POST['rank_id'] ?? 1);
        $newXP = (int)($_POST['total_xp'] ?? 0);

        $result = $db->update('users',
            ['rank_id' => $newRank, 'total_xp' => $newXP],
            "id = $userId"
        );

        if ($result) {
            $message = '✅ کاربر با موفقیت به‌روزرسانی شد';
        } else {
            $error = 'خطا در به‌روزرسانی کاربر';
        }
    }

    elseif ($_POST['action'] === 'reset_user') {
        $userId = (int)$_POST['user_id'];
        
        $result = $db->update('users',
            ['rank_id' => '1', 'total_xp' => '0', 'games_played' => '0', 'games_won' => '0'],
            "id = $userId"
        );

        if ($result) {
            $message = '✅ کاربر با موفقیت ریست شد';
        } else {
            $error = 'خطا در ریست کردن کاربر';
        }
    }

    elseif ($_POST['action'] === 'add_coins') {
        $userId = (int)$_POST['user_id'];
        $coins = (int)($_POST['coins'] ?? 0);

        $user = $db->selectOne("SELECT * FROM users WHERE id = ?", "i", [$userId]);
        $newCoins = $user['free_coins'] + $coins;

        $result = $db->update('users',
            ['free_coins' => $newCoins],
            "id = $userId"
        );

        if ($result) {
            $message = '✅ سکه با موفقیت اضافه شد';
        } else {
            $error = 'خطا در اضافه کردن سکه';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - پنل ادمین</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --success: #4CAF50;
            --warning: #FFD700;
            --danger: #ff6b6b;
            --dark: #2c3e50;
            --light: #ecf0f1;
            --white: #ffffff;
            --border: #e0e0e0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f7fa;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            color: white;
            padding: 30px 20px;
            height: 100vh;
            overflow-y: auto;
            position: fixed;
            right: 0;
            z-index: 1000;
        }

        .sidebar-header {
            margin-bottom: 30px;
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .logout-btn {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-link {
            display: block;
            padding: 12px 15px;
            background: rgba(255, 107, 107, 0.2);
            color: #FFB3B3;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .logout-link:hover {
            background: rgba(255, 107, 107, 0.3);
            color: white;
        }

        .main-content {
            margin-right: 250px;
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            height: 100vh;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .content-header h1 {
            color: var(--dark);
            font-size: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-right: 4px solid var(--primary);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-value {
            color: var(--dark);
            font-size: 28px;
            font-weight: 700;
        }

        .stat-label {
            color: #999;
            font-size: 13px;
            margin-top: 8px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-input,
        .sort-select {
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
        }

        .sort-select {
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: #f5f7fa;
            padding: 12px;
            text-align: right;
            color: var(--dark);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        tr:hover {
            background: #f9fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(255, 215, 0, 0.1);
            color: #FF9800;
        }

        .badge-danger {
            background: rgba(255, 107, 107, 0.1);
            color: var(--danger);
        }

        .badge-info {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary);
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #ee5a52;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }

        .success-message {
            background: rgba(76, 175, 80, 0.1);
            border-right: 4px solid var(--success);
            color: var(--success);
        }

        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border-right: 4px solid var(--danger);
            color: var(--danger);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .rank-icon {
            font-size: 18px;
        }

        @media (max-width: 1200px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-right: 200px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                padding: 20px;
            }

            .main-content {
                margin-right: 0;
                height: auto;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-bar {
                flex-direction: column;
            }

            .search-input {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2>🤖 ربات</h2>
            <p>پنل مدیریت</p>
        </div>

        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link">📊 داشبورد</a></li>
            <li class="nav-item"><a href="words.php" class="nav-link">📝 مدیریت کلمات</a></li>
            <li class="nav-item"><a href="users.php" class="nav-link active">👥 مدیریت کاربران</a></li>
            <li class="nav-item"><a href="games.php" class="nav-link">🎮 مدیریت بازی‌ها</a></li>
            <li class="nav-item"><a href="shop.php" class="nav-link">🛍️ فروشگاه</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link">💳 پرداخت‌ها</a></li>
            <li class="nav-item"><a href="achievements.php" class="nav-link">🎁 دستیابی‌ها</a></li>
            <li class="nav-item"><a href="reports.php" class="nav-link">📈 گزارش‌ها</a></li>
            <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ تنظیمات</a></li>
        </ul>

        <div class="logout-btn">
            <a href="logout.php" class="logout-link">🚪 خروج</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>👥 مدیریت کاربران</h1>
        </div>

        <?php if (!empty($message)): ?>
        <div class="message success-message">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="message error-message">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <div class="stat-label">کل کاربران</div>
            </div>

            <div class="stat-card" style="border-right-color: var(--success);">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $activeUsers; ?></div>
                <div class="stat-label">کاربران فعال</div>
            </div>

            <div class="stat-card" style="border-right-color: var(--warning);">
                <div class="stat-icon">🆕</div>
                <div class="stat-value"><?php echo $newUsers7Days; ?></div>
                <div class="stat-label">جدید (7 روز)</div>
            </div>

            <div class="stat-card" style="border-right-color: var(--danger);">
                <div class="stat-icon">🏆</div>
                <div class="stat-value"><?php echo $topRankUsers; ?></div>
                <div class="stat-label">کاربران رتبه‌بالا</div>
            </div>
        </div>

        <!-- Users Card -->
        <div class="card">
            <div class="card-header">👥 لیست کاربران</div>
            <div class="card-body">
                <form method="GET" class="search-bar">
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input" 
                        placeholder="جستجو برای نام یا نام‌کاربری..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                    >

                    <select name="sort" class="sort-select">
                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>📅 آخرین</option>
                        <option value="total_xp" <?php echo $sort === 'total_xp' ? 'selected' : ''; ?>>⭐ بیشترین XP</option>
                        <option value="games_won" <?php echo $sort === 'games_won' ? 'selected' : ''; ?>>🎯 بیشترین برد</option>
                    </select>

                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>نام‌کاربری</th>
                            <th>رتبه</th>
                            <th>XP</th>
                            <th>بازی</th>
                            <th>برد</th>
                            <th>سکه</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999;">هیچ کاربری یافت نشد</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                                <td>@<?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="rank-icon">
                                        <?php echo RANKS[$user['rank_id']]['icon'] ?? '⚪'; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $user['total_xp']; ?></strong></td>
                                <td><?php echo $user['games_played']; ?></td>
                                <td><?php echo $user['games_won']; ?></td>
                                <td><?php echo $user['premium_coins'] + $user['free_coins']; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?view_user=<?php echo $user['id']; ?>" class="btn btn-secondary">👁️ مشاهده</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید؟');">
                                            <input type="hidden" name="action" value="reset_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger">🔄 ریست</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // بستن پیام‌ها
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => msg.style.display = 'none');
        }, 5000);
    </script>
</body>
</html>