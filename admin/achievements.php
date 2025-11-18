<?php
require_once '../init.php';
require_admin_login();

$achievementManager = new AchievementManager($db, new UserManager($db));
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_achievement') {
        // منطق افزودن دستاورد در اینجا پیاده‌سازی می‌شود
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        // ...
        $message = "دستاورد جدید با موفقیت اضافه شد.";
    }
}

$achievements = $achievementManager->getAllAchievements();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت دستاوردها</title>
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
            <li><a href="payments.php">💳 پرداخت‌ها</a></li>
            <li><a href="achievements.php" class="active">🎁 دستاوردها</a></li>
        </ul>
    </nav>
    <div class="main-content">
        <div class="content-header"><h1>🎁 مدیریت دستاوردها</h1></div>

        <div class="card">
            <div class="card-header">افزودن دستاورد</div>
            <div class="card-body">
                <!-- فرم افزودن دستاورد -->
            </div>
        </div>

        <div class="card">
            <div class="card-header">لیست دستاوردها</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr><th>آیکون</th><th>نام</th><th>نوع</th><th>نیازمندی</th><th>پاداش</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($achievements as $ach): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ach['icon']); ?></td>
                            <td><?php echo htmlspecialchars($ach['name']); ?></td>
                            <td><?php echo htmlspecialchars($ach['type']); ?></td>
                            <td><?php echo htmlspecialchars($ach['requirement']); ?></td>
                            <td><?php echo htmlspecialchars($ach['reward_points']); ?> XP</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>