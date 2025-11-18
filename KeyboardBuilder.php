<?php
// ============================================
// کلاس ساخت کیبوردهای تلگرام (نسخه بازسازی شده)
// ============================================

class KeyboardBuilder {

    /**
     * منوی اصلی ربات
     */
    public static function mainMenu() {
        return [
            'keyboard' => [
                [['text' => '🚀 بازی جدید']],
                [['text' => '🏆 رتبه‌بندی'], ['text' => '👤 پروفایل']],
            ],
            'resize_keyboard' => true
        ];
    }

    /**
     * کیبورد لابی بازی (وقتی بازی در حالت انتظار است)
     */
    public static function gameLobby($gameId, $isCreator = false) {
        $keyboard = [[['text' => '➡️ پیوستن به بازی', 'callback_data' => 'join_' . $gameId]]];
        if ($isCreator) {
            $keyboard[] = [['text' => '✅ شروع بازی', 'callback_data' => 'start_' . $gameId]];
        }
        $keyboard[] = [['text' => '❌ لغو بازی', 'callback_data' => 'cancel_' . $gameId]];

        return ['inline_keyboard' => $keyboard];
    }

    /**
     * کیبورد داخل بازی (وقتی بازی فعال است)
     */
    public static function inGame() {
        return [
            'keyboard' => [
                [['text' => '🏁 تمام!']],
            ],
            'resize_keyboard' => true
        ];
    }

    /**
     * کیبورد نمایش نتایج
     */
    public static function gameResults($gameId)
    {
        return [
            'inline_keyboard' => [
                [['text' => '🏆 نمایش امتیازات کامل', 'callback_data' => 'scores_' . $gameId]],
                [['text' => 'Главное меню', 'callback_data' => 'back_main']]
            ]
        ];
    }
}
