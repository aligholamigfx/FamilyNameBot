<?php
// ============================================
// Webhook (نسخه نهایی و اصلاح شده)
// ============================================

require_once '../init.php';

// ایجاد اتصالات
$telegram = new TelegramAPI(BOT_TOKEN);
$userManager = new UserManager($db);
$gameManager = new GameManager($db);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { exit(); }

try {
    if (isset($input['message'])) {
        handleMessage($input['message'], $telegram, $userManager, $gameManager);
    } elseif (isset($input['callback_query'])) {
        handleCallback($input['callback_query'], $telegram, $userManager, $gameManager);
    }
} catch (Exception $e) {
    file_put_contents(LOG_DIR . '/webhook_errors.log', date('Y-m-d H:i:s') . " | ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

// ============================================
// توابع پردازشگر
// ============================================

function handleMessage($message, $telegram, $userManager, $gameManager) {
    $userId = $message['from']['id'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';

    $userManager->registerUser($userId, $message['from']['username'] ?? '', $message['from']['first_name'] ?? '');

    // بررسی اینکه آیا کاربر در یک بازی فعال است یا خیر
    $activeGame = $gameManager->getActiveGameForUser($userId);

    if ($activeGame) {
        // اگر کاربر دکمه "تمام" را زده باشد
        if ($text === '🏁 تمام!') {
            $gameManager->endRound($activeGame['game_id'], $userId);
            $telegram->sendMessage($chatId, "⏳ شما بازی را تمام کردید! ۱۰ ثانیه فرصت برای دیگران...", KeyboardBuilder::mainMenu());

            // این بخش برای یک ربات واقعی باید به صورت غیرهمزمان (asynchronous) اجرا شود
            sleep(10);
            $gameManager->calculateScores($activeGame['game_id']);

            $players = $gameManager->getGamePlayers($activeGame['game_id']);
            foreach ($players as $player) {
                // ارسال پیام نتایج به همه بازیکنان
                $telegram->sendMessage($player['user_id'], "🏁 بازی تمام شد! برای دیدن امتیازات، دکمه زیر را بزنید.", KeyboardBuilder::gameResults($activeGame['game_id']));
            }
        } else {
            // اگر پیام متنی ارسال کرده، به عنوان پاسخ ثبت می‌شود
            $gameManager->submitAnswers($activeGame['game_id'], $userId, $text);
            $telegram->sendMessage($chatId, "✅ پاسخ‌های شما ثبت شد. می‌توانید پاسخ‌های بیشتری ارسال کنید یا دکمه «تمام!» را بزنید.");
        }

    } else {
        // اگر کاربر در بازی فعال نیست، دستورات اصلی را پردازش کن
        if ($text === '🚀 بازی جدید') {
            $game = $gameManager->createGame($userId);
            $telegram->sendMessage($chatId, "✅ لابی بازی جدید ساخته شد!\n\nمنتظر بمانید تا دیگران با دکمه زیر به بازی ملحق شوند. پس از جمع شدن بازیکنان، دکمه «شروع بازی» را بزنید.", KeyboardBuilder::gameLobby($game['game_id'], true));
        } else {
            $telegram->sendMessage($chatId, "👋 به بازی اسم و فامیل خوش آمدید!", KeyboardBuilder::mainMenu());
        }
    }
}

function handleCallback($callback, $telegram, $userManager, $gameManager) {
    $userId = $callback['from']['id'];
    $chatId = $callback['message']['chat']['id'];
    $data = $callback['data'];

    $telegram->answerCallbackQuery($callback['id']);

    if (strpos($data, 'join_') === 0) {
        $gameId = str_replace('join_', '', $data);
        if ($gameManager->joinGame($gameId, $userId)) {
            $telegram->sendMessage($chatId, "✅ شما به بازی پیوستید!");
        } else {
            $telegram->sendMessage($chatId, "❌ شما از قبل در این بازی هستید.");
        }

    } elseif (strpos($data, 'start_') === 0) {
        $gameId = str_replace('start_', '', $data);
        $game = $gameManager->getGame($gameId);

        if ($game['creator_id'] == $userId) {
            $startedGame = $gameManager->startGame($gameId);
            $players = $gameManager->getGamePlayers($gameId);

            $responseText = "🚀 بازی شروع شد! حرف: <b>" . $startedGame['letter'] . "</b>\n\nپاسخ‌های خود را در قالب 'دسته: پاسخ' ارسال کنید. پس از اتمام، دکمه «تمام!» را از کیبورد اصلی بزنید.";

            foreach ($players as $player) {
                $telegram->sendMessage($player['user_id'], $responseText, KeyboardBuilder::inGame());
            }
        }
    }
}
