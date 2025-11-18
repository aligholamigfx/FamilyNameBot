<?php
// ============================================
// داشبورد پنل ادمین
// ============================================

require_once '../init.php';
require_admin_login();

// دریافت آمارهای سیستم
$totalUsers = $db->count('users');
$totalGames = $db->count('games', "status = 'finished'");
$activeGames = $db->count('games', "status = 'active' OR status = 'waiting'");
$totalRevenue = $db->selectOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'] ?? 0;
$totalCoinsSpent = $db->selectOne("SELECT SUM(total_cost) as total FROM purchases")['total'] ?? 0;

// کاربران جدید (7 روز اخیر)
$newUsers = $db->selectOne("SELECT COUNT(*) as count FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0;

// پرداخت‌های امروز
$todayPayments = $db->selectOne("SELECT COUNT(*) as count, SUM(amount) as total FROM payments WHERE DATE(completed_at) = CURDATE() AND status = 'completed'");

// آخرین کاربران
$recentUsers = $db->select("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");

// آخرین بازی‌ها
$recentGames = $db->select("SELECT * FROM games ORDER BY created_at DESC LIMIT 5");

// بهترین بازیکنان
$topPlayers = $db->select("SELECT * FROM users ORDER BY total_xp DESC LIMIT 5");

// آمار پرداخت‌ها
$paymentStats = $db->select("SELECT DATE(completed_at) as date, COUNT(*) as count, SUM(amount) as total FROM payments WHERE status = 'completed' AND completed_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(completed_at) ORDER BY date DESC");
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - پنل ادمین</title>
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
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    📊 داشبورد
                </a>
            </li>
            <li class="nav-item">
                <a href="words.php" class="nav-link">
                    📝 مدیریت کلمات
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link">
                    👥 مدیریت کاربران
                </a>
            </li>
            <li class="nav-item">
                <a href="games.php" class="nav-link">
                    🎮 مدیریت بازی‌ها
                </a>
            </li>
            <li class="nav-item">
                <a href="shop.php" class="nav-link">
                    🛍️ فروشگاه و اقلام
                </a>
            </li>
            <li class="nav-item">
                <a href="payments.php" class="nav-link">
                    💳 مدیریت پرداخت‌ها
                </a>
            </li>
            <li class="nav-item">
                <a href="achievements.php" class="nav-link">
                    🎁 دستیابی‌ها
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    📈 گزارش‌ها
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link">
                    ⚙️ تنظیمات
                </a>
            </li>
        </ul>

        <div class="logout-btn">
            <a href="logout.php" class="logout-link">
                🚪 خروج
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>📊 داشبورد</h1>
            <div class="header-info">
                <div class="user-info">
                    <strong>مدیر سیستم</strong>
                    <p><?php echo date('Y-m-d H:i'); ?></p>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">کل کاربران</div>
                <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-change">+<?php echo $newUsers; ?> کاربر جدید این هفته</div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">🎮</div>
                <div class="stat-label">کل بازی‌های انجام‌شده</div>
                <div class="stat-value"><?php echo number_format($totalGames); ?></div>
                <div class="stat-change">🔴 <?php echo $activeGames; ?> بازی فعال</div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">💳</div>
                <div class="stat-label">کل درآمد</div>
                <div class="stat-value">$<?php echo number_format($totalRevenue, 2); ?></div>
                <div class="stat-change">📅 امروز: $<?php echo number_format($todayPayments['total'] ?? 0, 2); ?></div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">💎</div>
                <div class="stat-label">کل سکه‌های خرج‌شده</div>
                <div class="stat-value"><?php echo number_format($totalCoinsSpent); ?></div>
                <div class="stat-change">🎁 پاداش ها شامل است</div>
            </div>
        </div>

        <!-- Tables Section -->
        <div class="table-section">
            <!-- Recent Users -->
            <div class="card">
                <div class="card-header">👥 آخرین کاربران</div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>نام‌کاربری</th>
                                <th>تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                                <td>@<?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Players -->
            <div class="card">
                <div class="card-header">🏆 بهترین بازیکنان</div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>XP</th>
                                <th>برد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topPlayers as $player): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($player['first_name']); ?></td>
                                <td><strong><?php echo $player['total_xp']; ?></strong></td>
                                <td><?php echo $player['games_won']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Games -->
        <div class="card">
            <div class="card-header">🎮 آخرین بازی‌ها</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>ID بازی</th>
                            <th>نوع</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGames as $game): ?>
                        <tr>
                            <td><code><?php echo substr($game['game_id'], 0, 15); ?>...</code></td>
                            <td>
                                <?php
                                    $typeLabel = [
                                        'single' => 'تک‌نفره',
                                        'multi' => 'چند‌نفره',
                                        'group' => 'گروهی'
                                    ][$game['type']] ?? $game['type'];
                                    echo $typeLabel;
                                ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $game['status'] === 'finished' ? 'success' : 'info'; ?>">
                                    <?php echo $game['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($game['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-secondary" onclick="viewGameDetails('<?php echo $game['game_id']; ?>')">نمایش</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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