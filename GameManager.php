<?php
// ============================================
// کلاس مدیریت بازی اسم و فامیل
// ============================================

class GameManager {
    private $db;
    private $telegram;
    private $userManager;

    // لیست حروف الفبای فارسی برای انتخاب تصادفی
    private $letters = ['ا', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی'];

    // دسته‌بندی‌های استاندارد بازی
    public static $categories = ['اسم', 'فامیل', 'شهر', 'کشور', 'غذا', 'میوه', 'حیوان', 'اشیا'];

    public function __construct(Database $db, TelegramAPI $telegram, UserManager $userManager) {
        $this->db = $db;
        $this->telegram = $telegram;
        $this->userManager = $userManager;
    }

    /**
     * شروع یک بازی جدید اسم و فامیل (تک‌نفره)
     */
    public function createNewGame($userId) {
        $gameId = uniqid('esmfamil_');
        $letter = $this->letters[array_rand($this->letters)];

        $this->db->insert('games', [
            'game_id' => $gameId,
            'type' => 'single',
            'creator_id' => $userId,
            'status' => 'active',
            'words' => json_encode(['letter' => $letter, 'answers' => []]),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->db->insert('game_players', [
            'game_id' => $gameId,
            'user_id' => $userId,
            'joined_at' => date('Y-m-d H:i:s')
        ]);

        // به‌روزرسانی وضعیت کاربر برای دریافت پاسخ
        $this->setUserState($userId, 'playing_esmfamil', ['game_id' => $gameId, 'letter' => $letter]);

        return ['game_id' => $gameId, 'letter' => $letter];
    }

    /**
     * پردازش پاسخ‌های بازیکن
     */
    public function processPlayerAnswers($userId, $gameId, $text) {
        $answers = explode("\n", $text);
        $parsedAnswers = [];
        $score = 0;

        foreach ($answers as $line) {
            // هر خط باید در فرمت "دسته: پاسخ" باشد
            if (strpos($line, ':') !== false) {
                list($category, $answer) = explode(':', $line, 2);
                $category = trim($category);
                $answer = trim($answer);

                if (in_array($category, self::$categories) && !empty($answer)) {
                    $parsedAnswers[$category] = $answer;
                    // امتیازدهی ساده: 10 امتیاز برای هر پاسخ کامل
                    $score += 10;
                }
            }
        }

        // اتمام بازی و ثبت نتایج
        $this->finishGame($gameId, $userId, $score, $parsedAnswers);
        $this->clearUserState($userId);

        return ['score' => $score, 'answers' => $parsedAnswers];
    }

    /**
     * پایان دادن به بازی
     */
    private function finishGame($gameId, $userId, $score, $answers) {
        // دریافت اطلاعات بازی برای آپدیت `words`
        $game = $this->getGame($gameId);
        $gameData = json_decode($game['words'], true);
        $gameData['answers'] = $answers;

        $xpReward = floor($score * 1.5);
        $coinBonus = floor($score / 10);

        if ($coinBonus > 0) {
            $this->userManager->addCoins($userId, $coinBonus, 'free');
        }

        $newRank = $this->userManager->addXP($userId, $xpReward);

        $this->db->update('games',
            ['status' => 'finished', 'winner_id' => $userId, 'words' => json_encode($gameData)],
            "game_id = ?", "s", [$gameId]
        );

        $this->db->update('game_players',
            ['score' => $score, 'is_winner' => 1, 'finished_at' => date('Y-m-d H:i:s')],
            "game_id = ? AND user_id = ?", "si", [$gameId, $userId]
        );

        $this->userManager->updateGameStats($userId, true);

        return [
            'score' => $score,
            'coins' => $coinBonus,
            'xp' => $xpReward,
            'rank_up' => $newRank
        ];
    }

    /**
     * تنظیم وضعیت کاربر (برای مثال: در حال بازی)
     */
    public function setUserState($userId, $state, $data = []) {
        // این یک پیاده‌سازی ساده است. در یک سیستم واقعی، این باید در دیتابیس یا کش ذخیره شود
        $_SESSION['user_state'][$userId] = ['state' => $state, 'data' => $data];
    }

    /**
     * دریافت وضعیت کاربر
     */
    public function getUserState($userId) {
        return $_SESSION['user_state'][$userId] ?? null;
    }

    /**
     * پاک کردن وضعیت کاربر
     */
    public function clearUserState($userId) {
        unset($_SESSION['user_state'][$userId]);
    }

    public function getGame($gameId) {
        return $this->db->selectOne("SELECT * FROM games WHERE game_id = ?", "s", [$gameId]);
    }
}
