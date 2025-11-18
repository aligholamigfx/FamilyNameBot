<?php
// ============================================
// صفحه تنظیمات سیستم
// ============================================

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';
require_once '../Database.php';

$db = new Database();
$message = '';
$error = '';

// ذخیره تنظیمات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'save_general') {
        $bot_name = trim($_POST['bot_name'] ?? '');
        $bot_description = trim($_POST['bot_description'] ?? '');
        $maintenance_mode = $_POST['maintenance_mode'] ?? '0';
        
        if (empty($bot_name)) {
            $error = 'نام ربات نمی‌تواند خالی باشد';
        } else {
            $db->update('settings', ['value' => $bot_name], "key_name = 'bot_name'");
            $db->update('settings', ['value' => $bot_description], "key_name = 'bot_description'");
            $db->update('settings', ['value' => $maintenance_mode], "key_name = 'maintenance_mode'");
            $message = '✅ تنظیمات عمومی ذخیره شد';
        }
    }
    
    elseif ($_POST['action'] === 'save_game') {
        $game_timeout = intval($_POST['game_timeout'] ?? 300);
        $max_players = intval($_POST['max_players'] ?? 10);
        $win_points = intval($_POST['win_points'] ?? 100);
        $loss_points = intval($_POST['loss_points'] ?? 10);
        
        $db->update('settings', ['value' => $game_timeout], "key_name = 'game_timeout'");
        $db->update('settings', ['value' => $max_players], "key_name = 'max_players_group'");
        $db->update('settings', ['value' => $win_points], "key_name = 'win_points'");
        $db->update('settings', ['value' => $loss_points], "key_name = 'loss_points'");
        
        $message = '✅ تنظیمات بازی ذخیره شد';
    }
    
    elseif ($_POST['action'] === 'save_coins') {
        $coin_multiplier = floatval($_POST['coin_multiplier'] ?? 1.5);
        $free_coins_reward = intval($_POST['free_coins_reward'] ?? 5);
        
        $db->update('settings', ['value' => $coin_multiplier], "key_name = 'coin_multiplier'");
        $db->update('settings', ['value' => $free_coins_reward], "key_name = 'free_coins_reward'");
        
        $message = '✅ تنظیمات سکه ذخیره شد';
    }
    
    elseif ($_POST['action'] === 'save_ranks') {
        $rank_name = trim($_POST['rank_name'] ?? '');
        $rank_xp = intval($_POST['rank_xp'] ?? 0);
        
        if (empty($rank_name)) {
            $error = 'نام رتبه نمی‌تواند خالی باشد';
        } else {
            $db->insert('settings', [
                'key_name' => 'rank_' . uniqid(),
                'value' => json_encode(['name' => $rank_name, 'xp' => $rank_xp])
            ]);
            $message = '✅ رتبه جدید اضافه شد';
        }
    }
    
    elseif ($_POST['action'] === 'save_email') {
        $email_from = trim($_POST['email_from'] ?? '');
        $email_host = trim($_POST['email_host'] ?? '');
        $email_user = trim($_POST['email_user'] ?? '');
        $email_pass = trim($_POST['email_pass'] ?? '');
        
        $db->update('settings', ['value' => $email_from], "key_name = 'email_from'");
        $db->update('settings', ['value' => $email_host], "key_name = 'email_host'");
        $db->update('settings', ['value' => $email_user], "key_name = 'email_user'");
        $db->update('settings', ['value' => $email_pass], "key_name = 'email_pass'");
        
        $message = '✅ تنظیمات ایمیل ذخیره شد';
    }
    
    elseif ($_POST['action'] === 'backup_db') {
        performBackup($db);
        $message = '✅ پایگاه داده با موفقیت backup شد';
    }
    
    elseif ($_POST['action'] === 'clear_logs') {
        $db->delete('system_logs', "1=1");
        $message = '✅ لاگ‌ها پاک شدند';
    }
    
    elseif ($_POST['action'] === 'reset_system') {
        if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'yes') {
            // ریست کامل سیستم
            $db->delete('games', "1=1");
            $db->delete('game_players', "1=1");
            $db->delete('purchases', "1=1");
            
            $message = '✅ سیستم ریست شد (بدون حذف کاربران)';
        } else {
            $error = 'لطفاً تایید ریست را انجام دهید';
        }
    }
}

// دریافت تنظیمات فعلی
$general_settings = [
    'bot_name' => $db->selectOne("SELECT value FROM settings WHERE key_name = 'bot_name'")['value'] ?? 'ربات',
    'bot_description' => $db->selectOne("SELECT value FROM settings WHERE key_name = 'bot_description'")['value'] ?? '',
    'maintenance_mode' => $db->selectOne("SELECT value FROM settings WHERE key_name = 'maintenance_mode'")['value'] ?? '0'
];

