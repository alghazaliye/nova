CREATE TABLE `admins` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(150) NOT NULL
,  `email` varchar(190) NOT NULL
,  `password_hash` varchar(255) NOT NULL
,  `role_id` integer  NOT NULL
,  `is_active` integer NOT NULL DEFAULT 1
,  `last_login_at` datetime DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`email`)
,  CONSTRAINT `fk_admins_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
);
CREATE TABLE `app_settings` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `setting_key` varchar(100) NOT NULL
,  `setting_value` text DEFAULT NULL
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`setting_key`)
);
CREATE TABLE `attachments` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `uuid` char(36) NOT NULL
,  `uploader_id` integer  NOT NULL
,  `type` varchar(30) NOT NULL
,  `original_name` varchar(500) DEFAULT NULL
,  `file_name` varchar(500) NOT NULL
,  `mime_type` varchar(100) NOT NULL
,  `file_size` integer  NOT NULL DEFAULT 0
,  `storage_path` varchar(1000) NOT NULL
,  `thumbnail_path` varchar(1000) DEFAULT NULL
,  `width` integer  DEFAULT NULL
,  `height` integer  DEFAULT NULL
,  `duration` integer  DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  UNIQUE (`uuid`)
,  CONSTRAINT `fk_attachments_uploader` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`)
);
CREATE TABLE `audit_logs` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `admin_id` integer  NOT NULL
,  `action` varchar(100) NOT NULL
,  `entity_type` varchar(50) DEFAULT NULL
,  `entity_id` integer  DEFAULT NULL
,  `description` text DEFAULT NULL
,  `ip_address` varchar(45) DEFAULT NULL
,  `user_agent` text DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  CONSTRAINT `fk_audit_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
);
CREATE TABLE `blocks` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `blocked_user_id` integer  NOT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  UNIQUE (`user_id`,`blocked_user_id`)
,  CONSTRAINT `fk_blocks_blocked_user` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_blocks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `call_participants` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `call_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  `joined_at` datetime DEFAULT NULL
,  `left_at` datetime DEFAULT NULL
,  UNIQUE (`call_id`,`user_id`)
,  CONSTRAINT `fk_call_participants_call` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_call_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `calls` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `uuid` char(36) NOT NULL
,  `caller_id` integer  NOT NULL
,  `call_type` text  NOT NULL DEFAULT 'voice'
,  `status` text  NOT NULL DEFAULT 'calling'
,  `started_at` datetime DEFAULT NULL
,  `ended_at` datetime DEFAULT NULL
,  `duration` integer  DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  UNIQUE (`uuid`)
,  CONSTRAINT `fk_calls_caller` FOREIGN KEY (`caller_id`) REFERENCES `users` (`id`)
);
CREATE TABLE `contacts` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `contact_user_id` integer  NOT NULL
,  `nickname` varchar(150) DEFAULT NULL
,  `is_blocked` integer NOT NULL DEFAULT 0
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`user_id`,`contact_user_id`)
,  CONSTRAINT `fk_contacts_contact_user` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `conversation_members` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `conversation_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  `role` text  NOT NULL DEFAULT 'member'
,  `joined_at` datetime NOT NULL DEFAULT current_timestamp
,  `left_at` datetime DEFAULT NULL
,  `is_muted` integer NOT NULL DEFAULT 0
,  `is_pinned` integer NOT NULL DEFAULT 0
,  `last_read_message_id` integer  DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`conversation_id`,`user_id`)
,  CONSTRAINT `fk_conv_members_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_conv_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `conversations` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `uuid` char(36) NOT NULL
,  `type` text  NOT NULL DEFAULT 'private'
,  `title` varchar(200) DEFAULT NULL
,  `avatar` varchar(500) DEFAULT NULL
,  `created_by` integer  NOT NULL
,  `last_message_id` integer  DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`uuid`)
,  CONSTRAINT `fk_conversations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
,  CONSTRAINT `fk_conversations_last_message` FOREIGN KEY (`last_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
);
CREATE TABLE `device_registrations` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `device_uuid` varchar(190) NOT NULL
,  `device_name` varchar(200) DEFAULT NULL
,  `device_model` varchar(150) DEFAULT NULL
,  `platform` varchar(50) DEFAULT NULL
,  `os` varchar(80) DEFAULT NULL
,  `os_version` varchar(80) DEFAULT NULL
,  `app_version` varchar(40) DEFAULT NULL
,  `device_fingerprint` varchar(200) DEFAULT NULL
,  `fcm_token` varchar(500) DEFAULT NULL
,  `is_active` integer NOT NULL DEFAULT 1
,  `last_seen` datetime DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`user_id`,`device_uuid`)
);
CREATE TABLE `email_delivery_logs` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `email_type` text  NOT NULL DEFAULT 'registration'
,  `to_email` varchar(190) NOT NULL
,  `provider_id` integer  DEFAULT NULL
,  `subject` varchar(255) DEFAULT NULL
,  `status` text  NOT NULL DEFAULT 'pending'
,  `http_code` integer DEFAULT NULL
,  `response_time_ms` integer DEFAULT NULL
,  `response_summary` text DEFAULT NULL
,  `error_message` text DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
);
CREATE TABLE `email_providers` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(150) NOT NULL
,  `type` text  NOT NULL DEFAULT 'smtp'
,  `status` text  NOT NULL DEFAULT 'disabled'
,  `priority` integer NOT NULL DEFAULT 0
,  `is_default` integer NOT NULL DEFAULT 0
,  `is_fallback` integer NOT NULL DEFAULT 0
,  `host` varchar(200) DEFAULT NULL
,  `port` integer DEFAULT NULL
,  `encryption` text  NOT NULL DEFAULT 'tls'
,  `username` varchar(200) DEFAULT NULL
,  `password` text DEFAULT NULL
,  `from_email` varchar(200) DEFAULT NULL
,  `from_name` varchar(150) DEFAULT NULL
,  `api_base_url` varchar(300) DEFAULT NULL
,  `api_key` text DEFAULT NULL
,  `extra_config` text DEFAULT NULL
,  `success_count` integer  NOT NULL DEFAULT 0
,  `failure_count` integer  NOT NULL DEFAULT 0
,  `last_used_at` datetime DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
);
CREATE TABLE `email_verification_codes` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `email` varchar(190) NOT NULL
,  `name` varchar(150) DEFAULT NULL
,  `code_hash` varchar(255) NOT NULL
,  `manual_code_hash` varchar(255) DEFAULT NULL
,  `purpose` text  NOT NULL DEFAULT 'registration'
,  `status` text  NOT NULL DEFAULT 'pending'
,  `attempts` integer NOT NULL DEFAULT 0
,  `max_attempts` integer NOT NULL DEFAULT 5
,  `resends` integer NOT NULL DEFAULT 0
,  `delivery_mode` text  NOT NULL DEFAULT 'auto'
,  `expires_at` datetime DEFAULT NULL
,  `ip_address` varchar(45) DEFAULT NULL
,  `user_agent` text DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
);
CREATE TABLE `group_settings` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `group_id` integer  NOT NULL
,  `only_admins_can_message` integer NOT NULL DEFAULT 0
,  `only_admins_can_edit` integer NOT NULL DEFAULT 0
,  `approval_required` integer NOT NULL DEFAULT 0
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`group_id`)
,  CONSTRAINT `fk_group_settings_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
);
CREATE TABLE `groups` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `conversation_id` integer  NOT NULL
,  `name` varchar(200) NOT NULL
,  `description` text DEFAULT NULL
,  `avatar` varchar(500) DEFAULT NULL
,  `created_by` integer  NOT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`conversation_id`)
,  CONSTRAINT `fk_groups_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_groups_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
);
CREATE TABLE `message_reactions` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `message_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  `reaction` varchar(20) NOT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  UNIQUE (`message_id`,`user_id`)
,  CONSTRAINT `fk_message_reactions_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_message_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `message_reads` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `message_id` integer  NOT NULL
,  `user_id` integer  NOT NULL
,  `read_at` datetime NOT NULL DEFAULT current_timestamp
,  UNIQUE (`message_id`,`user_id`)
,  CONSTRAINT `fk_message_reads_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_message_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `messages` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `uuid` char(36) NOT NULL
,  `conversation_id` integer  NOT NULL
,  `sender_id` integer  NOT NULL
,  `reply_to_message_id` integer  DEFAULT NULL
,  `type` text  NOT NULL DEFAULT 'text'
,  `body` text DEFAULT NULL
,  `file_id` integer  DEFAULT NULL
,  `client_message_id` varchar(100) NOT NULL
,  `status` text  NOT NULL DEFAULT 'sending'
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  `deleted_at` datetime DEFAULT NULL
,  UNIQUE (`uuid`)
,  UNIQUE (`conversation_id`,`client_message_id`)
,  CONSTRAINT `fk_messages_attachment` FOREIGN KEY (`file_id`) REFERENCES `attachments` (`id`) ON DELETE SET NULL
,  CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_messages_reply` FOREIGN KEY (`reply_to_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
,  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
);
CREATE TABLE `notifications` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `type` varchar(50) NOT NULL
,  `title` varchar(255) NOT NULL
,  `body` text DEFAULT NULL
,  `data_json` longtext DEFAULT NULL CHECK (json_valid(`data_json`))
,  `is_read` integer NOT NULL DEFAULT 0
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `otp_delivery_logs` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `otp_id` integer  NOT NULL
,  `provider_id` integer  NOT NULL
,  `provider_type` varchar(20) NOT NULL
,  `phone_number` varchar(30) NOT NULL
,  `status` text  NOT NULL DEFAULT 'attempt'
,  `http_code` integer  DEFAULT NULL
,  `error_message` text DEFAULT NULL
,  `response_summary` varchar(500) DEFAULT NULL
,  `response_time_ms` integer  DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
);
CREATE TABLE `otp_providers` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(120) NOT NULL
,  `type` text  NOT NULL
,  `status` text  NOT NULL DEFAULT 'disabled'
,  `priority` integer  NOT NULL DEFAULT 0
,  `is_default` integer NOT NULL DEFAULT 0
,  `is_fallback` integer NOT NULL DEFAULT 0
,  `api_base_url` varchar(500) DEFAULT NULL
,  `api_key` varchar DEFAULT NULL
,  `api_secret` varchar DEFAULT NULL
,  `account_sid` varchar(300) DEFAULT NULL
,  `message_template` text DEFAULT NULL
,  `sender_id` varchar(100) DEFAULT NULL
,  `extra_config` longtext DEFAULT NULL
,  `success_count` integer  NOT NULL DEFAULT 0
,  `failure_count` integer  NOT NULL DEFAULT 0
,  `last_used_at` datetime DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
);
CREATE TABLE `otp_rate_limits` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `bucket_key` varchar(120) NOT NULL
,  `bucket_type` text  NOT NULL
,  `hits` integer  NOT NULL DEFAULT 1
,  `window_start` datetime NOT NULL
);
CREATE TABLE `otp_verifications` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `phone_number` varchar(30) NOT NULL
,  `name` varchar(150) DEFAULT NULL
,  `otp_hash` varchar(255) NOT NULL
,  `manual_code_hash` varchar(255) DEFAULT NULL
,  `status` text  NOT NULL DEFAULT 'pending'
,  `attempts` integer  NOT NULL DEFAULT 0
,  `max_attempts` integer  NOT NULL DEFAULT 5
,  `resends` integer  NOT NULL DEFAULT 0
,  `delivery_mode` text  NOT NULL DEFAULT 'auto'
,  `delivery_status` text  DEFAULT NULL
,  `provider_id` integer  DEFAULT NULL
,  `expires_at` datetime DEFAULT NULL
,  `verified_at` datetime DEFAULT NULL
,  `ip_address` varchar(45) DEFAULT NULL
,  `user_agent` text DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
);
CREATE TABLE `permissions` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(100) NOT NULL
,  `description` text DEFAULT NULL
,  UNIQUE (`name`)
);
CREATE TABLE `plans` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(100) NOT NULL
,  `price` decimal(10,2) NOT NULL DEFAULT 0.00
,  `currency` varchar(3) NOT NULL DEFAULT 'SAR'
,  `period` varchar(20) NOT NULL DEFAULT 'monthly'
,  `max_devices` integer NOT NULL DEFAULT 1
,  `badge_color` varchar(20) DEFAULT NULL
,  `created_at` timestamp NULL DEFAULT current_timestamp
,  `description` varchar(500) DEFAULT NULL
,  `features` text DEFAULT NULL
,  `is_active` integer NOT NULL DEFAULT 1
);
CREATE TABLE `reports` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `reporter_id` integer  NOT NULL
,  `reported_user_id` integer  NOT NULL
,  `message_id` integer  DEFAULT NULL
,  `conversation_id` integer  DEFAULT NULL
,  `reason` varchar(100) NOT NULL
,  `description` text DEFAULT NULL
,  `status` text  NOT NULL DEFAULT 'pending'
,  `reviewed_by` integer  DEFAULT NULL
,  `reviewed_at` datetime DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  CONSTRAINT `fk_reports_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL
,  CONSTRAINT `fk_reports_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
,  CONSTRAINT `fk_reports_reported_user` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `role_permissions` (
  `role_id` integer  NOT NULL
,  `permission_id` integer  NOT NULL
,  PRIMARY KEY (`role_id`,`permission_id`)
,  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
);
CREATE TABLE `roles` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `name` varchar(100) NOT NULL
,  `description` text DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  UNIQUE (`name`)
);
CREATE TABLE `sessions` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `token_hash` varchar(255) NOT NULL
,  `device_id` integer  DEFAULT NULL
,  `ip_address` varchar(45) DEFAULT NULL
,  `user_agent` text DEFAULT NULL
,  `expires_at` datetime NOT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `revoked_at` datetime DEFAULT NULL
,  UNIQUE (`token_hash`)
,  CONSTRAINT `fk_sessions_device` FOREIGN KEY (`device_id`) REFERENCES `user_devices` (`id`) ON DELETE SET NULL
,  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `stories` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `uuid` char(36) NOT NULL
,  `user_id` integer  NOT NULL
,  `type` text  NOT NULL DEFAULT 'text'
,  `text` text DEFAULT NULL
,  `file_id` integer  DEFAULT NULL
,  `privacy` text  NOT NULL DEFAULT 'contacts'
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `expires_at` datetime NOT NULL
,  `deleted_at` datetime DEFAULT NULL
,  UNIQUE (`uuid`)
,  CONSTRAINT `fk_stories_attachment` FOREIGN KEY (`file_id`) REFERENCES `attachments` (`id`) ON DELETE SET NULL
,  CONSTRAINT `fk_stories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `story_views` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `story_id` integer  NOT NULL
,  `viewer_id` integer  NOT NULL
,  `viewed_at` datetime NOT NULL DEFAULT current_timestamp
,  UNIQUE (`story_id`,`viewer_id`)
,  CONSTRAINT `fk_story_views_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE
,  CONSTRAINT `fk_story_views_viewer` FOREIGN KEY (`viewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `user_devices` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `device_uuid` varchar(100) NOT NULL
,  `device_name` varchar(200) DEFAULT NULL
,  `platform` varchar(30) NOT NULL DEFAULT 'android'
,  `app_version` varchar(30) DEFAULT NULL
,  `fcm_token` text DEFAULT NULL
,  `last_active_at` datetime DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`user_id`,`device_uuid`)
,  CONSTRAINT `fk_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
CREATE TABLE `user_subscriptions` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `user_id` integer  NOT NULL
,  `plan_id` integer  NOT NULL
,  `status` varchar(20) NOT NULL DEFAULT 'active'
,  `starts_at` datetime NOT NULL
,  `expires_at` datetime DEFAULT NULL
,  `created_at` timestamp NULL DEFAULT current_timestamp
);
CREATE TABLE `users` (
  `id` integer  NOT NULL PRIMARY KEY AUTOINCREMENT
,  `uuid` char(36) NOT NULL
,  `phone` varchar(30) NOT NULL
,  `email` varchar(190) DEFAULT NULL
,  `password_hash` varchar(255) DEFAULT NULL
,  `email_verified` integer NOT NULL DEFAULT 0
,  `phone_verified` integer NOT NULL DEFAULT 0
,  `name` varchar(150) NOT NULL
,  `username` varchar(100) DEFAULT NULL
,  `bio` text DEFAULT NULL
,  `avatar` varchar(500) DEFAULT NULL
,  `status_text` varchar(255) DEFAULT NULL
,  `is_online` integer NOT NULL DEFAULT 0
,  `last_seen` datetime DEFAULT NULL
,  `is_verified` integer NOT NULL DEFAULT 0
,  `is_blocked` integer NOT NULL DEFAULT 0
,  `blocked_at` datetime DEFAULT NULL
,  `created_at` datetime NOT NULL DEFAULT current_timestamp
,  `updated_at` datetime NOT NULL DEFAULT current_timestamp 
,  UNIQUE (`uuid`)
,  UNIQUE (`phone`)
,  UNIQUE (`username`)
,  UNIQUE (`email`)
);
CREATE INDEX "idx_message_reactions_idx_message_reactions_message_id" ON "message_reactions" (`message_id`);
CREATE INDEX "idx_message_reactions_fk_message_reactions_user" ON "message_reactions" (`user_id`);
CREATE INDEX "idx_email_delivery_logs_idx_email_logs_to" ON "email_delivery_logs" (`to_email`);
CREATE INDEX "idx_email_delivery_logs_idx_email_logs_created" ON "email_delivery_logs" (`created_at`);
CREATE INDEX "idx_conversation_members_idx_conv_members_conversation_id" ON "conversation_members" (`conversation_id`);
CREATE INDEX "idx_conversation_members_idx_conv_members_user_id" ON "conversation_members" (`user_id`);
CREATE INDEX "idx_call_participants_idx_call_participants_call_id" ON "call_participants" (`call_id`);
CREATE INDEX "idx_call_participants_idx_call_participants_user_id" ON "call_participants" (`user_id`);
CREATE INDEX "idx_otp_delivery_logs_idx_dl_otp" ON "otp_delivery_logs" (`otp_id`);
CREATE INDEX "idx_otp_delivery_logs_idx_dl_provider" ON "otp_delivery_logs" (`provider_id`);
CREATE INDEX "idx_device_registrations_idx_dr_user_active" ON "device_registrations" (`user_id`,`is_active`);
CREATE INDEX "idx_device_registrations_idx_dr_last_seen" ON "device_registrations" (`last_seen`);
CREATE INDEX "idx_users_idx_users_is_online" ON "users" (`is_online`);
CREATE INDEX "idx_users_idx_users_is_blocked" ON "users" (`is_blocked`);
CREATE INDEX "idx_stories_idx_stories_user_id" ON "stories" (`user_id`);
CREATE INDEX "idx_stories_idx_stories_expires_at" ON "stories" (`expires_at`);
CREATE INDEX "idx_stories_fk_stories_attachment" ON "stories" (`file_id`);
CREATE INDEX "idx_admins_idx_admins_role_id" ON "admins" (`role_id`);
CREATE INDEX "idx_sessions_idx_sessions_user_id" ON "sessions" (`user_id`);
CREATE INDEX "idx_sessions_fk_sessions_device" ON "sessions" (`device_id`);
CREATE INDEX "idx_otp_rate_limits_idx_rl_bucket" ON "otp_rate_limits" (`bucket_key`,`bucket_type`);
CREATE INDEX "idx_groups_idx_groups_created_by" ON "groups" (`created_by`);
CREATE INDEX "idx_email_verification_codes_idx_evc_email" ON "email_verification_codes" (`email`);
CREATE INDEX "idx_email_verification_codes_idx_evc_status" ON "email_verification_codes" (`status`);
CREATE INDEX "idx_conversations_idx_conversations_type" ON "conversations" (`type`);
CREATE INDEX "idx_conversations_idx_conversations_created_by" ON "conversations" (`created_by`);
CREATE INDEX "idx_conversations_fk_conversations_last_message" ON "conversations" (`last_message_id`);
CREATE INDEX "idx_blocks_idx_blocks_user_id" ON "blocks" (`user_id`);
CREATE INDEX "idx_blocks_idx_blocks_blocked_user_id" ON "blocks" (`blocked_user_id`);
CREATE INDEX "idx_otp_verifications_idx_otp_phone" ON "otp_verifications" (`phone_number`,`status`);
CREATE INDEX "idx_otp_verifications_idx_otp_provider" ON "otp_verifications" (`provider_id`);
CREATE INDEX "idx_otp_verifications_idx_otp_created" ON "otp_verifications" (`created_at`);
CREATE INDEX "idx_notifications_idx_notifications_user_id" ON "notifications" (`user_id`);
CREATE INDEX "idx_notifications_idx_notifications_is_read" ON "notifications" (`is_read`);
CREATE INDEX "idx_message_reads_idx_message_reads_message_id" ON "message_reads" (`message_id`);
CREATE INDEX "idx_message_reads_idx_message_reads_user_id" ON "message_reads" (`user_id`);
CREATE INDEX "idx_reports_idx_reports_reporter_id" ON "reports" (`reporter_id`);
CREATE INDEX "idx_reports_idx_reports_reported_user_id" ON "reports" (`reported_user_id`);
CREATE INDEX "idx_reports_idx_reports_status" ON "reports" (`status`);
CREATE INDEX "idx_reports_fk_reports_message" ON "reports" (`message_id`);
CREATE INDEX "idx_reports_fk_reports_conversation" ON "reports" (`conversation_id`);
CREATE INDEX "idx_messages_idx_messages_conversation_id" ON "messages" (`conversation_id`);
CREATE INDEX "idx_messages_idx_messages_sender_id" ON "messages" (`sender_id`);
CREATE INDEX "idx_messages_idx_messages_created_at" ON "messages" (`created_at`);
CREATE INDEX "idx_messages_idx_messages_status" ON "messages" (`status`);
CREATE INDEX "idx_messages_fk_messages_reply" ON "messages" (`reply_to_message_id`);
CREATE INDEX "idx_messages_fk_messages_attachment" ON "messages" (`file_id`);
CREATE INDEX "idx_contacts_idx_contacts_user_id" ON "contacts" (`user_id`);
CREATE INDEX "idx_contacts_idx_contacts_contact_user_id" ON "contacts" (`contact_user_id`);
CREATE INDEX "idx_audit_logs_idx_audit_logs_admin_id" ON "audit_logs" (`admin_id`);
CREATE INDEX "idx_audit_logs_idx_audit_logs_action" ON "audit_logs" (`action`);
CREATE INDEX "idx_audit_logs_idx_audit_logs_entity_type" ON "audit_logs" (`entity_type`);
CREATE INDEX "idx_audit_logs_idx_audit_logs_created_at" ON "audit_logs" (`created_at`);
CREATE INDEX "idx_story_views_idx_story_views_story_id" ON "story_views" (`story_id`);
CREATE INDEX "idx_story_views_idx_story_views_viewer_id" ON "story_views" (`viewer_id`);
CREATE INDEX "idx_user_subscriptions_user_id" ON "user_subscriptions" (`user_id`);
CREATE INDEX "idx_user_subscriptions_status" ON "user_subscriptions" (`status`);
CREATE INDEX "idx_user_devices_idx_devices_user_id" ON "user_devices" (`user_id`);
CREATE INDEX "idx_role_permissions_fk_role_permissions_permission" ON "role_permissions" (`permission_id`);
CREATE INDEX "idx_email_providers_idx_email_providers_status" ON "email_providers" (`status`);
CREATE INDEX "idx_calls_idx_calls_caller_id" ON "calls" (`caller_id`);
CREATE INDEX "idx_calls_idx_calls_created_at" ON "calls" (`created_at`);
CREATE INDEX "idx_attachments_idx_attachments_uploader_id" ON "attachments" (`uploader_id`);
CREATE UNIQUE INDEX idx_otp_rate_limits_unique ON otp_rate_limits (bucket_key, bucket_type);
CREATE UNIQUE INDEX idx_message_reads_unique ON message_reads (message_id, user_id);
CREATE UNIQUE INDEX idx_conversation_members_unique ON conversation_members (conversation_id, user_id);
CREATE UNIQUE INDEX idx_user_devices_unique ON user_devices (user_id, device_uuid);
CREATE TABLE privacy_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL UNIQUE, show_last_seen INTEGER NOT NULL DEFAULT 1, show_online_status INTEGER NOT NULL DEFAULT 1, show_read_receipts INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT (datetime('now','localtime')), updated_at DATETIME NOT NULL DEFAULT (datetime('now','localtime')));
CREATE TABLE message_edits (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, conversation_id INTEGER NOT NULL, user_id INTEGER NOT NULL, old_body TEXT, new_body TEXT, edited_at DATETIME NOT NULL DEFAULT (datetime('now','localtime')));
CREATE TABLE message_deletions (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, conversation_id INTEGER NOT NULL, deleted_by INTEGER NOT NULL, original_body TEXT, original_type VARCHAR(50), scope_type VARCHAR(20) NOT NULL DEFAULT 'for_me', deleted_at DATETIME NOT NULL DEFAULT (datetime('now','localtime')));
CREATE UNIQUE INDEX idx_message_deletions_unique ON message_deletions (message_id, conversation_id, deleted_by, scope_type);
