<?php
// ============================================
// مدیریت پرداخت‌ها
// ============================================

require_once '../init.php';
require_admin_login();

// فیلترها
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$whereClause = "1=1";
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $whereClause .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// دریافت پرداخت‌ها
$payments = $db->select(
    "SELECT p.*, u.username
     FROM payments p
     JOIN users u ON p.user_id = u.user_id
     WHERE $whereClause
     ORDER BY p.created_at DESC
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
    <title>مدیریت پرداخت‌ها - پنل ادمین</title>
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
            <li class="nav-item"><a href="games.php" class="nav-link">🎮 مدیریت بازی‌ها</a></li>
            <li class="nav-item"><a href="shop.php" class="nav-link">🛍️ فروشگاه</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link active">💳 پرداخت‌ها</a></li>
            <li class="nav-item"><a href="achievements.php" class="nav-link">🎁 دستیابی‌ها</a></li>
        </ul>
        <div class="logout-btn"><a href="logout.php" class="logout-link">🚪 خروج</a></div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>💳 مدیریت پرداخت‌ها</h1>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="GET" class="search-bar">
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php if($status_filter === 'all') echo 'selected'; ?>>همه وضعیت‌ها</option>
                        <option value="pending" <?php if($status_filter === 'pending') echo 'selected'; ?>>در انتظار</option>
                        <option value="completed" <?php if($status_filter === 'completed') echo 'selected'; ?>>تکمیل شده</option>
                        <option value="failed" <?php if($status_filter === 'failed') echo 'selected'; ?>>ناموفق</option>
                    </select>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>سفارش</th>
                            <th>کاربر</th>
                            <th>مبلغ</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="5" style="text-align: center;">هیچ پرداختی یافت نشد</td></tr>
                        <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['order_id']); ?></td>
                            <td>@<?php echo htmlspecialchars($payment['username']); ?></td>
                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php
                                    echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger');
                                ?>">
                                    <?php echo htmlspecialchars($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($payment['created_at'])); ?></td>
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