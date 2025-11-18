<?php
// ============================================
// Webhook - دریافت و پردازش پیام‌های تلگرام
// ============================================

require_once '../init.php';
require_once '../TelegramAPI.php';
require_once '../UserManager.php';
require_once '../GameManager.php';
require_once '../ShopManager.php';
require_once '../PaymentHandler.php';
require_once '../KeyboardBuilder.php';
require_once '../RankingManager.php';
require_once '../AchievementManager.php';

// ایجاد اتصالات
$telegram = new TelegramAPI(BOT_TOKEN);
$userManager = new UserManager($db);
$gameManager = new GameManager($db, $telegram, $userManager);
$shopManager = new ShopManager($db, $userManager);
$payment = new PaymentHandler($db, CRYPTOMUS_API_KEY, CRYPTOMUS_MERCHANT_UUID);
$rankingManager = new RankingManager($db);
$achievementManager = new AchievementManager($db, $userManager);

// دریافت داده‌های ورودی
$input = json_decode(file_get_contents('php://input'), true);

// لاگ‌گیری
$log_file = LOG_DIR . '/webhook_' . date('Y-m-d') . '.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " | INPUT: " . json_encode($input) . "\n", FILE_APPEND);

function custom_log($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " | DEBUG: " . $message . "\n", FILE_APPEND);
}

// پردازش
try {
    if (isset($input['message'])) {
        handleMessage($input['message'], $telegram, $userManager, $gameManager, $shopManager, $rankingManager);
    } elseif (isset($input['callback_query'])) {
        handleCallback($input['callback_query'], $telegram, $userManager, $gameManager, $shopManager, $achievementManager, $payment, $rankingManager);
    }
} catch (Exception $e) {
    custom_log("FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

echo json_encode(['ok' => true]);

// ============================================
// توابع پردازش
// ============================================

function handleMessage($message, $telegram, $userManager, $gameManager, $shopManager, $rankingManager) {
    $userId = $message['from']['id'];
    $username = $message['from']['username'] ?? 'Unknown';
    $firstName = $message['from']['first_name'] ?? '';
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';

    $user = $userManager->getUser($userId);
    if (!$user) {
        $userManager->registerUser($userId, $username, $firstName);
    }

    if (strpos($text, '/start') === 0) {
        $telegram->sendMessage($chatId, "👋 خوش آمدید!", KeyboardBuilder::mainMenu());
    } elseif ($text === '🎮 بازی') {
        $telegram->sendMessage($chatId, "🎮 انتخاب نوع بازی:", KeyboardBuilder::gameMenu());
    } elseif ($text === '👤 پروفایل') {
        showUserProfile($chatId, $userId, $telegram, $userManager);
    } elseif ($text === '💎 فروشگاه') {
        $telegram->sendMessage($chatId, "🛍️ فروشگاه", KeyboardBuilder::shopMenu());
    } elseif ($text === '🏆 رتبه‌بندی') {
        $telegram->sendMessage($chatId, "🏆 رتبه‌بندی", KeyboardBuilder::ratingMenu());
    }
}

function handleCallback($callback, $telegram, $userManager, $gameManager, $shopManager, $achievementManager, $payment, $rankingManager) {
    $userId = $callback['from']['id'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'];

    if ($data === 'game_single') {
        $game = $gameManager->createSinglePlayerGame($userId);
        $gameText = "🎮 بازی تک‌نفره شروع شد!\n\nکلمات:\n";
        foreach($game['words'] as $word) { $gameText .= "- $word\n"; }
        $telegram->editMessage($chatId, $messageId, $gameText, KeyboardBuilder::finishGameKeyboard($game['game_id']));
    }

    elseif (strpos($data, 'finish_') === 0) {
        $gameId = str_replace('finish_', '', $data);
        $score = rand(30, 100);
        $result = $gameManager->finishSinglePlayerGame($gameId, $userId, $score);

        $resultText = "🎉 بازی پایان یافت!\n\n";
        $resultText .= "🎯 امتیاز: " . $result['score'] . "\n";
        $resultText .= "💎 سکه: +" . $result['coins'] . "\n";
        $resultText .= "⭐ XP: +" . $result['xp'];

        if ($result['rank_up']) {
            $newRank = RANKS[$result['rank_up']];
            $resultText .= "\n\n🎊 تبریک! به رتبه " . $newRank['name'] . " " . $newRank['icon'] . " ارتقا یافتید!";
        }
        $telegram->editMessage($chatId, $messageId, $resultText, KeyboardBuilder::gameResultKeyboard());
    }

    elseif ($data === 'back_main') {
        $telegram->editMessage($chatId, $messageId, "منوی اصلی", KeyboardBuilder::mainMenu());
    }

    $telegram->answerCallbackQuery($callback['id']);
}

function showUserProfile($chatId, $userId, $telegram, $userManager) {
    $profile = $userManager->getUserProfile($userId);
    $text = "👤 پروفایل شما:\n\n";
    $text .= "نام: {$profile['first_name']}\n";
    $text .= "رتبه: {$profile['rank']['name']} {$profile['rank']['icon']}\n";
    $text .= "امتیاز: {$profile['total_xp']} XP\n";
    $text .= "سکه: {$profile['total_coins']} 💎";
    $telegram->sendMessage($chatId, $text);
}
