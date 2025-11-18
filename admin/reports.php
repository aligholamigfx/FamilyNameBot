<?php
// ============================================
// صفحه گزارشات
// ============================================

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';
require_once '../Database.php';

$db = new Database();

// دریافت پارامترها
$report_type = $_GET['type'] ?? 'revenue';
$days = intval($_GET['days'] ?? 30);
$export = $_GET['export'] ?? '';

// صادرات به CSV
if (!empty($export)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
    
    if ($export === 'users') {
        $data = $db->select("SELECT * FROM users ORDER BY created_at DESC");
        echo "نام,نام‌کاربری,XP,رتبه,بازی‌ها,برد‌ها,سکه‌ها,تاریخ\n";
        foreach ($data as $row) {
            echo $row['first_name'] . "," . $row['username'] . "," . $row['total_xp'] . "," . $row['rank_id'] . "," . $row['games_played'] . "," . $row['games_won'] . "," . ($row['premium_coins'] + $row['free_coins']) . "," . $row['created_at'] . "\n";
        }
    }
    
    elseif ($export === 'payments') {
        $data = $db->select("SELECT p.*, u.first_name FROM payments p LEFT JOIN users u ON p.user_id = u.user_id ORDER BY p.created_at DESC");
        echo "کاربر,مبلغ,ارز,وضعیت,تاریخ\n";
        foreach ($data as $row) {
            echo $row['first_name'] . "," . $row['amount'] . "," . $row['currency'] . "," . $row['status'] . "," . $row['created_at'] . "\n";
        }
    }
    
    exit;
}

// دریافت داده‌های گزارش
$report_data = [];

if ($report_type === 'revenue') {
    $report_data = $db->select("
        SELECT DATE(completed_at) as date, COUNT(*) as count, SUM(amount) as total 
        FROM payments 
        WHERE status = 'completed' AND completed_at > DATE_SUB(NOW(), INTERVAL $days DAY)
        GROUP BY DATE(completed_at) 
        ORDER BY date DESC
    ");
}

elseif ($report_type === 'users') {
    $report_data = $db->select("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL $days DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date DESC
    ");
}

elseif ($report_type === 'games') {
    $report_data = $db->select("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM games 
        WHERE status = 'finished' AND created_at > DATE_SUB(NOW(), INTERVAL $days DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date DESC
    ");
}

// آمار کلی
$summary = [
    'total_users' => $db->count('users'),
    'total_games' => $db->count('games', "status = 'finished'"),
    'total_revenue' => $db->selectOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'] ?? 0,
    'avg_user_xp' => $db->selectOne("SELECT AVG(total_xp) as avg FROM users")['avg'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات - پنل ادمین</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        .report-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            position: relative;
            height: 400px;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-section select,
        .filter-section button {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            background: white;
        }

        .filter-section button {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .filter-section button:hover {
            background: var(--primary-dark);
        }

        .summary-table {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .summary-item {
            padding: 15px;
            background: var(--gray);
            border-radius: 8px;
            text-align: center;
        }

        .summary-item .label {
            color: #999;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .summary-item .value {
            color: var(--dark);
            font-size: 24px;
            font-weight: 700;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .export-btn {
            padding: 10px 15px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            background: #45a049;
            transform: translateY(-2px);
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
            <li class="nav-item"><a href="users.php" class="nav-link">👥 مدیریت کاربران</a></li>
            <li class="nav-item"><a href="games.php" class="nav-link">🎮 مدیریت بازی‌ها</a></li>
            <li class="nav-item"><a href="shop.php" class="nav-link">🛍️ فروشگاه</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link">💳 پرداخت‌ها</a></li>
            <li class="nav-item"><a href="achievements.php" class="nav-link">🎁 دستیابی‌ها</a></li>
            <li class="nav-item"><a href="reports.php" class="nav-link active">📈 گزارش‌ها</a></li>
            <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ تنظیمات</a></li>
        </ul>

        <div class="logout-btn">
            <a href="logout.php" class="logout-link">🚪 خروج</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>📈 گزارشات و آمار</h1>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap;">
                <select name="type" onchange="this.form.submit()">
                    <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>💳 درآمد</option>
                    <option value="users" <?php echo $report_type === 'users' ? 'selected' : ''; ?>>👥 کاربران</option>
                    <option value="games" <?php echo $report_type === 'games' ? 'selected' : ''; ?>>🎮 بازی‌ها</option>
                </select>

                <select name="days" onchange="this.form.submit()">
                    <option value="7" <?php echo $days === 7 ? 'selected' : ''; ?>>7 روز</option>
                    <option value="30" <?php echo $days === 30 ? 'selected' : ''; ?>>30 روز</option>
                    <option value="90" <?php echo $days === 90 ? 'selected' : ''; ?>>90 روز</option>
                    <option value="365" <?php echo $days === 365 ? 'selected' : ''; ?>>سال</option>
                </select>

                <button type="submit">🔄 بروزرسانی</button>
            </form>

            <div class="export-buttons">
                <a href="?type=<?php echo $report_type; ?>&days=<?php echo $days; ?>&export=users" class="export-btn">📥 صادرات کاربران</a>
                <a href="?type=<?php echo $report_type; ?>&days=<?php echo $days; ?>&export=payments" class="export-btn">📥 صادرات پرداخت‌ها</a>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary-table">
            <div class="summary-row">
                <div class="summary-item">
                    <div class="label">کل کاربران</div>
                    <div class="value"><?php echo $summary['total_users']; ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">کل بازی‌ها</div>
                    <div class="value"><?php echo $summary['total_games']; ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">کل درآمد</div>
                    <div class="value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">میانگین XP</div>
                    <div class="value"><?php echo round($summary['avg_user_xp']); ?></div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="report-container">
            <div class="chart-container">
                <canvas id="reportChart"></canvas>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card">
            <div class="card-header">📋 داده‌های تفصیلی</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>تعداد</th>
                            <?php if ($report_type === 'revenue'): ?>
                            <th>مبلغ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo $row['count']; ?></td>
                            <?php if ($report_type === 'revenue'): ?>
                            <td>$<?php echo number_format($row['total'], 2); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // رسم نمودار
        const ctx = document.getElementById('reportChart').getContext('2d');
        const labels = <?php echo json_encode(array_column($report_data, 'date')); ?>;
        const data = <?php echo json_encode(array_column($report_data, 'count')); ?>;
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '<?php echo $report_type === 'revenue' ? 'درآمد' : ($report_type === 'users' ? 'کاربران' : 'بازی‌ها'); ?>',
                    data: data,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>