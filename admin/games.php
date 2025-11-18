<?php
// ============================================
// مدیریت بازی‌ها
// ============================================

require_once '../init.php';
require_admin_login();

// فیلترها
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$whereClause = "1=1";
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $whereClause .= " AND g.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// دریافت بازی‌ها
$games = $db->select(
    "SELECT g.*, u.username as creator_username
     FROM games g
     JOIN users u ON g.creator_id = u.user_id
     WHERE $whereClause
     ORDER BY g.created_at DESC
     LIMIT 100",
    $types,
    $params
);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت بازی‌ها - پنل ادمین</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header"><h2>🤖 ربات</h2><p>پنل مدیریت</p></div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link">📊 داشبورد</a></li>
            <li class="nav-item"><a href="words.php" class="nav-link">📝 مدیریت کلمات</a></li>
            <li class="nav-item"><a href="users.php" class="nav-link">👥 مدیریت کاربران</a></li>
            <li class="nav-item"><a href="games.php" class="nav-link active">🎮 مدیریت بازی‌ها</a></li>
            <li class="nav-item"><a href="shop.php" class="nav-link">🛍️ فروشگاه</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link">💳 پرداخت‌ها</a></li>
            <li class="nav-item"><a href="achievements.php" class="nav-link">🎁 دستیابی‌ها</a></li>
        </ul>
        <div class="logout-btn"><a href="logout.php" class="logout-link">🚪 خروج</a></div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>🎮 مدیریت بازی‌ها</h1>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="GET" class="search-bar">
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php if($status_filter === 'all') echo 'selected'; ?>>همه وضعیت‌ها</option>
                        <option value="waiting" <?php if($status_filter === 'waiting') echo 'selected'; ?>>در انتظار</option>
                        <option value="active" <?php if($status_filter === 'active') echo 'selected'; ?>>فعال</option>
                        <option value="finished" <?php if($status_filter === 'finished') echo 'selected'; ?>>تمام شده</option>
                    </select>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>شناسه بازی</th>
                            <th>سازنده</th>
                            <th>نوع</th>
                            <th>وضعیت</th>
                            <th>تاریخ ایجاد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($games)): ?>
                        <tr><td colspan="5" style="text-align: center;">هیچ بازی‌ای یافت نشد</td></tr>
                        <?php else: ?>
                        <?php foreach ($games as $game): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($game['game_id']); ?></td>
                            <td>@<?php echo htmlspecialchars($game['creator_username']); ?></td>
                            <td><?php echo htmlspecialchars($game['type']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $game['status'] === 'finished' ? 'success' : 'warning'; ?>">
                                    <?php echo htmlspecialchars($game['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($game['created_at'])); ?></td>
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