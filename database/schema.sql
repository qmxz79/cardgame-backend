-- Card Game Database Schema
-- Created: 2026-03-09

CREATE DATABASE IF NOT EXISTS cardgame DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cardgame;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status TINYINT DEFAULT 1 COMMENT '1=active, 0=inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 卡牌表
CREATE TABLE IF NOT EXISTS cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    card_type ENUM('creature', 'spell', 'artifact', 'land') NOT NULL,
    mana_cost VARCHAR(20),
    power INT DEFAULT NULL,
    toughness INT DEFAULT NULL,
    rarity ENUM('common', 'uncommon', 'rare', 'mythic') DEFAULT 'common',
    set_code VARCHAR(10),
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_card_type (card_type),
    INDEX idx_rarity (rarity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 卡组表
CREATE TABLE IF NOT EXISTS decks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    format VARCHAR(50) DEFAULT 'standard',
    is_public TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 卡组卡牌关联表
CREATE TABLE IF NOT EXISTS deck_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deck_id INT NOT NULL,
    card_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_deck_card (deck_id, card_id),
    FOREIGN KEY (deck_id) REFERENCES decks(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    INDEX idx_deck_id (deck_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 游戏对局表
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_uuid VARCHAR(36) UNIQUE NOT NULL,
    player1_id INT,
    player2_id INT,
    winner_id INT,
    status ENUM('waiting', 'playing', 'finished') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 游戏动作历史记录
CREATE TABLE IF NOT EXISTS game_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    player_id INT,
    action_type VARCHAR(50) NOT NULL,
    action_data JSON,
    turn_number INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_game_id (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入示例数据
INSERT INTO cards (name, description, card_type, mana_cost, power, toughness, rarity) VALUES
('火焰精灵', '具有冲锋能力的火元素生物', 'creature', '2R', 3, 2, 'common'),
('冰霜巨人', '强大的冰元素，具有高防御', 'creature', '4UU', 6, 7, 'rare'),
('治愈之光', '恢复 3 点生命值', 'spell', '1W', NULL, NULL, 'common'),
('黑暗契约', '牺牲一个生物抽取 2 张牌', 'spell', '2B', NULL, NULL, 'uncommon'),
('古老图书馆', '神器：每回合抽牌阶段额外抽 1 张牌', 'artifact', '3', NULL, NULL, 'rare'),
('森林', '基本地：添加 G', 'land', NULL, NULL, NULL, 'common'),
('山脉', '基本地：添加 R', 'land', NULL, NULL, NULL, 'common'),
('岛屿', '基本地：添加 U', 'land', NULL, NULL, NULL, 'common');
