-- =====================================================
-- NOVA Messenger - Production seed (SQLite-safe)
-- Idempotent: INSERT OR IGNORE makes it safe to run
-- on every container startup.
-- =====================================================

-- Roles
INSERT OR IGNORE INTO roles (id, name, description) VALUES
  (1, 'super_admin', 'مدير النظام الكامل'),
  (2, 'moderator',   'مشرف المحتوى'),
  (3, 'support',     'فريق الدعم الفني');

-- Permissions
INSERT OR IGNORE INTO permissions (id, name, description) VALUES
  (1,  'users.view',      'عرض المستخدمين'),
  (2,  'users.create',    'إنشاء مستخدم'),
  (3,  'users.edit',      'تعديل بيانات المستخدم'),
  (4,  'users.block',     'حظر المستخدم'),
  (5,  'users.delete',    'حذف المستخدم'),
  (6,  'messages.view',   'عرض الرسائل'),
  (7,  'messages.delete', 'حذف الرسائل'),
  (8,  'groups.view',     'عرض المجموعات'),
  (9,  'groups.manage',   'إدارة المجموعات'),
  (10, 'reports.view',    'عرض البلاغات'),
  (11, 'reports.resolve', 'معالجة البلاغات'),
  (12, 'admins.manage',   'إدارة المشرفين'),
  (13, 'settings.manage', 'إدارة الإعدادات'),
  (14, 'audit.view',      'عرض سجل العمليات'),
  (15, 'subscriptions.manage', 'إدارة الاشتراكات المميزة'),
  (16, 'users.manage',    'إدارة المستخدمين والصلاحيات');

-- Role permissions (super_admin gets all; moderator/support limited)
INSERT OR IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;
INSERT OR IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name IN (
  'users.view','users.block','messages.view','messages.delete',
  'groups.view','groups.manage','reports.view','reports.resolve'
);
INSERT OR IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE name IN ('users.view','reports.view','reports.resolve');

-- =====================================================
-- Default Admin Account
-- Email:    admin@nova-messenger.com
-- Username: alghazaliye
-- Password: 738155861 (bcrypt hash below)
-- =====================================================
UPDATE admins SET name='alghazaliye', password_hash='$2y$10$oY4R5sTAv/N.WOkiQYj1wOukrM7bWP9.SBcjH8Pqmf5asKMV12ZwO', updated_at=CURRENT_TIMESTAMP WHERE id=1;
INSERT OR IGNORE INTO admins (id, name, email, password_hash, role_id, is_active) VALUES
  (1, 'alghazaliye', 'admin@nova-messenger.com',
   '$2y$10$oY4R5sTAv/N.WOkiQYj1wOukrM7bWP9.SBcjH8Pqmf5asKMV12ZwO', 1, 1);

-- =====================================================
-- Default App Settings (idempotent via ON CONFLICT)
-- =====================================================
INSERT INTO app_settings (setting_key, setting_value) VALUES
  ('app_name',              'NOVA Messenger'),
  ('app_version',           '5.4.2'),
  ('maintenance_mode',      '0'),
  ('allow_registration',    '1'),
  ('allow_calls',           '1'),
  ('allow_groups',          '1'),
  ('allow_stories',         '1'),
  ('max_file_size_mb',      '50'),
  ('max_image_size_mb',     '10'),
  ('max_video_size_mb',     '100'),
  ('story_duration_hrs',    '24'),
  ('otp_expiry_minutes',    '5'),
  ('session_duration_days', '30'),
  ('default_language',      'ar'),
  ('default_timezone',      'Asia/Riyadh'),
  ('fcm_enabled',           '0'),
  ('realtime_provider',     'polling'),
  ('auth_email_login',      '1'),
  ('auth_phone_login',      '1'),
  ('auth_username_login',   '1'),
  ('auth_email_registration',  '1'),
  ('auth_phone_registration',  '1'),
  ('otp_email_enabled',        '1')
ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = datetime('now','localtime');

-- =====================================================
-- OTP delivery pipeline
-- A disabled sms row satisfies the fail-closed check so
-- the API starts; real delivery uses the SMTP pipeline
-- (email_providers, configured via Render ENV vars).
-- =====================================================
-- OTP providers + encrypted Gmail SMTP row are inserted by
-- database/seed_providers.php (reads ENV vars at startup).
