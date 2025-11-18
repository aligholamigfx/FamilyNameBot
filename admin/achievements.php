<?php
// ============================================
// مدیریت دستاوردها
// ============================================

require_once '../init.php';
require_admin_login();

$message = '';
$error = '';

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_achievement') {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $icon = sanitize_input($_POST['icon'] ?? '');
        $type = sanitize_input($_POST['type'] ?? 'games_played');
        $requirement = (int)($_POST['requirement'] ?? 0);
        $reward_points = (int)($_POST['reward_points'] ?? 0);

        if ($db->insert('achievements', [
            'name' => $name,
            'description' => $description,
            'icon' => $icon,
            'type' => $type,
            'requirement' => $requirement,
            'reward_points' => $reward_points
        ])) {
            $message = 'دستاورد با موفقیت اضافه شد.';
        } else {
            $error = 'خطا در افزودن دستاورد.';
        }
    }
}

// دریافت دستاوردها
$achievements = $db->select("SELECT * FROM achievements ORDER BY type, requirement ASC");

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دستاوردها - پنل ادمین</title>
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
            <li class="nav-item"><a href="payments.php" class="nav-link">💳 پرداخت‌ها</a></li>
            <li class="nav-item"><a href="achievements.php" class="nav-link active">🎁 دستیابی‌ها</a></li>
        </ul>
        <div class="logout-btn"><a href="logout.php" class="logout-link">🚪 خروج</a></div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header"><h1>🎁 مدیریت دستاوردها</h1></div>

        <?php if($message): ?><div class="message success-message"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="message error-message"><?php echo $error; ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">افزودن دستاورد جدید</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_achievement">
                    <div class="form-group"><label>نام</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>توضیحات</label><input type="text" name="description"></div>
                    <div class="form-group"><label>آیکون</label><input type="text" name="icon"></div>
                    <div class="form-group"><label>نوع</label>
                        <select name="type">
                            <option value="games_played">تعداد بازی</option>
                            <option value="games_won">تعداد برد</option>
                            <option value="total_xp">مجموع امتیاز</option>
                        </select>
                    </div>
                    <div class="form-group"><label>نیازمندی (عدد)</label><input type="number" name="requirement" required></div>
                    <div class="form-group"><label>پاداش (XP)</label><input type="number" name="reward_points" required></div>
                    <button type="submit" class="btn btn-primary">افزودن</button>
                </form>
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
                        <?php foreach ($achievements as $ach): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ach['icon']); ?></td>
                            <td><?php echo htmlspecialchars($ach['name']); ?></td>
                            <td><?php echo htmlspecialchars($ach['type']); ?></td>
                            <td><?php echo $ach['requirement']; ?></td>
                            <td><?php echo $ach['reward_points']; ?> XP</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>