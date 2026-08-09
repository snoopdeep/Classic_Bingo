-- Table 1: users (Identity & Profile)
-- Stores the persistent state of each player's identity.
CREATE TABLE users (
    user_id VARCHAR(36) PRIMARY KEY,
    user_name VARCHAR(16) NOT NULL UNIQUE, -- keep it power of 2.
    avatar_id ENUM('avatar_01', 'avatar_02', 'avatar_03', 'avatar_04', 'avatar_05') NOT NULL DEFAULT 'avatar_01',
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user', 
    refresh_token VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);
