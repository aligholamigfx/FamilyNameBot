<?php
require_once '../init.php';
require_admin_login();

// منطق دریافت پرداخت‌ها از دیتابیس
$payments = $db->select(
    "SELECT p.*, u.username
     FROM payments p
     JOIN users u ON p.user_id = u.user_id
     ORDER BY p.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت پرداخت‌ها</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-header"><h2>🤖 پنل مدیریت</h2></div>
        <ul class="nav-menu">
            <li><a href="dashboard.php">📊 داشبورد</a></li>
            <li><a href="users.php">👥 کاربران</a></li>
            <li><a href="games.php">🎮 بازی‌ها</a></li>
            <li><a href="words.php">📝 کلمات</a></li>
            <li><a href="shop.php">🛍️ فروشگاه</a></li>
            <li><a href="payments.php" class="active">💳 پرداخت‌ها</a></li>
            <li><a href="achievements.php">🎁 دستاوردها</a></li>
        </ul>
    </nav>
    <div class="main-content">
        <div class="content-header"><h1>💳 مدیریت پرداخت‌ها</h1></div>

        <div class="card">
            <div class="card-header">تاریخچه پرداخت‌ها</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr><th>شناسه سفارش</th><th>کاربر</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['order_id']); ?></td>
                            <td>@<?php echo htmlspecialchars($payment['username']); ?></td>
                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $payment['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo htmlspecialchars($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($payment['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>