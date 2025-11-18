<?php
// ============================================
// مدیریت کاربران
// ============================================

require_once '../init.php';
require_admin_login();

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
            "id = ?", "i", [$userId]
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

        $result = $db->incrementColumn('users', 'free_coins', $coins, "id = $userId");

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
        // ... (JavaScript code remains the same) ...
    </script>
</body>
</html>