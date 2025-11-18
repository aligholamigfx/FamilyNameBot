-- ============================================
-- پایگاه داده ربات تلگرام
-- ============================================

-- جدول کاربران
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(255) UNIQUE,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255),
    balance DECIMAL(10, 2) DEFAULT 0,
    premium_coins INT DEFAULT 0,
    free_coins INT DEFAULT 0,
    total_xp INT DEFAULT 0,
    rank_id INT DEFAULT 1,
    level INT DEFAULT 1,
    games_played INT DEFAULT 0,
    games_won INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_rank_id (rank_id),
    INDEX idx_total_xp (total_xp DESC),
    INDEX idx_games_won (games_won DESC),
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول کلمات بازی
CREATE TABLE words (
    id INT PRIMARY KEY AUTO_INCREMENT,
    word VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(100),
    difficulty ENUM('آسان', 'متوسط', 'سخت') DEFAULT 'متوسط',
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_difficulty (difficulty),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول بازی‌ها
CREATE TABLE games (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id VARCHAR(255) UNIQUE NOT NULL,
    type ENUM('single', 'multi', 'group') DEFAULT 'single',
    creator_id BIGINT NOT NULL,
    group_id BIGINT,
    winner_id BIGINT,
    status ENUM('waiting', 'active', 'finished') DEFAULT 'active',
    words JSON,
    total_prize INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    INDEX idx_game_id (game_id),
    INDEX idx_creator (creator_id),
    INDEX idx_status (status),
    INDEX idx_type (type),
    FOREIGN KEY (creator_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول بازیکنان بازی
CREATE TABLE game_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id VARCHAR(255) NOT NULL,
    user_id BIGINT NOT NULL,
    score INT DEFAULT 0,
    is_winner TINYINT DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    INDEX idx_game_id (game_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول اقلام فروشگاه
CREATE TABLE shop_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    price INT NOT NULL,
    category VARCHAR(100),
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول خریدها
CREATE TABLE purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    total_cost INT NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول پرداخت‌ها
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    order_id VARCHAR(255) UNIQUE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USDT',
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_id VARCHAR(255),
    transaction_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_order_id (order_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول خریدهای سکه
CREATE TABLE coin_purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    amount INT NOT NULL,
    order_id VARCHAR(255) UNIQUE,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول دستیابی‌ها
CREATE TABLE achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    requirement INT,
    reward_points INT,
    type ENUM('games_played', 'games_won', 'points', 'purchases', 'rank_reached', 'coins_spent', 'total_coins', 'win_rate', 'consecutive_days') DEFAULT 'games_played',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول دستیابی‌های کاربر
CREATE TABLE user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    achievement_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول لاگ سیستم
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(255),
    user_id BIGINT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action (action),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تنظیمات
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(255) UNIQUE NOT NULL,
    value LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ایندکس‌های اضافی
CREATE INDEX idx_users_first_name ON users(first_name);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_payments_completed_at ON payments(completed_at);
CREATE INDEX idx_games_status_type ON games(status, type);
CREATE INDEX idx_players_score ON game_players(user_id, score DESC);
CREATE INDEX idx_payments_user_status ON payments(user_id, status);
CREATE INDEX idx_achievements_type ON achievements(type, requirement);

-- داده‌های نمونه برای کلمات
INSERT INTO words (word, category, difficulty) VALUES
('برنامه‌ریزی', 'تکنولوژی', 'متوسط'),
('کامپیوتر', 'تکنولوژی', 'آسان'),
('اینترنت', 'تکنولوژی', 'آسان'),
('وبسایت', 'تکنولوژی', 'متوسط'),
('داده‌بیس', 'تکنولوژی', 'سخت'),
('فوتبال', 'ورزش', 'آسان'),
('تنیس', 'ورزش', 'آسان'),
('شنا', 'ورزش', 'آسان'),
('تهران', 'شهر', 'آسان'),
('اصفهان', 'شهر', 'آسان'),
('شیر', 'حیوان', 'آسان'),
('پلنگ', 'حیوان', 'متوسط'),
('دلفین', 'حیوان', 'متوسط');

-- داده‌های نمونه برای اقلام فروشگاه
INSERT INTO shop_items (name, description, icon, price, category) VALUES
('کیف طلایی', 'کیف مخصوص برای جمع‌آوری سکه', '💼', 50, 'equipment'),
('شمشیر جادویی', 'افزایش قدرت 2 برابری', '⚔️', 100, 'weapon'),
('شیلد الماسی', 'محافظت کامل 100%', '🛡️', 75, 'shield'),
('جام نوشیدنی', 'بازیابی تمام انرژی', '🍷', 30, 'potion'),
('نقاب مخفی', 'پنهان شدن از حریفان', '🎭', 60, 'accessory');

-- داده‌های نمونه برای دستیابی‌ها
INSERT INTO achievements (name, description, icon, requirement, reward_points, type) VALUES
('شروع‌کننده', 'اولین بازی‌ات را شروع کن', '🎮', 1, 10, 'games_played'),
('شیطان‌برتر', '10 بازی برتری داشته باش', '🔥', 10, 50, 'games_won'),
('امپراطور', '50 بازی برتری داشته باش', '👑', 50, 200, 'games_won'),
('دونده‌ی چاپ‌تخت', '100 بازی انجام داده باش', '🏃', 100, 100, 'games_played'),
('سیکل‌زن', '500 بازی انجام داده باش', '🚴', 500, 300, 'games_played'),
('کسب‌و‌کار خوب', '1000 سکه خرج کن', '💰', 1000, 150, 'coins_spent'),
('ستاره‌ی درخشان', 'به رتبه ستاره‌ی درخشان برسی', '⭐', 7, 500, 'rank_reached'),
('جمع‌کننده', '5000 سکه جمع کن', '🪙', 5000, 200, 'total_coins');