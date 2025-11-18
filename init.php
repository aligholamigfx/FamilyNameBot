<?php
// ============================================
// فایل راه‌انداز اولیه
// ============================================

// شروع نشست
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیمات Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/logs/php_errors.log');

// تنظیمات امنیتی (فقط برای صفحات HTML)
if (php_sapi_name() !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

// لود کردن فایل‌های اصلی
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TelegramAPI.php';
require_once __DIR__ . '/UserManager.php';
require_once __DIR__ . '/GameManager.php';
require_once __DIR__ . '/ShopManager.php';
require_once __DIR__ . '/PaymentHandler.php';
require_once __DIR__ . '/KeyboardBuilder.php';
require_once __DIR__ . '/RankingManager.php';
require_once __DIR__ . '/AchievementManager.php';

// ایجاد یک نمونه از کلاس دیتابیس
$db = new Database();

// تابع برای پاکسازی ورودی کاربر
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// تابع برای بررسی لاگین ادمین
function require_admin_login() {
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
            header('Location: login.php');
            exit;
        }
    }
}
