-- ============================================
-- پایگاه داده ربات اسم و فامیل (نسخه بازسازی شده)
-- ============================================

-- جدول کاربران
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(255),
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255),
    premium_coins INT DEFAULT 0,
    free_coins INT DEFAULT 0,
    total_xp INT DEFAULT 0,
    rank_id INT DEFAULT 1,
    games_played INT DEFAULT 0,
    games_won INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول کلمات (برای اعتبارسنجی)
CREATE TABLE words (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category VARCHAR(100) NOT NULL,
    word VARCHAR(255) NOT NULL,
    is_active TINYINT DEFAULT 1,
    UNIQUE KEY unique_word (category, word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول بازی‌ها
CREATE TABLE games (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id VARCHAR(255) UNIQUE NOT NULL,
    group_id BIGINT, -- برای بازی‌های گروهی
    status ENUM('waiting', 'active', 'scoring', 'finished') DEFAULT 'waiting',
    letter CHAR(2) CHARACTER SET utf8mb4, -- حرف انتخاب شده برای دور بازی
    creator_id BIGINT NOT NULL,
    winner_id BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول بازیکنان در هر بازی
CREATE TABLE game_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id VARCHAR(255) NOT NULL,
    user_id BIGINT NOT NULL,
    answers JSON, -- پاسخ‌های بازیکن به صورت JSON
    score INT DEFAULT 0, -- امتیاز این دور
    bonus_points INT DEFAULT 0, -- امتیاز اضافی برای پایان زدن زودتر
    is_winner TINYINT DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول پرداخت‌ها
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    order_id VARCHAR(255) UNIQUE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول آیتم‌های فروشگاه
CREATE TABLE shop_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    price INT NOT NULL,
    is_active TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول خریدهای کاربران
CREATE TABLE purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    item_id INT NOT NULL,
    total_cost INT NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول دستاوردها
CREATE TABLE achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    type ENUM('games_played', 'games_won', 'total_xp') NOT NULL,
    requirement INT NOT NULL,
    reward_points INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول دستاوردهای کسب شده توسط کاربران
CREATE TABLE user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    achievement_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- افزودن داده‌های نمونه برای کلمات
INSERT INTO words (category, word) VALUES
('اسم', 'علی'), ('اسم', 'سارا'), ('اسم', 'رضا'),
('شهر', 'تهران'), ('شهر', 'اصفهان'), ('شهر', 'شیراز'),
('کشور', 'ایران'), ('کشور', 'عراق'), ('کشور', 'ترکیه');
