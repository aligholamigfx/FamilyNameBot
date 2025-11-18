<?php
// ============================================
// Webhook - دریافت و پردازش پیام‌های تلگرام
// ============================================

// شامل کردن تمام کلاس‌ها
require_once '../config.php';
require_once '../Database.php';
require_once '../TelegramAPI.php';
require_once '../UserManager.php';
require_once '../GameManager.php';
require_once '../ShopManager.php';
require_once '../PaymentHandler.php';
require_once '../KeyboardBuilder.php';
require_once '../RankingManager.php';
require_once '../AchievementManager.php';

// ایجاد اتصالات
$db = new Database();
$telegram = new TelegramAPI(BOT_TOKEN);
$userManager = new UserManager($db);
$gameManager = new GameManager($db, $telegram, $userManager);
$shopManager = new ShopManager($db, $userManager);
$payment = new PaymentHandler($db, CRYPTOMUS_API_KEY, CRYPTOMUS_MERCHANT_UUID);
$rankingManager = new RankingManager($db);
$achievementManager = new AchievementManager($db, $userManager);

// دریافت داده‌های ورودی
$input = json_decode(file_get_contents('php://input'), true);

// ثبت در لاگ
file_put_contents(LOG_DIR . '/webhook_' . date('Y-m-d') . '.log', 
    date('Y-m-d H:i:s') . " | " . json_encode($input) . "\n", 
    FILE_APPEND);

// پردازش پیام‌ها
if (isset($input['message'])) {
    handleMessage($input['message'], $telegram, $userManager, $gameManager, $db);
}

// پردازش callback query
elseif (isset($input['callback_query'])) {
    handleCallback($input['callback_query'], $telegram, $userManager, $gameManager, $shopManager, 
                   $payment, $rankingManager, $achievementManager, $db);
}

echo json_encode(['ok' => true]);

// ============================================
// توابع پردازش
// ============================================

function handleMessage($message, $telegram, $userManager, $gameManager, $db) {
    $userId = $message['from']['id'];
    $username = $message['from']['username'] ?? 'Unknown';
    $firstName = $message['from']['first_name'] ?? '';
    $lastName = $message['from']['last_name'] ?? '';
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    
    // ثبت کاربر جدید
    $userManager->registerUser($userId, $username, $firstName, $lastName);
    
    // دریافت اطلاعات کاربر
    $user = $userManager->getUserProfile($userId);
    $rank = $user['rank'];
    
    // پردازش دستورات
    if (strpos($text, '/start') === 0) {
        $welcome = "👋 خوش آمدید {$user['first_name']}!\n\n";
        $welcome .= "🎮 یک بازی سرگرم‌کننده با چالش‌های متنوع\n";
        $welcome .= "💎 کسب سکه‌های ارزشمند\n";
        $welcome .= "🏆 رقابت برای رتبه‌های بالا\n";
        $welcome .= "🎁 دستیابی‌های جذاب\n\n";
        $welcome .= "از منو زیر شروع کنید:";
        
        $telegram->sendMessage($chatId, $welcome, KeyboardBuilder::mainMenu());
    }
    
    elseif ($text === '🎮 بازی') {
        $gameMenu = "🎮 انتخاب نوع بازی:\n\n";
        $gameMenu .= "🎯 بازی تک‌نفره\n";
        $gameMenu .= "👥 بازی چند‌نفره\n";
        $gameMenu .= "🏁 بازی گروهی رقابتی";
        $telegram->sendMessage($chatId, $gameMenu, KeyboardBuilder::gameMenu());
    }
    
    elseif ($text === '👤 پروفایل') {
        showUserProfile($telegram, $chatId, $user, $userManager, $achievementManager);
    }
    
    elseif ($text === '💎 فروشگاه') {
        $telegram->sendMessage($chatId, "🛍️ فروشگاه\n\nچه کاری می‌خواهید انجام دهید؟", 
                              KeyboardBuilder::shopMenu());
    }
    
    elseif ($text === '🏆 رتبه‌بندی') {
        $telegram->sendMessage($chatId, "🏆 رتبه‌بندی و آمار\n\nچه اطلاعاتی می‌خواهید مشاهده کنید؟", 
                              KeyboardBuilder::ratingMenu());
    }
    
    elseif ($text === '⚙️ تنظیمات') {
        $telegram->sendMessage($chatId, "⚙️ تنظیمات\n\nتنظیمات مورد نظر خود را انتخاب کنید:", 
                              KeyboardBuilder::settingsMenu());
    }
    
    elseif ($text === '❓ راهنما') {
        showTutorial($telegram, $chatId);
    }
}

