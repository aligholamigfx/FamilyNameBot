<?php
// ============================================
// Webhook (نسخه بازسازی شده برای اسم و فامیل)
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
    $userState = $gameManager->getUserState($userId);

    if ($userState && $userState['state'] === 'playing_esmfamil') {
        // اگر کاربر در حال بازی است، پیام او به عنوان پاسخ در نظر گرفته می‌شود
        $gameManager->submitAnswers($userState['data']['game_id'], $userId, $text);
        $telegram->sendMessage($chatId, "✅ پاسخ‌های شما ثبت شد. منتظر بمانید تا دیگران نیز پاسخ دهند یا دکمه «تمام!» را بزنید.");

    } elseif ($text === '🚀 بازی جدید') {
        $game = $gameManager->createGame($userId);
        $telegram->sendMessage($chatId, "✅ لابی بازی جدید ساخته شد!\n\nدیگران می‌توانند با دکمه زیر به بازی ملحق شوند. پس از جمع شدن بازیکنان، دکمه «شروع بازی» را بزنید.", KeyboardBuilder::gameLobby($game['game_id'], true));

    } elseif ($text === '🏁 تمام!') {
        if ($userState && $userState['state'] === 'submitting_answers') {
            $gameManager->endRound($userState['data']['game_id'], $userId);
            $gameManager->clearUserState($userId);
            $telegram->sendMessage($chatId, "⏳ شما بازی را تمام کردید! ۱۰ ثانیه فرصت برای دیگران...", KeyboardBuilder::mainMenu());

            // شروع شمارش معکوس و محاسبه امتیازات (در یک ربات واقعی این باید به صورت غیرهمزمان انجام شود)
            sleep(10);
            $gameManager->calculateScores($userState['data']['game_id']);

            // اطلاع‌رسانی به همه بازیکنان
            $players = $gameManager->getGamePlayers($userState['data']['game_id']);
            foreach ($players as $player) {
                $telegram->sendMessage($player['user_id'], "🏁 بازی تمام شد! نتایج در حال محاسبه است...", KeyboardBuilder::gameResults($userState['data']['game_id']));
            }
        }
    } else {
        $telegram->sendMessage($chatId, "👋 به بازی اسم و فامیل خوش آمدید!", KeyboardBuilder::mainMenu());
    }
}

function handleCallback($callback, $telegram, $userManager, $gameManager) {
    $userId = $callback['from']['id'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
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

        // فقط سازنده می‌تواند بازی را شروع کند
        if ($game['creator_id'] == $userId) {
            $startedGame = $gameManager->startGame($gameId);
            $players = $gameManager->getGamePlayers($gameId);

            $responseText = "🚀 بازی شروع شد! حرف: <b>" . $startedGame['letter'] . "</b>\n\nپاسخ‌های خود را در پیام‌های جداگانه و در قالب 'دسته: پاسخ' ارسال کنید. پس از اتمام، دکمه «تمام!» را بزنید.";

            // اطلاع‌رسانی به همه بازیکنان
            foreach ($players as $player) {
                $gameManager->setUserState($player['user_id'], 'submitting_answers', ['game_id' => $gameId]);
                $telegram->sendMessage($player['user_id'], $responseText, KeyboardBuilder::inGame());
            }
        }
    }
}
