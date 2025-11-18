<?php
// ============================================
// تنظیمات کامل ربات
// ============================================

// اطلاعات ربات تلگرام
define('BOT_TOKEN', 'TokenBot');
define('BOT_USERNAME', 'UsernameBot');
define('WEBHOOK_URL', 'https://limostock.ir/Bots/EsmFamil/bot/webhook.php');

// اطلاعات Cryptomus
define('CRYPTOMUS_API_KEY', '-');
define('CRYPTOMUS_MERCHANT_UUID', '-');

// اطلاعات پایگاه داده
define('DB_HOST', 'localhost');
define('DB_USER', 'djlkvdut_haotqq');
define('DB_PASS', 'F]PJ=1W6b9)5');
define('DB_NAME', 'djlkvdut_ajieot');

// تنظیمات ادمین
define('ADMIN_PASSWORD_HASH', password_hash('admin123', PASSWORD_BCRYPT));
define('PANEL_URL', 'https://limostock.ir/Bots/EsmFamil/admin/login.php');

// تنظیمات بازی
define('GAME_TIMEOUT', 300);
define('MAX_PLAYERS_GROUP', 10);
define('WIN_POINTS', 100);
define('LOSS_POINTS', 10);

// تنظیمات سکه
define('COIN_SYMBOL', '💎');
define('COIN_NAME', 'کریستال');
define('BASE_COIN_MULTIPLIER', 1.5);

// تعریف رتبه‌ها
$RANKS = [
    1 => [
        'name' => 'تازه‌کار',
        'min_xp' => 0,
        'icon' => '⚪',
        'color' => '#9E9E9E',
        'description' => 'شروع سفر شما'
    ],
    2 => [
        'name' => 'شروع‌کننده',
        'min_xp' => 100,
        'icon' => '🟢',
        'color' => '#4CAF50',
        'description' => 'اولین قدم‌های موفق'
    ],
    3 => [
        'name' => 'سطح درمیانی',
        'min_xp' => 300,
        'icon' => '🔵',
        'color' => '#2196F3',
        'description' => 'بازیکن باتجربه'
    ],
    4 => [
        'name' => 'حرفه‌ای',
        'min_xp' => 600,
        'icon' => '🟣',
        'color' => '#9C27B0',
        'description' => 'مسلط و با‌تاب'
    ],
    5 => [
        'name' => 'استاد',
        'min_xp' => 1000,
        'icon' => '🟠',
        'color' => '#FF9800',
        'description' => 'دارای مهارت عالی'
    ],
    6 => [
        'name' => 'افسانه‌ای',
        'min_xp' => 1500,
        'icon' => '🔴',
        'color' => '#F44336',
        'description' => 'نام و نشان‌ات شنیده می‌شود'
    ],
    7 => [
        'name' => 'بی‌نظیر',
        'min_xp' => 2500,
        'icon' => '⭐',
        'color' => '#FFD700',
        'description' => 'بالاترین رتبه'
    ],
];

define('RANKS', $RANKS);

// تنظیمات زمان
date_default_timezone_set('Asia/Tehran');

// تنظیمات Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/logs/php_errors.log');

// تنظیمات امنیتی
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// فایل‌های لاگ
define('LOG_DIR', dirname(__FILE__) . '/logs');
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// تعریف상수های درخواست HTTP
define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD'] ?? 'GET');
define('REMOTE_IP', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

?>