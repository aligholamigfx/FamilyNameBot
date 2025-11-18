<?php
// ============================================
// مدیریت کاربران
// ============================================

require_once '../init.php';
require_admin_login();

$userManager = new UserManager($db);
$message = '';
$error = '';

// ستون‌های مجاز برای مرتب‌سازی
$allowed_sort_columns = ['created_at', 'total_xp', 'games_won', 'first_name', 'username'];

// جستجو و فیلتر
$search = sanitize_input($_GET['search'] ?? '');
$sort = in_array($_GET['sort'] ?? 'created_at', $allowed_sort_columns) ? $_GET['sort'] : 'created_at';
$order = ($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// ساخت query
$params = [];
$types = '';
$whereClause = "1=1";
if (!empty($search)) {
    $whereClause .= " AND (first_name LIKE ? OR username LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params = [$searchTerm, $searchTerm];
    $types = "ss";
}

// دریافت کاربران
$users = $db->select("SELECT * FROM users WHERE $whereClause ORDER BY $sort $order LIMIT 100", $types, $params);

// آمار کاربران
$totalUsers = $db->count('users');
$activeUsers = $db->count('users', "games_played > 0");
$newUsers7Days = $db->count('users', "created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$topRankUsers = $db->count('users', "rank_id >= 5");

// به‌روزرسانی کاربر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_user') {
        $userId = (int)$_POST['user_id'];

        $result = $db->update('users',
            ['rank_id' => 1, 'total_xp' => 0, 'games_played' => 0, 'games_won' => 0],
            "id = ?", "i", [$userId]
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

        // Get user_id from id
        $user = $db->selectOne("SELECT user_id FROM users WHERE id = ?", "i", [$userId]);

        if ($user && $coins != 0) {
            if ($userManager->addCoins($user['user_id'], $coins, 'free')) {
                 $message = '✅ سکه با موفقیت اضافه شد';
            } else {
                 $error = 'خطا در اضافه کردن سکه';
            }
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
    <link rel="stylesheet" href="styles.css">
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
        </div>

        <!-- Users Card -->
        <div class="card">
            <div class="card-header">👥 لیست کاربران</div>
            <div class="card-body">
                <form method="GET" class="search-bar">
                    <input type="text" name="search" class="search-input" placeholder="جستجو..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍</button>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>نام‌کاربری</th>
                            <th>رتبه</th>
                            <th>XP</th>
                            <th>سکه</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="6" style="text-align: center;">کاربری یافت نشد</td></tr>
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                            <td>@<?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo RANKS[$user['rank_id']]['name'] ?? 'N/A'; ?></td>
                            <td><?php echo $user['total_xp']; ?></td>
                            <td><?php echo $user['premium_coins'] + $user['free_coins']; ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="add_coins">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="number" name="coins" value="10" style="width: 70px;">
                                    <button type="submit" class="btn btn-primary">افزودن سکه</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('آیا مطمئن هستید؟');">
                                    <input type="hidden" name="action" value="reset_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger">ریست</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>