<?php
// ============================================
// مدیریت کلمات
// ============================================

require_once '../init.php';
require_admin_login();

$message = '';
$error = '';

// افزودن کلمه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $word = sanitize_input($_POST['word'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $difficulty = sanitize_input($_POST['difficulty'] ?? 'متوسط');

        if (empty($word)) {
            $error = 'لطفاً کلمه را وارد کنید';
        } elseif (empty($category)) {
            $error = 'لطفاً دسته را انتخاب کنید';
        } else {
            $result = $db->insert('words', [
                'word' => $word,
                'category' => $category,
                'difficulty' => $difficulty,
                'is_active' => 1,
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
        $word = sanitize_input($_POST['word'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $difficulty = sanitize_input($_POST['difficulty'] ?? 'متوسط');

        $result = $db->update('words',
            ['word' => $word, 'category' => $category, 'difficulty' => $difficulty],
            "id = ?", "i", [$wordId]
        );

        if ($result) {
            $message = '✅ کلمه با موفقیت به‌روزرسانی شد';
        } else {
            $error = 'خطا در به‌روزرسانی کلمه';
        }
    }

    elseif ($_POST['action'] === 'delete') {
        $wordId = (int)$_POST['word_id'];
        $result = $db->update('words', ['is_active' => 0], "id = ?", "i", [$wordId]);

        if ($result) {
            $message = '✅ کلمه با موفقیت حذف شد';
        } else {
            $error = 'خطا در حذف کلمه';
        }
    }

    elseif ($_POST['action'] === 'restore') {
        $wordId = (int)$_POST['word_id'];
        $result = $db->update('words', ['is_active' => 1], "id = ?", "i", [$wordId]);

        if ($result) {
            $message = '✅ کلمه با موفقیت بازگردانی شد';
        } else {
            $error = 'خطا در بازگردانی کلمه';
        }
    }
}

// دریافت فیلتر
$filter = $_GET['filter'] ?? 'active';
$search = sanitize_input($_GET['search'] ?? '');
$category_filter = sanitize_input($_GET['category'] ?? '');

// ساخت query
$params = [];
$types = '';
$whereClause = $filter === 'active' ? "is_active = 1" : "is_active = 0";

if (!empty($search)) {
    $whereClause .= " AND word LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

if (!empty($category_filter)) {
    $whereClause .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

// دریافت کلمات
$words = $db->select("SELECT * FROM words WHERE $whereClause ORDER BY created_at DESC", $types, $params);

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
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="message error-message">
            <?php echo $error; ?>
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
                                        <button class="btn btn-secondary" onclick='editWord(<?php echo json_encode($word, JSON_UNESCAPED_UNICODE); ?>)'>✏️ ویرایش</button>
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
        const modal = document.getElementById('wordModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const wordForm = document.getElementById('wordForm');
        const wordIdInput = document.getElementById('wordId');
        const wordInput = document.getElementById('word');
        const categoryInput = document.getElementById('category');
        const difficultyInput = document.getElementById('difficulty');

        function openAddWordModal() {
            modalTitle.textContent = '➕ افزودن کلمه جدید';
            formAction.value = 'add';
            wordForm.reset();
            wordIdInput.value = '';
            modal.classList.add('active');
        }

        function closeWordModal() {
            modal.classList.remove('active');
        }

        function editWord(wordData) {
            modalTitle.textContent = '✏️ ویرایش کلمه';
            formAction.value = 'update';
            wordIdInput.value = wordData.id;
            wordInput.value = wordData.word;
            categoryInput.value = wordData.category;
            difficultyInput.value = wordData.difficulty;
            modal.classList.add('active');
        }

        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeWordModal();
            }
        });

        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.style.display = 'none', 500);
            });
        }, 5000);
    </script>
</body>
</html>