<?php
// ============================================
// کلاس مدیریت بازی اسم و فامیل (نسخه حرفه‌ای)
// ============================================

class GameManager {
    private $db;
    private $letters = ['ا', 'ب', 'پ', 'ت', 'ج', 'چ', 'خ', 'د', 'ر', 'ز', 'س', 'ش', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی'];
    public static $categories = ['اسم', 'فامیل', 'شهر', 'کشور', 'غذا', 'میوه', 'حیوان', 'اشیا'];

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * ایجاد یک لابی بازی جدید
     */
    public function createGame($userId, $groupId = null) {
        $gameId = uniqid('game_');
        $this->db->insert('games', [
            'game_id' => $gameId,
            'creator_id' => $userId,
            'group_id' => $groupId,
            'status' => 'waiting'
        ]);
        $this->joinGame($gameId, $userId);
        return $this->getGame($gameId);
    }

    /**
     * پیوستن یک بازیکن به بازی
     */
    public function joinGame($gameId, $userId) {
        $existingPlayer = $this->db->selectOne("SELECT id FROM game_players WHERE game_id = ? AND user_id = ?", "si", [$gameId, $userId]);
        if ($existingPlayer) {
            return false; // Already in game
        }
        return $this->db->insert('game_players', ['game_id' => $gameId, 'user_id' => $userId]);
    }

    /**
     * شروع بازی برای همه بازیکنان
     */
    public function startGame($gameId) {
        $letter = $this->letters[array_rand($this->letters)];
        $this->db->update(
            'games',
            ['status' => 'active', 'letter' => $letter, 'started_at' => date('Y-m-d H:i:s')],
            "game_id = ?", "s", [$gameId]
        );
        return $this->getGame($gameId);
    }

    /**
     * ثبت پاسخ‌های یک بازیکن
     */
    public function submitAnswers($gameId, $userId, $answersText) {
        $answers = $this->parseAnswers($answersText);
        $this->db->update(
            'game_players',
            ['answers' => json_encode($answers)],
            "game_id = ? AND user_id = ?", "si", [$gameId, $userId]
        );
        return $answers;
    }

    /**
     * وقتی یک بازیکن دکمه "پایان" را می‌زند
     */
    public function endRound($gameId, $finisherUserId) {
        // دادن امتیاز اضافی به اولین نفر
        $this->db->update('game_players', ['bonus_points' => 10], "game_id = ? AND user_id = ?", "si", [$gameId, $finisherUserId]);
        // تغییر وضعیت بازی به "در حال امتیازدهی"
        $this->db->update('games', ['status' => 'scoring'], "game_id = ?", "s", [$gameId]);
        return true;
    }

    /**
     * محاسبه و ثبت نهایی امتیازات
     */
    public function calculateScores($gameId) {
        $players = $this->getGamePlayers($gameId);
        $game = $this->getGame($gameId);
        $letter = $game['letter'];
        $allAnswers = [];

        // جمع‌آوری تمام پاسخ‌ها برای مقایسه
        foreach (self::$categories as $category) {
            $allAnswers[$category] = [];
            foreach ($players as $player) {
                $answers = json_decode($player['answers'], true);
                if (isset($answers[$category])) {
                    $allAnswers[$category][] = $answers[$category];
                }
            }
        }

        // محاسبه امتیاز برای هر بازیکن
        foreach ($players as $player) {
            $score = 0;
            $playerAnswers = json_decode($player['answers'], true);

            foreach (self::$categories as $category) {
                if (isset($playerAnswers[$category])) {
                    $answer = $playerAnswers[$category];

                    // اعتبارسنجی اولیه: آیا با حرف درست شروع می‌شود؟
                    if (mb_substr($answer, 0, 1) !== $letter) {
                        continue; // امتیاز صفر
                    }

                    // اعتبارسنجی با دیتابیس
                    $isValid = $this->validateWord($category, $answer);
                    if ($isValid) {
                        // بررسی منحصر به فرد بودن
                        $occurrences = array_count_values($allAnswers[$category]);
                        if ($occurrences[$answer] > 1) {
                            $score += 10; // پاسخ تکراری
                        } else {
                            $score += 20; // پاسخ منحصر به فرد
                        }
                    }
                }
            }

            // ثبت امتیاز نهایی (امتیاز پاسخ‌ها + امتیاز اضافی)
            $finalScore = $score + $player['bonus_points'];
            $this->db->update('game_players', ['score' => $finalScore], "id = ?", "i", [$player['id']]);
        }

        // تعیین برنده و پایان بازی
        $this->finalizeGame($gameId);
    }

    private function finalizeGame($gameId) {
        $winner = $this->db->selectOne("SELECT user_id FROM game_players WHERE game_id = ? ORDER BY score DESC, bonus_points DESC LIMIT 1", "s", [$gameId]);
        $this->db->update(
            'games',
            ['status' => 'finished', 'winner_id' => $winner['user_id'], 'finished_at' => date('Y-m-d H:i:s')],
            "game_id = ?", "s", [$gameId]
        );

        // آپدیت آمار کلی بازیکنان
        $players = $this->getGamePlayers($gameId);
        foreach($players as $player){
            (new UserManager($this->db))->updateGameStats($player['user_id'], $player['user_id'] == $winner['user_id']);
        }
    }

    private function parseAnswers($text) {
        $parsed = [];
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($category, $answer) = explode(':', $line, 2);
                $category = trim($category);
                if (in_array($category, self::$categories)) {
                    $parsed[$category] = trim($answer);
                }
            }
        }
        return $parsed;
    }

    private function validateWord($category, $word) {
        // اعتبارسنجی با دیتابیس
        $exists = $this->db->selectOne("SELECT id FROM words WHERE category = ? AND word = ?", "ss", [$category, $word]);
        return $exists !== null;
    }

    public function getGame($gameId) {
        return $this->db->selectOne("SELECT * FROM games WHERE game_id = ?", "s", [$gameId]);
    }

    public function getGamePlayers($gameId) {
        return $this->db->select("SELECT * FROM game_players WHERE game_id = ?", "s", [$gameId]);
    }

    /**
     * بررسی اینکه آیا کاربر در یک بازی فعال حضور دارد یا خیر
     */
    public function getActiveGameForUser($userId) {
        return $this->db->selectOne(
            "SELECT g.* FROM games g
             JOIN game_players gp ON g.game_id = gp.game_id
             WHERE gp.user_id = ? AND g.status IN ('active', 'scoring')",
            "i",
            [$userId]
        );
    }
}
