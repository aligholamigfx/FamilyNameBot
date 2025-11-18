<?php
// ============================================
// داشبورد پنل ادمین
// ============================================

session_start();

// بررسی ورود
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';
require_once '../Database.php';

$db = new Database();

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

        html, body {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f7fa;
        }

        body {
            display: flex;
        }

        /* Sidebar */
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
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
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

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
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
            cursor: pointer;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding-right: 20px;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .nav-icon {
            margin-left: 10px;
            font-size: 18px;
        }

        .logout-btn {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: rgba(255, 107, 107, 0.2);
            color: #FFB3B3;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            cursor: pointer;
        }

        .logout-link:hover {
            background: rgba(255, 107, 107, 0.3);
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-right: 250px;
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            height: 100vh;
        }

        /* Header */
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

        .header-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .user-info {
            text-align: right;
            padding-right: 15px;
            border-right: 2px solid var(--border);
        }

        .user-info p {
            color: #666;
            font-size: 13px;
        }

        .user-info strong {
            color: var(--dark);
            display: block;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-right: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.success {
            border-right-color: var(--success);
        }

        .stat-card.warning {
            border-right-color: var(--warning);
        }

        .stat-card.danger {
            border-right-color: var(--danger);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #999;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .stat-value {
            color: var(--dark);
            font-size: 28px;
            font-weight: 700;
        }

        .stat-change {
            font-size: 12px;
            color: var(--success);
            margin-top: 8px;
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* Tables */
        .table-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
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

        tr:last-child td {
            border-bottom: none;
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

        /* Actions */
        .action-buttons {
            display: flex;
            gap: 10px;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-right: 200px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .table-section {
                grid-template-columns: 1fr;
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

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Scroll Bar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
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
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    📊 داشبورد
                    <span class="nav-icon">→</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="words.php" class="nav-link">
                    📝 مدیریت کلمات
                    <span class="nav-icon">→</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link">
                    👥 مدیریت کاربران
                    <span class="nav-icon">→</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="games.php" class="nav-link">
                    🎮 مدیریت بازی‌ها
                    <span class="nav-icon">→</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="shop.php" class="nav-link">
                    🛍️ فروشگاه و اقلام
                    <span class="nav-icon">→</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payments.php" class="nav-link">
                    💳 مدیریت پرداخت‌ها
                    <span class="nav-icon">→</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="achievements.php" class="nav-link">
                    🎁 دستیابی‌ها
                    <span class="nav-icon">→</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    📈 گزارش‌ها
                    <span class="nav-icon">→</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link">
                    ⚙️ تنظیمات
                    <span class="nav-icon">→</span>
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
        // نشانه‌گذاری صفحه فعال
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });

        // مشاهده جزئیات بازی
        function viewGameDetails(gameId) {
            alert('جزئیات بازی: ' + gameId);
            // می‌تواند به صفحه‌ای دیگر منتقل شود
        }

        // بازخورد واقعی‌وقت (اختیاری)
        setInterval(function() {
            fetch('get_stats.php')
                .then(response => response.json())
                .then(data => {
                    // به‌روزرسانی آمارها
                    console.log('Stats updated:', data);
                });
        }, 30000); // هر 30 ثانیه
    </script>
</body>
</html>