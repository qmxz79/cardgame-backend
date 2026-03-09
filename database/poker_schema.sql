-- 扑克游戏数据库 Schema
-- 4 人升级/拖拉机游戏

USE cardgame;

-- 游戏房间表
CREATE TABLE IF NOT EXISTS poker_games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_uuid VARCHAR(36) UNIQUE NOT NULL,
    status ENUM('waiting', 'playing', 'finished') DEFAULT 'waiting',
    current_level VARCHAR(5) DEFAULT '2',
    landlord_seat INT DEFAULT 0,
    current_player_seat INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 游戏玩家表
CREATE TABLE IF NOT EXISTS poker_game_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    user_id INT NOT NULL,
    seat INT NOT NULL,
    team INT NOT NULL,
    level INT DEFAULT 2,
    score INT DEFAULT 0,
    is_landlord TINYINT DEFAULT 0,
    FOREIGN KEY (game_id) REFERENCES poker_games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_game_seat (game_id, seat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 出牌记录表
CREATE TABLE IF NOT EXISTS poker_plays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    player_seat INT NOT NULL,
    cards JSON NOT NULL,
    round_number INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES poker_games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 游戏结果表
CREATE TABLE IF NOT EXISTS poker_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    winning_team INT NOT NULL,
    player_levels JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES poker_games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
