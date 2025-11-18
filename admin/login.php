<?php
// ============================================
// صفحه ورود به پنل ادمین
// ============================================

require_once '../init.php';

// اگر کاربر از قبل لاگین کرده، به داشبورد منتقل شود
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// بررسی فرم ورود
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'رمز عبور اشتباه است';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل ادمین</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #f5f7fa;
        }

        .login-card {
            width: 90%;
            max-width: 400px;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .login-header h2 {
            margin-bottom: 10px;
            color: var(--dark);
        }

        .login-header p {
            margin-bottom: 30px;
            color: #999;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
        }

        .error-message {
            color: var(--danger);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h2>ورود به پنل</h2>
            <p>لطفاً رمز عبور خود را وارد کنید</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="message error-message">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="password" name="password" placeholder="رمز عبور" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">ورود</button>
        </form>
    </div>
</body>
</html>