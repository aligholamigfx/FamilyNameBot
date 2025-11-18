<?php
// ============================================
// صفحه ورود پنل ادمین
// ============================================

session_start();

// اگر قبلاً وارد شده
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// درخواست ورود
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config.php';
    
    $password = $_POST['password'] ?? '';
    
    if (empty($password)) {
        $error = 'لطفاً رمز عبور را وارد کنید';
    } elseif (password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'رمز عبور اشتباه است';
        // ثبت تلاش ناموفق
        error_log('Failed admin login attempt from ' . $_SERVER['REMOTE_ADDR']);
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود پنل ادمین - ربات تلگرام</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --success: #4CAF50;
            --danger: #ff6b6b;
            --warning: #FFD700;
            --dark: #2c3e50;
            --light: #ecf0f1;
            --white: #ffffff;
        }

        html, body {
            height: 100%;
            font-family: 'Segoe UI', 'Tahoma', sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            padding: 50px 40px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .login-header h1 {
            color: var(--dark);
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .login-header p {
            color: #999;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 14px;
        }

        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input[type="password"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input[type="password"]::placeholder {
            color: #bbb;
        }

        .error-message {
            background: rgba(255, 107, 107, 0.1);
            color: var(--danger);
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-right: 4px solid var(--danger);
            font-size: 14px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .success-message {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-right: 4px solid var(--success);
            font-size: 14px;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }

        .login-footer p {
            color: #999;
            font-size: 13px;
            line-height: 1.8;
        }

        .version {
            color: #ccc;
            font-size: 12px;
            margin-top: 20px;
        }

        /* تایپ شدن متن */
        .typing {
            overflow: hidden;
            border-right: 2px solid var(--primary);
            white-space: nowrap;
            animation: typing 3.5s steps(40, end), blink-caret 0.75s step-end infinite;
        }

        @keyframes typing {
            from { width: 0; }
            to { width: 100%; }
        }

        @keyframes blink-caret {
            from, to { border-color: transparent; }
            50% { border-color: var(--primary); }
        }

        .security-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #666;
            border-left: 4px solid var(--primary);
        }

        .security-info strong {
            color: var(--dark);
            display: block;
            margin-bottom: 5px;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .login-container {
                padding: 40px 25px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .logo {
                width: 70px;
                height: 70px;
                font-size: 35px;
            }
        }

        /* حالت تاریک */
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1e3c72, #2a5298);
            }

            .login-container {
                background: #1f1f1f;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
            }

            .login-header h1,
            label {
                color: #fff;
            }

            .login-header p,
            .login-footer p {
                color: #aaa;
            }

            input[type="password"],
            input[type="text"] {
                background: #2a2a2a;
                color: #fff;
                border-color: #444;
            }

            input[type="password"]::placeholder {
                color: #666;
            }

            input[type="password"]:focus,
            input[type="text"]:focus {
                border-color: var(--primary);
                background: #333;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">🔐</div>
            <h1>پنل ادمین</h1>
            <p>ورود به پنل مدیریت ربات تلگرام</p>
        </div>

        <div class="security-info">
            <strong>⚠️ نکات امنیتی:</strong>
            • رمز عبور خود را محفوظ نگهدارید<br>
            • هرگز رمز را با کسی شریک نکنید<br>
            • از کامپیوتر امن استفاده کنید
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <strong>❌ خطا:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <strong>✅ موفق:</strong> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="password">🔑 رمز عبور</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="رمز عبور خود را وارد کنید" 
                    required 
                    autofocus
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-login">
                🔓 ورود به پنل
            </button>
        </form>

        <div class="login-footer">
            <p>
                📝 این پنل برای مدیران سیستم است<br>
                🛡️ تمام فعالیت‌ها ثبت می‌شوند<br>
                <span class="version">v1.0.0 | 2024</span>
            </p>
        </div>
    </div>

    <script>
        // بهبود امنیت
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            
            if (password.length < 3) {
                e.preventDefault();
                alert('رمز عبور باید حداقل 3 کاراکتر باشد');
                return false;
            }

            // پاک کردن حساسیت‌ها
            document.getElementById('password').value = '';
        });

        // جلوگیری از Autofill
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').value = '';
        });

        // لاگ کردن تلاش‌های ناموفق
        document.getElementById('loginForm').addEventListener('invalid', function(e) {
            console.warn('Login attempt validation failed');
        });
    </script>
</body>
</html>