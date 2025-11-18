<?php
// ============================================
// Webhook - دریافت و پردازش پیام‌های تلگرام
// ============================================

require_once '../init.php';

// ایجاد اتصالات
$telegram = new TelegramAPI(BOT_TOKEN);
$userManager = new UserManager($db);
$gameManager = new GameManager($db, $telegram, $userManager);

// دریافت داده‌های ورودی و پردازش
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    exit();
}

try {
    if (isset($input['message'])) {
        handleMessage($input['message'], $telegram, $userManager, $gameManager);
    } elseif (isset($input['callback_query'])) {
        handleCallback($input['callback_query'], $telegram, $userManager, $gameManager);
    }
} catch (Exception $e) {
    // لاگ کردن خطاهای احتمالی
    file_put_contents(LOG_DIR . '/webhook_errors.log', date('Y-m-d H:i:s') . " | ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

echo json_encode(['ok' => true]);

// ============================================
// توابع پردازشگر
// ============================================

function handleMessage($message, $telegram, $userManager, $gameManager) {
    $userId = $message['from']['id'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';

    // ثبت‌نام یا به‌روزرسانی اطلاعات کاربر
    $userManager->registerUser($userId, $message['from']['username'] ?? '', $message['from']['first_name'] ?? '');

    // بررسی وضعیت فعلی کاربر (آیا در حال بازی است؟)
    $userState = $gameManager->getUserState($userId);

    if ($userState && $userState['state'] === 'playing_esmfamil') {
        // اگر کاربر در حال بازی است، پیام او را به عنوان پاسخ پردازش کن
        $gameId = $userState['data']['game_id'];
        $result = $gameManager->processPlayerAnswers($userId, $gameId, $text);

        $responseText = "✅ بازی تمام شد!\n\n";
        $responseText .= "امتیاز شما: " . $result['score'] . "\n\n";
        $responseText .= "پاسخ‌های ثبت شده:\n";
        foreach ($result['answers'] as $category => $answer) {
            $responseText .= "<b>" . htmlspecialchars($category) . ":</b> " . htmlspecialchars($answer) . "\n";
        }
        $telegram->sendMessage($chatId, $responseText, KeyboardBuilder::mainMenu());

    } else {
        // اگر در حال بازی نیست، دستورات اصلی را پردازش کن
        if (strpos($text, '/start') === 0) {
            $telegram->sendMessage($chatId, "👋 به بازی اسم و فامیل خوش آمدید!", KeyboardBuilder::mainMenu());
        }
    }
}

function handleCallback($callback, $telegram, $userManager, $gameManager) {
    $userId = $callback['from']['id'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'];

    // پاسخ اولیه برای جلوگیری از نمایش لودینگ روی دکمه
    $telegram->answerCallbackQuery($callback['id']);

    if ($data === 'game_single') {
        $game = $gameManager->createNewGame($userId);

        $responseText = "
🚀 بازی شروع شد! حرف انتخاب شده: <b>" . $game['letter'] . "</b>

لطفاً پاسخ‌های خود را در قالب زیر، هر کدام در یک خط، ارسال کنید:
<i>اسم: [پاسخ]
فامیل: [پاسخ]
شهر: [پاسخ]
کشور: [پاسخ]
غذا: [پاسخ]
میوه: [پاسخ]
حیوان: [پاسخ]
اشیا: [پاسخ]</i>

شما ۳ دقیقه فرصت دارید!
        ";
        $telegram->editMessage($chatId, $messageId, $responseText);
    }

    elseif ($data === 'back_main') {
        $telegram->editMessage($chatId, $messageId, "منوی اصلی", KeyboardBuilder::mainMenu());
    }
}
