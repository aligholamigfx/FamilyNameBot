<?php
// ============================================
// مدیریت کلمات
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

// افزودن کلمه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $word = trim($_POST['word'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $difficulty = $_POST['difficulty'] ?? 'متوسط';

        if (empty($word)) {
            $error = 'لطفاً کلمه را وارد کنید';
        } elseif (empty($category)) {
            $error = 'لطفاً دسته را انتخاب کنید';
        } else {
            $result = $db->insert('words', [
                'word' => $word,
                'category' => $category,
                'difficulty' => $difficulty,
                'is_active' => '1',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if ($result) {
                $message = '✅ کلمه با موفقیت اضافه شد';
            } else {
                $error = 'خطا در افزودن کلمه: ' . $db->getError();
            }
        }
    }

    elseif ($_POST['action'] === 'update') {
        $wordId = (int)$_POST['word_id'];
        $word = trim($_POST['word'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $difficulty = $_POST['difficulty'] ?? 'متوسط';

        $result = $db->update('words',
            ['word' => $word, 'category' => $category, 'difficulty' => $difficulty],
            "id = $wordId"
        );

        if ($result) {
            $message = '✅ کلمه با موفقیت به‌روزرسانی شد';
        } else {
            $error = 'خطا در به‌روزرسانی کلمه';
        }
    }

    elseif ($_POST['action'] === 'delete') {
        $wordId = (int)$_POST['word_id'];
        $result = $db->update('words', ['is_active' => '0'], "id = $wordId");

        if ($result) {
            $message = '✅ کلمه با موفقیت حذف شد';
        } else {
            $error = 'خطا در حذف کلمه';
        }
    }

    elseif ($_POST['action'] === 'restore') {
        $wordId = (int)$_POST['word_id'];
        $result = $db->update('words', ['is_active' => '1'], "id = $wordId");

        if ($result) {
            $message = '✅ کلمه با موفقیت بازگردانی شد';
        } else {
            $error = 'خطا در بازگردانی کلمه';
        }
    }
}

// دریافت فیلتر
$filter = $_GET['filter'] ?? 'active';
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';

// ساخت query
$whereClause = $filter === 'active' ? "is_active = 1" : "is_active = 0";

if (!empty($search)) {
    $whereClause .= " AND word LIKE '%" . $db->escape($search) . "%'";
}

if (!empty($category_filter)) {
    $whereClause .= " AND category = '" . $db->escape($category_filter) . "'";
}

// دریافت کلمات
$words = $db->select("SELECT * FROM words WHERE $whereClause ORDER BY created_at DESC");

// دریافت دسته‌ها
$categories = $db->select("SELECT DISTINCT category FROM words WHERE is_active = 1 ORDER BY category");

// آمار کلمات
$totalWords = $db->count('words', "is_active = 1");
$deletedWords = $db->count('words', "is_active = 0");
$wordStats = $db->select("SELECT category, COUNT(*) as count FROM words WHERE is_active = 1 GROUP BY category");
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کلمات - پنل ادمین</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--dark);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            color: var(--dark);
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
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

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
        }

        .words-table {
            width: 100%;
            margin-top: 20px;
        }

        .words-table th,
        .words-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e0e0e0;
        }

        .words-table th {
            background: #f5f7fa;
            font-weight: 600;
            color: var(--dark);
        }

        .words-table tbody tr:hover {
            background: #f9fafc;
        }

        .difficulty-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .difficulty-easy {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .difficulty-medium {
            background: rgba(255, 215, 0, 0.1);
            color: #FF9800;
        }

        .difficulty-hard {
            background: rgba(255, 107, 107, 0.1);
            color: var(--danger);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-mini {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border-right: 3px solid var(--primary);
        }

        .stat-mini .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-mini .label {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Sidebar (same as dashboard.php) -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2>🤖 ربات</h2>
            <p>پنل مدیریت</p>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link">📊 داشبورد</a></li>
            <li class="nav-item"><a href="words.php" class="nav-link active">📝 مدیریت کلمات</a></li>
            <li class="nav-item"><a href="users.php" class="nav-link">👥 مدیریت کاربران</a></li>
            <li class="nav-item"><a href="games.php" class="nav-link">🎮 مدیریت بازی‌ها</a></li>
            <li class="nav-item"><a href="shop.php" class="nav-link">🛍️ فروشگاه</a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link">💳 پرداخت‌ها</a></li>
            <li class="nav-item"><a href="achievements.php" class="nav-link">🎁 دستیابی‌ها</a></li>
            <li class="nav-item"><a href="reports.php" class="nav-link">📈 گزارش‌ها</a></li>
            <li class="nav-item"><a href="settings.php" class="nav-link">⚙️ تنظیمات</a></li>
        </ul>
        <div class="logout-btn">
            <a href="logout.php" class="logout-link">🚪 خروج</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>📝 مدیریت کلمات</h1>
            <button class="btn btn-primary" onclick="openAddWordModal()">➕ افزودن کلمه</button>
        </div>

        <?php if (!empty($message)): ?>
        <div class="message success-message">
            <?php echo $message; ?>
            <button onclick="this.parentElement.style.display='none'" style="float: left; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">×</button>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="message error-message">
            <?php echo $error; ?>
            <button onclick="this.parentElement.style.display='none'" style="float: left; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">×</button>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="value"><?php echo $totalWords; ?></div>
                <div class="label">کل کلمات</div>
            </div>
            <div class="stat-mini" style="border-right-color: var(--danger);">
                <div class="value"><?php echo $deletedWords; ?></div>
                <div class="label">کلمات حذف‌شده</div>
            </div>
            <?php foreach ($wordStats as $stat): ?>
            <div class="stat-mini" style="border-right-color: var(--warning);">
                <div class="value"><?php echo $stat['count']; ?></div>
                <div class="label"><?php echo htmlspecialchars($stat['category']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Search & Filter -->
        <div class="card">
            <div class="card-body">
                <form method="GET" class="search-bar">
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input" 
                        placeholder="جستجو برای کلمه..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                    >

                    <select name="category" class="filter-select">
                        <option value="">تمام دسته‌ها</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                            <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filter" class="filter-select">
                        <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>✅ فعال</option>
                        <option value="deleted" <?php echo $filter === 'deleted' ? 'selected' : ''; ?>>🗑️ حذف‌شده</option>
                    </select>

                    <button type="submit" class="btn btn-primary">🔍 جستجو</button>
                </form>
            </div>
        </div>

        <!-- Words Table -->
        <div class="card">
            <div class="card-body">
                <table class="words-table">
                    <thead>
                        <tr>
                            <th>کلمه</th>
                            <th>دسته</th>
                            <th>سختی</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($words)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">هیچ کلمه‌ای یافت نشد</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($words as $word): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($word['word']); ?></strong></td>
                                <td><?php echo htmlspecialchars($word['category']); ?></td>
                                <td>
                                    <?php
                                    $diffClass = [
                                        'آسان' => 'difficulty-easy',
                                        'متوسط' => 'difficulty-medium',
                                        'سخت' => 'difficulty-hard'
                                    ][$word['difficulty']] ?? 'difficulty-easy';
                                    ?>
                                    <span class="difficulty-badge <?php echo $diffClass; ?>">
                                        <?php echo htmlspecialchars($word['difficulty']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($word['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-secondary" onclick="editWord(<?php echo $word['id']; ?>)">✏️ ویرایش</button>
                                        <?php if ($filter === 'active'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('آیا مطمئن هستید؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="word_id" value="<?php echo $word['id']; ?>">
                                            <button type="submit" class="btn btn-danger">🗑️ حذف</button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="word_id" value="<?php echo $word['id']; ?>">
                                            <button type="submit" class="btn btn-primary">♻️ بازگردانی</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Word Modal -->
    <div id="wordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">➕ افزودن کلمه جدید</h2>
                <button class="close-btn" onclick="closeWordModal()">×</button>
            </div>

            <form method="POST" id="wordForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="word_id" id="wordId" value="">

                <div class="form-group">
                    <label for="word">کلمه *</label>
                    <input type="text" id="word" name="word" required>
                </div>

                <div class="form-group">
                    <label for="category">دسته *</label>
                    <input type="text" id="category" name="category" list="categoryList" required>
                    <datalist id="categoryList">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label for="difficulty">سختی</label>
                    <select id="difficulty" name="difficulty">
                        <option value="آسان">🟢 آسان</option>
                        <option value="متوسط" selected>🟡 متوسط</option>
                        <option value="سخت">🔴 سخت</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeWordModal()">❌ انصراف</button>
                    <button type="submit" class="btn btn-primary">✅ ثبت</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddWordModal() {
            document.getElementById('modalTitle').textContent = '➕ افزودن کلمه جدید';
            document.getElementById('formAction').value = 'add';
            document.getElementById('wordForm').reset();
            document.getElementById('wordId').value = '';
            document.getElementById('wordModal').classList.add('active');
        }

        function closeWordModal() {
            document.getElementById('wordModal').classList.remove('active');
        }

        function editWord(wordId) {
            alert('ویرایش کلمه ' + wordId);
            // در نسخه کامل، باید از AJAX برای دریافت اطلاعات کلمه استفاده شود
        }

        // بستن مودال با کلیک بیرون از آن
        document.getElementById('wordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeWordModal();
            }
        });

        // بستن پیام‌ها بعد از 5 ثانیه
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => msg.style.display = 'none');
        }, 5000);
    </script>
</body>
</html>