$game_settings = [
    'game_timeout' => intval($db->selectOne("SELECT value FROM settings WHERE key_name = 'game_timeout'")['value'] ?? 300),
    'max_players' => intval($db->selectOne("SELECT value FROM settings WHERE key_name = 'max_players_group'")['value'] ?? 10),
    'win_points' => intval($db->selectOne("SELECT value FROM settings WHERE key_name = 'win_points'")['value'] ?? 100),
    'loss_points' => intval($db->selectOne("SELECT value FROM settings WHERE key_name = 'loss_points'")['value'] ?? 10)
];

$coin_settings = [
    'multiplier' => floatval($db->selectOne("SELECT value FROM settings WHERE key_name = 'coin_multiplier'")['value'] ?? 1.5),
    'free_reward' => intval($db->selectOne("SELECT value FROM settings WHERE key_name = 'free_coins_reward'")['value'] ?? 5)
];

// آمار سیستم
$system_stats = [
    'database_size' => getDatabaseSize($db),
    'total_logs' => $db->count('system_logs'),
    'backups_count' => countBackups()
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات - پنل ادمین</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .setting-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .setting-card h3 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--success);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .btn-save {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 15px;
        }

        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .danger-zone {
            background: rgba(255, 107, 107, 0.05);
            border: 2px solid rgba(255, 107, 107, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .danger-zone h4 {
            color: var(--danger);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger-action {
            background: var(--danger);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 10px;
        }

        .btn-danger-action:hover {
            background: #ee5a52;
            transform: translateY(-2px);
        }

        .stats-box {
            background: var(--gray);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .stats-box .label {
            color: #999;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .stats-box .value {
            color: var(--dark);
            font-size: 20px;
            font-weight: 700;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success-message {
            background: rgba(76, 175, 80, 0.1);
            border-right: 4px solid var(--success);
            color: var(--success);
        }

        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border-right: 4px solid var(--danger);
            color: var(--danger);
        }

        .info-box {
            background: rgba(102, 126, 234, 0.1);
            border-right: 4px solid var(--primary);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            color: var(--primary);
            font-size: 13px;
        }

        @media (max-width: 1024px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
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
            <li class="nav-item"><a href="reports.php" class="nav-link">📈 گزارش‌ها</a></li>
            <li class="nav-item"><a href="settings.php" class="nav-link active">⚙️ تنظیمات</a></li>
        </ul>

        <div class="logout-btn">
            <a href="logout.php" class="logout-link">🚪 خروج</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>⚙️ تنظیمات سیستم</h1>
        </div>

        <?php if (!empty($message)): ?>
        <div class="message success-message">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="message error-message">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- تنظیمات عمومی -->
        <div class="settings-grid">
            <div class="setting-card">
                <h3>📱 تنظیمات عمومی</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_general">
                    
                    <div class="form-group">
                        <label>نام ربات</label>
                        <input type="text" name="bot_name" value="<?php echo htmlspecialchars($general_settings['bot_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>توضیحات ربات</label>
                        <textarea name="bot_description"><?php echo htmlspecialchars($general_settings['bot_description']); ?></textarea>
                    </div>

                    <div class="form-group toggle-switch">
                        <label>حالت نگهداری</label>
                        <label class="switch">
                            <input type="checkbox" name="maintenance_mode" value="1" 
                                <?php echo $general_settings['maintenance_mode'] == 1 ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <button type="submit" class="btn-save">💾 ذخیره</button>
                </form>
            </div>

            <!-- تنظیمات بازی -->
            <div class="setting-card">
                <h3>🎮 تنظیمات بازی</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_game">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>مهلت زمانی بازی (ثانیه)</label>
                            <input type="number" name="game_timeout" value="<?php echo $game_settings['game_timeout']; ?>" min="60">
                        </div>
                        <div class="form-group">
                            <label>حداکثر بازیکن</label>
                            <input type="number" name="max_players" value="<?php echo $game_settings['max_players']; ?>" min="2">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>امتیاز برد</label>
                            <input type="number" name="win_points" value="<?php echo $game_settings['win_points']; ?>" min="1">
                        </div>
                        <div class="form-group">
                            <label>امتیاز باخت</label>
                            <input type="number" name="loss_points" value="<?php echo $game_settings['loss_points']; ?>" min="0">
                        </div>
                    </div>

                    <button type="submit" class="btn-save">💾 ذخیره</button>
                </form>
            </div>

            <!-- تنظیمات سکه -->
            <div class="setting-card">
                <h3>💎 تنظیمات سکه</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_coins">
                    
                    <div class="form-group">
                        <label>ضریب سکه (پاداش)</label>
                        <input type="number" name="coin_multiplier" value="<?php echo $coin_settings['multiplier']; ?>" step="0.1" min="0.1">
                    </div>

                    <div class="form-group">
                        <label>سکه رایگان پاداش (در بازی)</label>
                        <input type="number" name="free_coins_reward" value="<?php echo $coin_settings['free_reward']; ?>" min="0">
                    </div>

                    <div class="info-box">
                        💡 ضریب سکه برای محاسبه پاداش استفاده می‌شود
                    </div>

                    <button type="submit" class="btn-save">💾 ذخیره</button>
                </form>
            </div>

            <!-- تنظیمات ایمیل -->
            <div class="setting-card">
                <h3>📧 تنظیمات ایمیل</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_email">
                    
                    <div class="form-group">
                        <label>ایمیل فرستنده</label>
                        <input type="email" name="email_from" placeholder="noreply@example.com">
                    </div>

                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="email_host" placeholder="smtp.gmail.com">
                    </div>

                    <div class="form-group">
                        <label>نام کاربری SMTP</label>
                        <input type="text" name="email_user" placeholder="your@gmail.com">
                    </div>

                    <div class="form-group">
                        <label>رمز SMTP</label>
                        <input type="password" name="email_pass" placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn-save">💾 ذخیره</button>
                </form>
            </div>

            <!-- آمار سیستم -->
            <div class="setting-card">
                <h3>📊 آمار سیستم</h3>
                
                <div class="stats-box">
                    <div class="label">حجم پایگاه داده</div>
                    <div class="value"><?php echo $system_stats['database_size']; ?> MB</div>
                </div>

                <div class="stats-box">
                    <div class="label">تعداد لاگ‌ها</div>
                    <div class="value"><?php echo number_format($system_stats['total_logs']); ?></div>
                </div>

                <div class="stats-box">
                    <div class="label">تعداد بک‌آپ‌ها</div>
                    <div class="value"><?php echo $system_stats['backups_count']; ?></div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="backup_db">
                    <button type="submit" class="btn-save">📥 ایجاد بک‌آپ</button>
                </form>
            </div>

            <!-- ابزارهای خطرناک -->
            <div class="setting-card">
                <h3>⚠️ ابزارهای خطرناک</h3>
                
                <div class="danger-zone">
                    <h4>🗑️ پاک کردن لاگ‌ها</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn-danger-action" onclick="return confirm('آیا مطمئن هستید؟')">پاک کردن</button>
                    </form>
                </div>

                <div class="danger-zone">
                    <h4>🔄 ریست سیستم</h4>
                    <p style="font-size: 12px; color: var(--danger); margin-bottom: 10px;">
                        ⚠️ این کار تمام بازی‌ها و تراکنش‌ها را حذف می‌کند (کاربران محفوظ می‌ماند)
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_system">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                            <input type="checkbox" name="confirm_reset" value="yes" required>
                            <span>من متوجه عواقب هستم</span>
                        </label>
                        <button type="submit" class="btn-danger-action" onclick="return confirm('آیا مطمئن هستید؟')">ریست کامل</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // بستن پیام‌ها بعد از 5 ثانیه
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => msg.style.display = 'none');
        }, 5000);
    </script>
</body>
</html>

<?php
// ============================================
// توابع کمکی
// ============================================

/**
 * دریافت حجم پایگاه داده
 */
function getDatabaseSize($db) {
    $result = $db->selectOne("SELECT 
        ROUND(((data_length + index_length) / 1024 / 1024), 2) as size 
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()");
    
    return $result['size'] ?? 0;
}

/**
 * شمارش بک‌آپ‌ها
 */
function countBackups() {
    $backupDir = LOG_DIR . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        return 0;
    }
    
    $files = scandir($backupDir);
    return count($files) - 2; // کم کردن . و ..
}

/**
 * ایجاد بک‌آپ پایگاه داده
 */
function performBackup($db) {
    $backupDir = LOG_DIR . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $filename = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // دریافت تمام جداول
    $tables = $db->select("SHOW TABLES");
    
    $backup = "-- Backup at " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- Database: " . DB_NAME . "\n\n";
    
    foreach ($tables as $table) {
        $tableName = $table[key($table)];
        
        // GET CREATE TABLE
        $create = $db->selectOne("SHOW CREATE TABLE $tableName");
        $backup .= $create['Create Table'] . ";\n\n";
    }
    
    // نوشتن فایل
    file_put_contents($filename, $backup);
    
    return true;
}

?>