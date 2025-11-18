<?php
// ============================================
// ساخت کیبورد‌های شیشه‌ای
// ============================================

class KeyboardBuilder {
    
    /**
     * منو اصلی
     */
    public static function mainMenu() {
        return [
            'keyboard' => [
                [
                    ['text' => '🎮 بازی'],
                    ['text' => '👤 پروفایل']
                ],
                [
                    ['text' => '💎 فروشگاه'],
                    ['text' => '🏆 رتبه‌بندی']
                ],
                [
                    ['text' => '⚙️ تنظیمات'],
                    ['text' => '❓ راهنما']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'is_persistent' => true
        ];
    }
    
    /**
     * منو بازی‌ها
     */
    public static function gameMenu() {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🎯 تک‌نفره', 'callback_data' => 'game_single'],
                    ['text' => '👥 چند‌نفره', 'callback_data' => 'game_multi']
                ],
                [
                    ['text' => '🏁 گروهی رقابتی', 'callback_data' => 'game_group'],
                    ['text' => '📋 بازی‌های من', 'callback_data' => 'my_games']
                ],
                [
                    ['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']
                ]
            ]
        ];
    }
    
    /**
     * منو فروشگاه
     */
    public static function shopMenu() {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '💰 افزایش موجودی', 'callback_data' => 'buy_coins'],
                    ['text' => '🎁 فروشگاه آیتم', 'callback_data' => 'shop_items']
                ],
                [
                    ['text' => '📊 موجودی من', 'callback_data' => 'my_balance'],
                    ['text' => '📜 تاریخچه خریدها', 'callback_data' => 'shop_history']
                ],
                [
                    ['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']
                ]
            ]
        ];
    }
    
    /**
     * بسته‌های سکه
     */
    public static function coinPackages() {
        return [
            'inline_keyboard' => [
                [['text' => '💎 100 سکه - $10', 'callback_data' => 'buy_100']],
                [['text' => '💎💎 500 سکه - $40 ✨ (50 پاداش)', 'callback_data' => 'buy_500']],
                [['text' => '💎💎💎 1000 سکه - $75 ⭐ (150 پاداش)', 'callback_data' => 'buy_1000']],
                [['text' => '💎💎💎💎 5000 سکه - $350 🔥 (1000 پاداش)', 'callback_data' => 'buy_5000']],
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_shop']]
            ]
        ];
    }
    
    /**
     * منو رتبه‌بندی
     */
    public static function ratingMenu() {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '⭐ برتر‌ترین‌ها', 'callback_data' => 'rank_top'],
                    ['text' => '🎯 بالاترین امتیاز', 'callback_data' => 'rank_score']
                ],
                [
                    ['text' => '👤 رتبه من', 'callback_data' => 'my_rank'],
                    ['text' => '📈 آمار شخصی', 'callback_data' => 'my_stats']
                ],
                [
                    ['text' => '📅 سراسری', 'callback_data' => 'rank_monthly'],
                    ['text' => '🎖️ دستیابی‌هایم', 'callback_data' => 'my_achievements']
                ],
                [
                    ['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']
                ]
            ]
        ];
    }
    
    /**
     * منو تنظیمات
     */
    public static function settingsMenu() {
        return [
            'inline_keyboard' => [
                [['text' => '🔔 اطلاع‌رسانی‌ها', 'callback_data' => 'settings_notify']],
                [['text' => '🌙 حالت تاریک', 'callback_data' => 'settings_dark']],
                [['text' => '🗣️ زبان', 'callback_data' => 'settings_lang']],
                [['text' => '🎨 تم', 'callback_data' => 'settings_theme']],
                [['text' => '📱 درباره ربات', 'callback_data' => 'settings_about']],
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']]
            ]
        ];
    }
    
    /**
     * تأیید خرید
     */
    public static function confirmPurchase($itemId, $price) {
        return [
            'inline_keyboard' => [
                [['text' => '✅ تأیید خرید', 'callback_data' => "confirm_buy_$itemId"]],
                [['text' => '❌ انصراف', 'callback_data' => 'back_shop']]
            ]
        ];
    }
    
    /**
     * کیبورد بله/خیر
     */
    public static function yesNoKeyboard() {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '✅ بله', 'callback_data' => 'yes'],
                    ['text' => '❌ خیر', 'callback_data' => 'no']
                ]
            ]
        ];
    }
    
    /**
     * کیبورد بازگشت
     */
    public static function backKeyboard() {
        return [
            'inline_keyboard' => [
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']]
            ]
        ];
    }
    
    /**
     * فیلترهای رتبه‌بندی
     */
    public static function rankingFilters() {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '⭐ بیشترین XP', 'callback_data' => 'rank_filter_xp'],
                    ['text' => '🎯 بیشترین برد', 'callback_data' => 'rank_filter_wins']
                ],
                [
                    ['text' => '🎮 بیشترین بازی', 'callback_data' => 'rank_filter_games'],
                    ['text' => '💎 بیشترین سکه', 'callback_data' => 'rank_filter_coins']
                ],
                [
                    ['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']
                ]
            ]
        ];
    }
    
    /**
     * منو پیام‌های توضیحی
     */
    public static function tutorialKeyboard() {
        return [
            'inline_keyboard' => [
                [['text' => '🎮 شروع بازی', 'callback_data' => 'game_single']],
                [['text' => '💎 خرید سکه', 'callback_data' => 'buy_coins']],
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']]
            ]
        ];
    }
    
    /**
     * منو رتبه‌ها
     */
    public static function rankSelectKeyboard() {
        $keyboard = ['inline_keyboard' => []];
        foreach (RANKS as $id => $rank) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "{$rank['icon']} {$rank['name']} ({$rank['min_xp']} XP)", 
                 'callback_data' => "rank_info_$id"]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']
        ];
        return $keyboard;
    }
    
    /**
     * منو آیتم‌های فروشگاه
     */
    public static function shopItemsKeyboard($items) {
        $keyboard = ['inline_keyboard' => []];
        foreach ($items as $item) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "{$item['icon']} {$item['name']} (💎 {$item['price']})", 
                 'callback_data' => 'item_' . $item['id']]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ بازگشت', 'callback_data' => 'back_shop']
        ];
        return $keyboard;
    }
    
    /**
     * دکمه انجام بازی
     */
    public static function finishGameKeyboard($gameId) {
        return [
            'inline_keyboard' => [
                [['text' => '✅ تمام کردم', 'callback_data' => 'finish_' . $gameId]],
                [['text' => '❌ انصراف', 'callback_data' => 'cancel_game']]
            ]
        ];
    }
    
    /**
     * دکمه‌های نتیجه بازی
     */
    public static function gameResultKeyboard() {
        return [
            'inline_keyboard' => [
                [['text' => '🔄 بازی دوباره', 'callback_data' => 'game_single']],
                [['text' => '📊 مشاهده آمار', 'callback_data' => 'my_stats']],
                [['text' => '⬅️ بازگشت', 'callback_data' => 'back_main']]
            ]
        ];
    }
    
    /**
     * دکمه‌های صفحه‌بندی
     */
    public static function paginationKeyboard($current_page = 1, $total_pages = 1) {
        $keyboard = [];
        
        if ($current_page > 1) {
            $keyboard[] = ['text' => '⬅️ قبل', 'callback_data' => 'page_' . ($current_page - 1)];
        }
        
        $keyboard[] = ['text' => "📄 $current_page / $total_pages", 'callback_data' => 'page_info'];
        
        if ($current_page < $total_pages) {
            $keyboard[] = ['text' => 'بعد ➡️', 'callback_data' => 'page_' . ($current_page + 1)];
        }
        
        return ['inline_keyboard' => [$keyboard]];
    }
}

?>