-- =====================================================
-- NOVA Messenger - Database Schema
-- Version: 1.0.0
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =====================================================

CREATE DATABASE IF NOT EXISTS `nova`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `nova`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =====================================================
-- SECTION 1: USERS & AUTH
-- =====================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`          CHAR(36)        NOT NULL,
  `phone`         VARCHAR(30)     NOT NULL,
  `email`         VARCHAR(190)    NULL DEFAULT NULL,
  `password_hash` VARCHAR(255)    NULL DEFAULT NULL,
  `name`          VARCHAR(150)    NOT NULL,
  `username`      VARCHAR(100)    NULL DEFAULT NULL,
  `bio`           TEXT            NULL DEFAULT NULL,
  `avatar`        VARCHAR(500)    NULL DEFAULT NULL,
  `status_text`   VARCHAR(255)    NULL DEFAULT NULL,
  `is_online`     TINYINT(1)      NOT NULL DEFAULT 0,
  `last_seen`     DATETIME        NULL DEFAULT NULL,
  `is_verified`   TINYINT(1)      NOT NULL DEFAULT 0,
  `is_blocked`    TINYINT(1)      NOT NULL DEFAULT 0,
  `blocked_at`    DATETIME        NULL DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_uuid`     (`uuid`),
  UNIQUE KEY `uq_users_phone`    (`phone`),
  UNIQUE KEY `uq_users_username` (`username`),
  INDEX `idx_users_is_online`    (`is_online`),
  INDEX `idx_users_is_blocked`   (`is_blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `user_devices` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `device_uuid`    VARCHAR(100)    NOT NULL,
  `device_name`    VARCHAR(200)    NULL DEFAULT NULL,
  `platform`       VARCHAR(30)     NOT NULL DEFAULT 'android',
  `app_version`    VARCHAR(30)     NULL DEFAULT NULL,
  `fcm_token`      TEXT            NULL DEFAULT NULL,
  `last_active_at` DATETIME        NULL DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_device_user_uuid` (`user_id`, `device_uuid`),
  INDEX `idx_devices_user_id` (`user_id`),
  CONSTRAINT `fk_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `sessions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `token_hash`  VARCHAR(255)    NOT NULL,
  `device_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `ip_address`  VARCHAR(45)     NULL DEFAULT NULL,
  `user_agent`  TEXT            NULL DEFAULT NULL,
  `expires_at`  DATETIME        NOT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at`  DATETIME        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessions_token_hash` (`token_hash`),
  INDEX `idx_sessions_user_id` (`user_id`),
  CONSTRAINT `fk_sessions_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`        (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sessions_device` FOREIGN KEY (`device_id`) REFERENCES `user_devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `contacts` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `contact_user_id` BIGINT UNSIGNED NOT NULL,
  `nickname`        VARCHAR(150)    NULL DEFAULT NULL,
  `is_blocked`      TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contacts_pair` (`user_id`, `contact_user_id`),
  INDEX `idx_contacts_user_id`         (`user_id`),
  INDEX `idx_contacts_contact_user_id` (`contact_user_id`),
  CONSTRAINT `fk_contacts_user`         FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contacts_contact_user` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `blocks` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `blocked_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocks_pair` (`user_id`, `blocked_user_id`),
  INDEX `idx_blocks_user_id`         (`user_id`),
  INDEX `idx_blocks_blocked_user_id` (`blocked_user_id`),
  CONSTRAINT `fk_blocks_user`         FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blocks_blocked_user` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 2: CONVERSATIONS & MESSAGES
-- =====================================================

CREATE TABLE IF NOT EXISTS `conversations` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`            CHAR(36)        NOT NULL,
  `type`            ENUM('private','group') NOT NULL DEFAULT 'private',
  `title`           VARCHAR(200)    NULL DEFAULT NULL,
  `avatar`          VARCHAR(500)    NULL DEFAULT NULL,
  `created_by`      BIGINT UNSIGNED NOT NULL,
  `last_message_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conversations_uuid` (`uuid`),
  INDEX `idx_conversations_type`       (`type`),
  INDEX `idx_conversations_created_by` (`created_by`),
  CONSTRAINT `fk_conversations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `conversation_members` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id`     BIGINT UNSIGNED NOT NULL,
  `user_id`             BIGINT UNSIGNED NOT NULL,
  `role`                ENUM('member','admin','owner') NOT NULL DEFAULT 'member',
  `joined_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at`             DATETIME        NULL DEFAULT NULL,
  `is_muted`            TINYINT(1)      NOT NULL DEFAULT 0,
  `is_pinned`           TINYINT(1)      NOT NULL DEFAULT 0,
  `last_read_message_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conv_member_pair` (`conversation_id`, `user_id`),
  INDEX `idx_conv_members_conversation_id` (`conversation_id`),
  INDEX `idx_conv_members_user_id`         (`user_id`),
  CONSTRAINT `fk_conv_members_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conv_members_user`         FOREIGN KEY (`user_id`)         REFERENCES `users`         (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `attachments` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`           CHAR(36)        NOT NULL,
  `uploader_id`    BIGINT UNSIGNED NOT NULL,
  `type`           VARCHAR(30)     NOT NULL,
  `original_name`  VARCHAR(500)    NULL DEFAULT NULL,
  `file_name`      VARCHAR(500)    NOT NULL,
  `mime_type`      VARCHAR(100)    NOT NULL,
  `file_size`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `storage_path`   VARCHAR(1000)   NOT NULL,
  `thumbnail_path` VARCHAR(1000)   NULL DEFAULT NULL,
  `width`          INT UNSIGNED    NULL DEFAULT NULL,
  `height`         INT UNSIGNED    NULL DEFAULT NULL,
  `duration`       INT UNSIGNED    NULL DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attachments_uuid` (`uuid`),
  INDEX `idx_attachments_uploader_id` (`uploader_id`),
  CONSTRAINT `fk_attachments_uploader` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `messages` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`                CHAR(36)        NOT NULL,
  `conversation_id`     BIGINT UNSIGNED NOT NULL,
  `sender_id`           BIGINT UNSIGNED NOT NULL,
  `reply_to_message_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `type`                ENUM('text','emoji','image','video','audio','voice','file','location','contact','poll','system') NOT NULL DEFAULT 'text',
  `body`                TEXT            NULL DEFAULT NULL,
  `file_id`             BIGINT UNSIGNED NULL DEFAULT NULL,
  `client_message_id`   VARCHAR(100)    NOT NULL,
  `status`              ENUM('sending','sent','delivered','read','failed','deleted') NOT NULL DEFAULT 'sending',
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          DATETIME        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_messages_uuid`              (`uuid`),
  UNIQUE KEY `uq_messages_client_message_id` (`conversation_id`, `client_message_id`),
  INDEX `idx_messages_conversation_id` (`conversation_id`),
  INDEX `idx_messages_sender_id`       (`sender_id`),
  INDEX `idx_messages_created_at`      (`created_at`),
  INDEX `idx_messages_status`          (`status`),
  CONSTRAINT `fk_messages_conversation`  FOREIGN KEY (`conversation_id`)     REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_sender`        FOREIGN KEY (`sender_id`)           REFERENCES `users`         (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_messages_reply`         FOREIGN KEY (`reply_to_message_id`) REFERENCES `messages`      (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_attachment`    FOREIGN KEY (`file_id`)             REFERENCES `attachments`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK for last_message_id after messages table is created
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conversations_last_message` FOREIGN KEY (`last_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL;

-- =====================================================

CREATE TABLE IF NOT EXISTS `message_reads` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `read_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_message_reads_pair` (`message_id`, `user_id`),
  INDEX `idx_message_reads_message_id` (`message_id`),
  INDEX `idx_message_reads_user_id`    (`user_id`),
  CONSTRAINT `fk_message_reads_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_reads_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `message_reactions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `reaction`   VARCHAR(20)     NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_message_reactions_pair` (`message_id`, `user_id`),
  INDEX `idx_message_reactions_message_id` (`message_id`),
  CONSTRAINT `fk_message_reactions_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_reactions_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 3: GROUPS
-- =====================================================

CREATE TABLE IF NOT EXISTS `groups` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `name`            VARCHAR(200)    NOT NULL,
  `description`     TEXT            NULL DEFAULT NULL,
  `avatar`          VARCHAR(500)    NULL DEFAULT NULL,
  `created_by`      BIGINT UNSIGNED NOT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_groups_conversation_id` (`conversation_id`),
  INDEX `idx_groups_created_by` (`created_by`),
  CONSTRAINT `fk_groups_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_groups_creator`       FOREIGN KEY (`created_by`)      REFERENCES `users`         (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `group_settings` (
  `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id`               BIGINT UNSIGNED NOT NULL,
  `only_admins_can_message` TINYINT(1)     NOT NULL DEFAULT 0,
  `only_admins_can_edit`    TINYINT(1)     NOT NULL DEFAULT 0,
  `approval_required`       TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_settings_group_id` (`group_id`),
  CONSTRAINT `fk_group_settings_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 4: CALLS
-- =====================================================

CREATE TABLE IF NOT EXISTS `calls` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`       CHAR(36)        NOT NULL,
  `caller_id`  BIGINT UNSIGNED NOT NULL,
  `call_type`  ENUM('voice','video') NOT NULL DEFAULT 'voice',
  `status`     ENUM('calling','ringing','answered','missed','rejected','ended','failed') NOT NULL DEFAULT 'calling',
  `started_at` DATETIME        NULL DEFAULT NULL,
  `ended_at`   DATETIME        NULL DEFAULT NULL,
  `duration`   INT UNSIGNED    NULL DEFAULT NULL COMMENT 'Duration in seconds',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calls_uuid` (`uuid`),
  INDEX `idx_calls_caller_id`  (`caller_id`),
  INDEX `idx_calls_created_at` (`created_at`),
  CONSTRAINT `fk_calls_caller` FOREIGN KEY (`caller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `call_participants` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`   BIGINT UNSIGNED NOT NULL,
  `joined_at` DATETIME        NULL DEFAULT NULL,
  `left_at`   DATETIME        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_call_participants_pair` (`call_id`, `user_id`),
  INDEX `idx_call_participants_call_id` (`call_id`),
  INDEX `idx_call_participants_user_id` (`user_id`),
  CONSTRAINT `fk_call_participants_call` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_call_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 5: STORIES
-- =====================================================

CREATE TABLE IF NOT EXISTS `stories` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`       CHAR(36)        NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       ENUM('text','image','video') NOT NULL DEFAULT 'text',
  `text`       TEXT            NULL DEFAULT NULL,
  `file_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
  `privacy`    ENUM('all','contacts','close_friends') NOT NULL DEFAULT 'contacts',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME        NOT NULL,
  `deleted_at` DATETIME        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stories_uuid` (`uuid`),
  INDEX `idx_stories_user_id`    (`user_id`),
  INDEX `idx_stories_expires_at` (`expires_at`),
  CONSTRAINT `fk_stories_user`       FOREIGN KEY (`user_id`) REFERENCES `users`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stories_attachment` FOREIGN KEY (`file_id`) REFERENCES `attachments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `story_views` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `story_id`  BIGINT UNSIGNED NOT NULL,
  `viewer_id` BIGINT UNSIGNED NOT NULL,
  `viewed_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_story_views_pair` (`story_id`, `viewer_id`),
  INDEX `idx_story_views_story_id`  (`story_id`),
  INDEX `idx_story_views_viewer_id` (`viewer_id`),
  CONSTRAINT `fk_story_views_story`  FOREIGN KEY (`story_id`)  REFERENCES `stories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_story_views_viewer` FOREIGN KEY (`viewer_id`) REFERENCES `users`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 6: NOTIFICATIONS & REPORTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `type`        VARCHAR(50)     NOT NULL,
  `title`       VARCHAR(255)    NOT NULL,
  `body`        TEXT            NULL DEFAULT NULL,
  `data_json`   JSON            NULL DEFAULT NULL,
  `is_read`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notifications_user_id` (`user_id`),
  INDEX `idx_notifications_is_read` (`is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `reports` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id`      BIGINT UNSIGNED NOT NULL,
  `reported_user_id` BIGINT UNSIGNED NOT NULL,
  `message_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
  `conversation_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
  `reason`           VARCHAR(100)    NOT NULL,
  `description`      TEXT            NULL DEFAULT NULL,
  `status`           ENUM('pending','reviewing','resolved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`      BIGINT UNSIGNED NULL DEFAULT NULL,
  `reviewed_at`      DATETIME        NULL DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_reports_reporter_id`      (`reporter_id`),
  INDEX `idx_reports_reported_user_id` (`reported_user_id`),
  INDEX `idx_reports_status`           (`status`),
  CONSTRAINT `fk_reports_reporter`      FOREIGN KEY (`reporter_id`)      REFERENCES `users`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_reported_user` FOREIGN KEY (`reported_user_id`) REFERENCES `users`         (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_message`       FOREIGN KEY (`message_id`)       REFERENCES `messages`      (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_conversation`  FOREIGN KEY (`conversation_id`)  REFERENCES `conversations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 7: ADMIN PANEL
-- =====================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `description` TEXT         NULL DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `description` TEXT         NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_role_permissions_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id`       INT UNSIGNED NOT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at` DATETIME     NULL DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`),
  INDEX `idx_admins_role_id` (`role_id`),
  CONSTRAINT `fk_admins_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`    INT UNSIGNED    NOT NULL,
  `action`      VARCHAR(100)    NOT NULL,
  `entity_type` VARCHAR(50)     NULL DEFAULT NULL,
  `entity_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `description` TEXT            NULL DEFAULT NULL,
  `ip_address`  VARCHAR(45)     NULL DEFAULT NULL,
  `user_agent`  TEXT            NULL DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_logs_admin_id`    (`admin_id`),
  INDEX `idx_audit_logs_action`      (`action`),
  INDEX `idx_audit_logs_entity_type` (`entity_type`),
  INDEX `idx_audit_logs_created_at`  (`created_at`),
  CONSTRAINT `fk_audit_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================

CREATE TABLE IF NOT EXISTS `app_settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         NULL DEFAULT NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
