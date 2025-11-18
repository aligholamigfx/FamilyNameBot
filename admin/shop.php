<?php
// ============================================
// مدیریت فروشگاه
// ============================================

require_once '../init.php';
require_admin_login();

$shopManager = new ShopManager($db, new UserManager($db));
$message = '';
$error = '';

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $icon = sanitize_input($_POST['icon'] ?? '');
    $price = (int)($_POST['price'] ?? 0);
    $category = sanitize_input($_POST['category'] ?? 'general');

    if ($_POST['action'] === 'add_item') {
        if ($shopManager->addItem($name, $description, $icon, $price, $category)) {
            $message = 'آیتم با موفقیت اضافه شد.';
        } else {
            $error = 'خطا در افزودن آیتم.';
        }
    }
}

// دریافت آیتم‌ها
$items = $shopManager->getAllItems();

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت فروشگاه - پنل ادمین</title>
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
            <li class="nav-item"><a href="shop.php" class="nav-link active">🛍️ فروشگاه</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link">💳 پرداخت‌ها</a></li>
            <li class="nav-item"><a href="achievements.php" class="nav-link">🎁 دستیابی‌ها</a></li>
        </ul>
        <div class="logout-btn"><a href="logout.php" class="logout-link">🚪 خروج</a></div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header"><h1>🛍️ مدیریت فروشگاه</h1></div>

        <?php if($message): ?><div class="message success-message"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="message error-message"><?php echo $error; ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header">افزودن آیتم جدید</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_item">
                    <div class="form-group"><label>نام آیتم</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>توضیحات</label><input type="text" name="description"></div>
                    <div class="form-group"><label>آیکون</label><input type="text" name="icon"></div>
                    <div class="form-group"><label>قیمت</label><input type="number" name="price" required></div>
                    <div class="form-group"><label>دسته</label><input type="text" name="category" value="general"></div>
                    <button type="submit" class="btn btn-primary">افزودن</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">لیست آیتم‌ها</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr><th>آیکون</th><th>نام</th><th>قیمت</th><th>دسته</th><th>وضعیت</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['icon']); ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo $item['price']; ?> 💎</td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td><?php echo $item['is_active'] ? 'فعال' : 'غیرفعال'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>