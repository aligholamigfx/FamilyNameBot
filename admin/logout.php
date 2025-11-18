<?php
// ============================================
// خروج از پنل ادمین
// ============================================

session_start();

// ثبت خروج
if (isset($_SESSION['admin'])) {
    require_once '../config.php';
    require_once '../Database.php';
    
    $db = new Database();
    
    // ثبت در لاگ
    $db->insert('system_logs', [
        'action' => 'admin_logout',
        'user_id' => null,
        'details' => json_encode(['timestamp' => date('Y-m-d H:i:s')]),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

// پاک کردن سشن
session_unset();
session_destroy();

// حذف کوکی‌های سشن
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// تغییر مسیر
header('Location: login.php?logout=true');
exit;
?>