-- NOVA Messenger — OTP System Migration (v5.1.0)
-- Multi-provider OTP (Twilio / Vonage / HTTP REST / Manual) with Fallback
-- Safe to run: uses IF NOT EXISTS; never drops existing tables

CREATE TABLE IF NOT EXISTS otp_providers (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL COMMENT 'اسم المزود الظاهر',
  type        ENUM('twilio','vonage','http_rest','test','manual') NOT NULL,
  status      ENUM('enabled','disabled') NOT NULL DEFAULT 'disabled',
  priority    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = higher priority (fallback order)',
  is_default  TINYINT(1) NOT NULL DEFAULT 0,
  is_fallback TINYINT(1) NOT NULL DEFAULT 0,
  api_base_url  VARCHAR(500) NULL COMMENT 'رابط قاعدة API لمزود HTTP REST',
  api_key     VARCHAR(500) NULL COMMENT 'مشفّرة: key1::aes-256-cbc(iv)::cipher',
  api_secret  VARCHAR(500) NULL COMMENT 'مشفّرة: key1::aes-256-cbc(iv)::cipher',
  account_sid VARCHAR(300) NULL COMMENT 'لمزود Twilio',
  message_template TEXT NULL COMMENT 'نص الرسالة: {OTP} {PHONE} {MINUTES} {APP_NAME}',
  sender_id   VARCHAR(100) NULL COMMENT 'المُرسل (From / sender_id)',
  extra_config JSON NULL COMMENT 'إعدادات إضافية حرة',
  success_count  INT UNSIGNED NOT NULL DEFAULT 0,
  failure_count  INT UNSIGNED NOT NULL DEFAULT 0,
  last_used_at   DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS otp_verifications (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone_number    VARCHAR(30) NOT NULL,
  name            VARCHAR(150) NULL COMMENT 'الاسم عند التسجيل',
  otp_hash        VARCHAR(255) NOT NULL COMMENT 'bcrypt hash للرمز',
  status          ENUM('pending','sent','delivery_failed','manual','verified','expired','blocked','cancelled') NOT NULL DEFAULT 'pending',
  attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 5,
  resends         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  delivery_mode   ENUM('auto','manual','auto_fallback') NOT NULL DEFAULT 'auto',
  delivery_status ENUM('pending','sent','failed','manual','expired') NULL,
  provider_id     INT UNSIGNED NULL,
  expires_at      DATETIME NULL,
  verified_at     DATETIME NULL,
  ip_address      VARCHAR(45) NULL,
  user_agent      TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_otp_phone (phone_number, status),
  INDEX idx_otp_provider (provider_id),
  INDEX idx_otp_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS otp_delivery_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  otp_id         BIGINT UNSIGNED NOT NULL,
  provider_id    INT UNSIGNED NOT NULL,
  provider_type  VARCHAR(20) NOT NULL,
  phone_number   VARCHAR(30) NOT NULL,
  status         ENUM('attempt','success','failed','timeout','manual') NOT NULL DEFAULT 'attempt',
  http_code      SMALLINT UNSIGNED NULL,
  error_message  TEXT NULL,
  response_summary VARCHAR(500) NULL COMMENT 'خلاصة الاستجابة بدون أسرار',
  response_time_ms INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dl_otp (otp_id),
  INDEX idx_dl_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS otp_rate_limits (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bucket_key    VARCHAR(120) NOT NULL COMMENT 'phone:/ip:/device:identifier',
  bucket_type   ENUM('phone','ip','device','endpoint') NOT NULL,
  hits          INT UNSIGNED NOT NULL DEFAULT 1,
  window_start  DATETIME NOT NULL,
  INDEX idx_rl_bucket (bucket_key, bucket_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- مزود اختبار افتراضي مفعّل (يُستخدم في بيئات التطوير والإنتاج التجريبية)
INSERT INTO otp_providers (name, type, status, priority, is_default, is_fallback, created_at, updated_at)
SELECT 'مزود الاختبار', 'test', 'enabled', 1, 1, 0, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM otp_providers WHERE type = 'test' AND status = 'enabled' LIMIT 1);

-- صلاحيات RBAC الجديدة
INSERT INTO permissions (name, description) VALUES
 ('otp.providers.view',    'عرض مزودي OTP'),
 ('otp.providers.create',  'إنشاء مزود OTP'),
 ('otp.providers.update',  'تعديل مزود OTP'),
 ('otp.providers.delete',  'حذف مزود OTP'),
 ('otp.providers.test',    'اختبار مزود OTP'),
 ('otp.providers.enable',  'تفعيل/تعطيل مزود OTP'),
 ('registration.view',     'عرض طلبات التسجيل'),
 ('registration.view_otp', 'عرض رمز OTP للمستخدم'),
 ('registration.regenerate_otp', 'إعادة إنشاء رمز OTP'),
 ('registration.verify',   'تأكيد طلب تسجيل يدويًا'),
 ('registration.cancel',   'إلغاء طلب تسجيل'),
 ('otp.stats',             'عرض إحصائيات OTP'),
 ('otp.settings',          'إدارة إعدادات OTP')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- منح جميع صلاحيات OTP لمدير النظام super_admin تلقائيًا
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, p.id FROM permissions p
WHERE p.name LIKE 'otp.%' OR p.name LIKE 'registration.%';

-- إعدادات افتراضية
INSERT INTO app_settings (setting_key, setting_value) VALUES
 ('otp_length', '6'),
 ('otp_expiry_minutes', '5'),
 ('otp_max_attempts', '5'),
 ('otp_resend_cooldown_seconds', '60'),
 ('otp_max_resends', '5'),
 ('otp_delivery_mode', 'auto'),
 ('otp_default_provider_id', '0'),
 ('otp_enable_fallback', '1'),
 ('otp_enable_manual_fallback', '1'),
 ('otp_message_template', 'رمز التحقق الخاص بك هو: {OTP}. صالح لمدة {MINUTES} دقيقة. لا تشاركه مع أي شخص. — {APP_NAME}'),
 ('otp_rate_limit_per_phone_per_hour', '10'),
 ('otp_rate_limit_per_ip_per_hour', '30'),
 ('otp_encryption_key', ''),
 ('otp_stats_refresh', '0')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
