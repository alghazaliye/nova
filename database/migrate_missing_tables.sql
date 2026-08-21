-- Migration: add missing message_edits / message_deletions tables (SQLite-safe)
CREATE TABLE IF NOT EXISTS `message_edits` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `message_id` INTEGER NOT NULL,
    `conversation_id` INTEGER NOT NULL,
    `user_id` INTEGER NOT NULL,
    `old_body` TEXT,
    `new_body` TEXT,
    `edited_at` DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `message_deletions` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `message_id` INTEGER NOT NULL,
    `conversation_id` INTEGER NOT NULL,
    `deleted_by` INTEGER NOT NULL,
    `original_body` TEXT,
    `original_type` VARCHAR(50) DEFAULT 'text',
    `scope_type` VARCHAR(20) DEFAULT 'me',
    `deleted_at` DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
);