function handleCallback($callback, $telegram, $userManager, $gameManager, $shopManager, 
                        $payment, $rankingManager, $achievementManager, $db) {
    $userId = $callback['from']['id'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'];
    
    $user = $userManager->getUserProfile($userId);
    
    // بازی تک‌نفره
    if ($data === 'game_single') {
        $result = $gameManager->createSinglePlayerGame($userId);
        
        $gameText = "🎮 بازی تک‌نفره شروع شد!\n\n";
        $gameText .= "🎯 کلمات:\n";
        foreach ($result['words'] as $index => $word) {
            $gameText .= ($index + 1) . ". " . $word . "\n";
        }
        $gameText .= "\n⏱️ زمان: 5 دقیقه\n";
        $gameText .= "💡 هرکلمه ✓: +10 امتیاز";
        
        $telegram->editMessage($chatId, $messageId, $gameText, KeyboardBuilder::finishGameKeyboard($result['game_id']));
    }
    
    // تکمیل بازی
    elseif (strpos($data, 'finish_') === 0) {
        $gameId = str_replace('finish_', '', $data);
        $score = 50;
        
        $reward = $gameManager->finishSinglePlayerGame($gameId, $userId, $score);
        $newAchievements = $achievementManager->checkAndUnlockAchievements($userId);
        $user = $userManager->getUserProfile($userId);
        $rank = RANKS[$user['rank_id']];
        
        $resultText = "🎉 بازی پایان یافت!\n\n";
        $resultText .= "📊 نتایج:\n";
        $resultText .= "🎯 امتیاز: " . $reward['score'] . "\n";
        $resultText .= "💎 سکه کسب شده: " . $reward['coins'] . " 🟢\n";
        $resultText .= "⭐ XP کسب شده: " . $reward['xp'] . "\n";
        
        if ($reward['rank_up']) {
            $newRank = RANKS[$reward['rank_up']];
            $resultText .= "\n🎊 تبریک! به رتبه " . $newRank['name'] . " " . $newRank['icon'] . " ارتقا یافتید!\n";
        }
        
        if (!empty($newAchievements)) {
            $resultText .= "\n🎁 دستیابی جدید:\n";
            foreach ($newAchievements as $ach) {
                $resultText .= "{$ach['achievement']['icon']} {$ach['achievement']['name']}\n";
            }
        }
        
        $telegram->editMessage($chatId, $messageId, $resultText, KeyboardBuilder::gameResultKeyboard());
    }
    
    // خرید سکه
    elseif ($data === 'buy_coins') {
        $text = "💎 افزایش موجودی\n\n";
        $text .= "💰 موجودی فعلی:\n";
        $text .= "🔴 سکه‌های پریمیوم: {$user['premium_coins']}\n";
        $text .= "🟢 سکه‌های رایگان: {$user['free_coins']}\n";
        $text .= "━━━━━━━━━━━━━━━━\n";
        $text .= "📦 کل: {$user['total_coins']} سکه\n\n";
        $text .= "بسته‌های دسترس:";
        
        $telegram->editMessage($chatId, $messageId, $text, KeyboardBuilder::coinPackages());
    }
    
    // پرداخت بسته‌های سکه
    elseif (strpos($data, 'buy_') === 0) {
        $package = (int)str_replace('buy_', '', $data);
        $packages = $shopManager->getCoinPackages();
        
        if (isset($packages[$package])) {
            $pkg = $packages[$package];
            $confirmText = "💳 تایید خرید\n\n";
            $confirmText .= "📦 بسته: {$pkg['label']}\n";
            $confirmText .= "💵 مبلغ: \${$pkg['price']}\n\n";
            $confirmText .= "آیا می‌خواهید ادامه دهید?";
            
            $paymentResult = $payment->createPayment($userId, $pkg['price'], 'USDT');
            
            if ($paymentResult['success']) {
                $telegram->editMessage($chatId, $messageId, $confirmText, [
                    'inline_keyboard' => [
                        [['text' => '✅ پرداخت', 'url' => $paymentResult['url']]],
                        [['text' => '❌ انصراف', 'callback_data' => 'back_shop']]
                    ]
                ]);
            }
        }
    }
    
    // فروشگاه اقلام
    elseif ($data === 'shop_items') {
        $items = $shopManager->getItems();
        $shopText = "🛍️ فروشگاه آیتم‌ها\n\n";
        
        foreach ($items as $item) {
            $shopText .= "{$item['icon']} {$item['name']}\n";
            $shopText .= "💎 {$item['price']} سکه\n";
            $shopText .= "{$item['description']}\n";
            $shopText .= "━━━━━━━━━━\n";
        }
        
        $telegram->editMessage($chatId, $messageId, $shopText, KeyboardBuilder::shopItemsKeyboard($items));
    }
    
    // انتخاب آیتم
    elseif (strpos($data, 'item_') === 0) {
        $itemId = (int)str_replace('item_', '', $data);
        $item = $shopManager->getItemById($itemId);
        
        if ($item) {
            $itemText = "{$item['icon']} {$item['name']}\n\n";
            $itemText .= "{$item['description']}\n\n";
            $itemText .= "💎 قیمت: {$item['price']} سکه\n";
            $itemText .= "📦 دسته: {$item['category']}";
            
            $telegram->editMessage($chatId, $messageId, $itemText, 
                                  KeyboardBuilder::confirmPurchase($itemId, $item['price']));
        }
    }
    
    // تأیید خرید
    elseif (strpos($data, 'confirm_buy_') === 0) {
        $itemId = (int)str_replace('confirm_buy_', '', $data);
        $result = $shopManager->purchaseItem($userId, $itemId);
        
        if ($result) {
            $confirmText = "✅ خرید موفق!\n\n";
            $confirmText .= "📦 {$result['name']}\n";
            $confirmText .= "💎 {$result['price']} سکه کم شد\n\n";
            $confirmText .= "🎉 خرید شما با موفقیت انجام شد!";
            
            $telegram->editMessage($chatId, $messageId, $confirmText, [
                'inline_keyboard' => [
                    [['text' => '🛍️ ادامه خرید', 'callback_data' => 'shop_items']],
                    [['text' => '⬅️ بازگشت', 'callback_data' => 'back_shop']]
                ]
            ]);
        } else {
            $telegram->answerCallbackQuery($callback['id'], '❌ سکه کافی ندارید!', true);
        }
    }
    
    // موجودی
    elseif ($data === 'my_balance') {
        $user = $userManager->getUserProfile($userId);
        $balanceText = "💰 موجودی شما\n\n";
        $balanceText .= "🔴 سکه‌های پریمیوم: {$user['premium_coins']}\n";
        $balanceText .= "🟢 سکه‌های رایگان: {$user['free_coins']}\n";
        $balanceText .= "━━━━━━━━━━━━━━━━\n";
        $balanceText .= "📊 کل: {$user['total_coins']} سکه\n\n";
        $balanceText .= "💡 نکات:\n";
        $balanceText .= "• سکه‌های پریمیوم: خریداری شده با رمزارز\n";
        $balanceText .= "• سکه‌های رایگان: کسب از بازی و رتبه‌بندی";
        
        $telegram->editMessage($chatId, $messageId, $balanceText, [
            'inline_keyboard' => [
                [['text' => '💳 افزایش موجودی', 'callback_data' => 'buy_coins']],
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_shop']]
            ]
        ]);
    }
    
    // رتبه من
    elseif ($data === 'my_rank') {
        $progress = $userManager->getRankProgress($userId);
        $rankText = "🏆 رتبه شما\n\n";
        $rankText .= "{$progress['current_icon']} {$progress['current_rank']}\n\n";
        
        if (!isset($progress['max_level'])) {
            $filled = round(($progress['progress_percent'] / 100) * 10);
            $bar = '█' . str_repeat('█', $filled) . str_repeat('░', 10 - $filled) . '█';
            $rankText .= "📈 پیشرفت به {$progress['next_rank']}:\n";
            $rankText .= "$bar\n";
            $rankText .= "{$progress['progress_percent']}% ({$progress['current_xp']}/{$progress['next_rank_xp']} XP)\n";
            $rankText .= "🎯 نیاز: {$progress['xp_needed']} XP دیگر";
        } else {
            $rankText .= "🎊 شما به بالاترین رتبه رسیده‌اید!\n";
            $rankText .= "⭐ ادامه دهید و رکورد خود را بهبود ببخشید";
        }
        
        $telegram->editMessage($chatId, $messageId, $rankText, [
            'inline_keyboard' => [
                [['text' => '🏆 جدول رتبه‌بندی', 'callback_data' => 'rank_top']],
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']]
            ]
        ]);
    }
    
    // جدول رتبه‌بندی
    elseif ($data === 'rank_top') {
        $topPlayers = $userManager->getTopPlayers(10);
        $rankingText = "🏆 بهترین بازیکنان\n\n";
        
        foreach ($topPlayers as $index => $player) {
            $rank = RANKS[$player['rank_id']];
            $medal = match($index) { 
                0 => '🥇 ', 
                1 => '🥈 ', 
                2 => '🥉 ', 
                default => '   ' 
            };
            $rankingText .= "{$medal}{$rank['icon']} {$player['first_name']}\n";
            $rankingText .= "   ⭐ {$player['total_xp']} XP | 🎯 {$player['games_won']} برد\n";
        }
        
        $telegram->editMessage($chatId, $messageId, $rankingText, [
            'inline_keyboard' => [
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']]
            ]
        ]);
    }
    
    // آمار شخصی
    elseif ($data === 'my_stats') {
        $stats = $gameManager->getGameStats($userId);
        $user = $userManager->getUserProfile($userId);
        $rank = $user['rank'];
        
        $statsText = "📊 آمار شخصی\n\n";
        $statsText .= "👤 نام: {$user['first_name']} {$user['last_name']}\n";
        $statsText .= "{$rank['icon']} رتبه: {$rank['name']}\n";
        $statsText .= "⭐ XP کل: {$user['total_xp']}\n\n";
        $statsText .= "🎮 بازی‌ها:\n";
        $statsText .= "📈 کل بازی: {$stats['total_games']}\n";
        $statsText .= "✅ برد‌ها: {$stats['wins']}\n";
        $statsText .= "📊 میانگین امتیاز: " . round($stats['avg_score'], 1) . "\n";
        $statsText .= "🔝 بهترین امتیاز: {$stats['best_score']}\n";
        $statsText .= "📉 نسبت برد: {$user['win_rate']}%\n\n";
        $statsText .= "💎 موجودی:\n";
        $statsText .= "🔴 پریمیوم: {$user['premium_coins']}\n";
        $statsText .= "🟢 رایگان: {$user['free_coins']}";
        
        $telegram->editMessage($chatId, $messageId, $statsText, [
            'inline_keyboard' => [
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']]
            ]
        ]);
    }
    
    // بازگشت به منو اصلی
    elseif ($data === 'back_main') {
        $user = $userManager->getUserProfile($userId);
        $backText = "👋 خوش آمدید {$user['first_name']}!\n\n";
        $backText .= "{$user['rank']['icon']} رتبه: {$user['rank']['name']}\n";
        $backText .= "⭐ XP: {$user['total_xp']}\n";
        $backText .= "💎 موجودی: {$user['total_coins']} سکه\n\n";
        $backText .= "از منو زیر انتخاب کنید:";
        
        $telegram->editMessage($chatId, $messageId, $backText, KeyboardBuilder::mainMenu());
    }
    
    // بازگشت به فروشگاه
    elseif ($data === 'back_shop') {
        $telegram->editMessage($chatId, $messageId,
            "🛍️ فروشگاه\n\nچه کاری می‌خواهید انجام دهید؟",
            KeyboardBuilder::shopMenu()
        );
    }
    
    $telegram->answerCallbackQuery($callback['id']);
}

function showUserProfile($telegram, $chatId, $user, $userManager, $achievementManager) {
    $rank = $user['rank'];
    $achievements = $achievementManager->getAchievementProgress($userId = $user['user_id']);
    
    $profileText = "👤 پروفایل شما\n\n";
    $profileText .= "📝 نام: {$user['first_name']} {$user['last_name']}\n";
    $profileText .= "🆔 کاربری: @{$user['username']}\n";
    $profileText .= "📅 عضویت: " . date('Y-m-d', strtotime($user['created_at'])) . "\n\n";
    
    $profileText .= "🏆 رتبه و امتیاز:\n";
    $profileText .= "{$rank['icon']} {$rank['name']}\n";
    $profileText .= "⭐ XP: {$user['total_xp']}\n\n";
    
    $profileText .= "💎 سکه‌ها:\n";
    $profileText .= "🔴 پریمیوم: {$user['premium_coins']}\n";
    $profileText .= "🟢 رایگان: {$user['free_coins']}\n";
    $profileText .= "📦 کل: {$user['total_coins']}\n\n";
    
    $profileText .= "🎮 آمار بازی:\n";
    $profileText .= "📈 کل بازی: {$user['games_played']}\n";
    $profileText .= "✅ برد: {$user['games_won']}\n";
    $profileText .= "📉 نسبت: {$user['win_rate']}%\n\n";
    
    $profileText .= "🎁 دستیابی‌ها:\n";
    $profileText .= "{$achievements['unlocked']}/{$achievements['total']} ({$achievements['percentage']}%)";
    
    $telegram->sendMessage($chatId, $profileText, [
        'inline_keyboard' => [
            [['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']]
        ]
    ]);
}

function showTutorial($telegram, $chatId) {
    $tutorialText = "❓ راهنما و نکات\n\n";
    $tutorialText .= "🎮 چگونه بازی کنم؟\n";
    $tutorialText .= "1. بازی تک‌نفره انتخاب کنید\n";
    $tutorialText .= "2. کلمات را حدس بزنید\n";
    $tutorialText .= "3. امتیاز و سکه کسب کنید\n\n";
    
    $tutorialText .= "💎 سکه‌ها چگونه کار می‌کنند؟\n";
    $tutorialText .= "🔴 پریمیوم: خریداری شده با رمزارز\n";
    $tutorialText .= "🟢 رایگان: از بازی و رتبه‌بندی\n\n";
    
    $tutorialText .= "🏆 سیستم رتبه‌بندی:\n";
    $tutorialText .= "• هرچه بیشتر بازی کنید، XP بیشتری کسب کنید\n";
    $tutorialText .= "• به رتبه‌های بالاتر برسید\n";
    $tutorialText .= "• در جدول رتبه‌بندی رقابت کنید\n\n";
    
    $tutorialText .= "💡 نکات:\n";
    $tutorialText .= "• بهترین امتیازات را ثبت کنید\n";
    $tutorialText .= "• روزانه بازی کنید\n";
    $tutorialText .= "• دستیابی‌ها را جمع کنید";
    
    $telegram->sendMessage($chatId, $tutorialText, KeyboardBuilder::tutorialKeyboard());
}

?>