-- =====================================================
-- NOVA Messenger - Seed Data (Development Only)
-- DO NOT USE IN PRODUCTION
-- =====================================================

USE `nova`;

-- =====================================================
-- Roles & Permissions
-- =====================================================

INSERT INTO `roles` (`name`, `description`) VALUES
  ('super_admin', 'مدير النظام الكامل'),
  ('moderator',   'مشرف المحتوى'),
  ('support',     'فريق الدعم الفني');

INSERT INTO `permissions` (`name`, `description`) VALUES
  ('users.view',      'عرض المستخدمين'),
  ('users.create',    'إنشاء مستخدم'),
  ('users.edit',      'تعديل بيانات المستخدم'),
  ('users.block',     'حظر المستخدم'),
  ('users.delete',    'حذف المستخدم'),
  ('messages.view',   'عرض الرسائل'),
  ('messages.delete', 'حذف الرسائل'),
  ('groups.view',     'عرض المجموعات'),
  ('groups.manage',   'إدارة المجموعات'),
  ('reports.view',    'عرض البلاغات'),
  ('reports.resolve', 'معالجة البلاغات'),
  ('admins.manage',   'إدارة المشرفين'),
  ('settings.manage',   'إدارة الإعدادات'),
  ('audit.view',      'عرض سجل العمليات'),
  ('subscriptions.manage', 'إدارة الاشتراكات المميزة'),
  ('users.manage',    'إدارة المستخدمين والصلاحيات');

-- Grant all permissions to super_admin (role_id = 1)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Grant limited permissions to moderator (role_id = 2)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `name` IN (
  'users.view', 'users.block',
  'messages.view', 'messages.delete',
  'groups.view', 'groups.manage',
  'reports.view', 'reports.resolve'
);

-- Grant support permissions (role_id = 3)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `name` IN (
  'users.view', 'reports.view', 'reports.resolve'
);

-- =====================================================
-- Default Admin Account
-- Password: Admin@1234 (hashed with password_hash())
-- =====================================================

INSERT INTO `admins` (`name`, `email`, `password_hash`, `role_id`) VALUES
  ('مدير النظام', 'admin@nova-messenger.com', '$2y$10$2QBddVSoUNwASnxODhwIr.MB1hBfbXTKLTsGYpogopARARU7Z7kYC', 1);

-- =====================================================
-- Default App Settings
-- =====================================================

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
  ('app_name',           'NOVA Messenger'),
  ('app_version',        '1.0.0'),
  ('maintenance_mode',   '0'),
  ('allow_registration', '1'),
  ('allow_calls',        '1'),
  ('allow_groups',       '1'),
  ('allow_stories',      '1'),
  ('max_file_size_mb',   '50'),
  ('max_image_size_mb',  '10'),
  ('max_video_size_mb',  '100'),
  ('story_duration_hrs', '24'),
  ('otp_expiry_minutes', '5'),
  ('session_duration_days', '30'),
  ('default_language',   'ar'),
  ('default_timezone',   'Asia/Riyadh'),
  ('fcm_enabled',        '1'),
  ('realtime_provider',  'websocket');

-- =====================================================
-- Sample Test Users (Development Only)
-- Password for all: Test@1234
-- =====================================================

INSERT INTO `users` (`uuid`, `phone`, `name`, `username`, `bio`, `is_verified`, `is_online`) VALUES
  (UUID(), '+966501234567', 'أحمد الغزالي',  'ahmed',   'مطور برمجيات', 1, 1),
  (UUID(), '+966502345678', 'سارة العمري',   'sara',    'مصممة UX',     1, 1),
  (UUID(), '+966503456789', 'محمد خالد',     'mohammed', 'مدير مشاريع', 1, 0),
  (UUID(), '+966504567890', 'نور الرشيد',    'nour',    'محللة بيانات', 1, 1),
  (UUID(), '+966505678901', 'خالد الأحمد',   'khaled',  'مهندس شبكات', 1, 0);
