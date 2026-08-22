<?php
/**
 * NOVA Messenger - Privacy & Security QA Test Script
 * This script simulates User A and User B interactions to verify privacy enforcement.
 */

declare(strict_types=1);
require_once __DIR__ . '/helpers/Database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/helpers/UuidHelper.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/AuthController.php';

// Mock AuthMiddleware to simulate different users
class MockAuth {
    public static $userId = 0;
    public static function set(int $id) { self::$userId = $id; }
}

// Override AuthMiddleware::authenticate for testing
// Note: In a real test we'd use reflection or a test-friendly architecture, 
// but here we will just call the controller methods directly with manual ID passing where possible
// or use the database to verify state.

$pdo = Database::getInstance();

function logTest($name, $result, $message = '') {
    $status = $result ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "{$status} {$name}: {$message}\n";
}

echo "--- بدء اختبارات الخصوصية والأمان (QA) ---\n";

// 1. إنشاء مستخدمين تجريبيين
$pdo->exec("DELETE FROM users WHERE phone IN ('+966000000001', '+966000000002') OR uuid IN ('uuid-a', 'uuid-b')");
$pdo->exec("INSERT INTO users (uuid, name, phone, created_at) VALUES ('uuid-a', 'User A', '+966000000001', datetime('now'))");
$userAId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO users (uuid, name, phone, created_at) VALUES ('uuid-b', 'User B', '+966000000002', datetime('now'))");
$userBId = (int)$pdo->lastInsertId();

$userCtrl = new UserController();

// 2. اختبار إخفاء رقم الهاتف (Privacy: Nobody)
$pdo->prepare("INSERT OR REPLACE INTO privacy_settings (user_id, show_phone) VALUES (?, 0)")->execute([$userAId]);
$profileA = $pdo->query("SELECT * FROM users WHERE id = $userAId")->fetch(PDO::FETCH_ASSOC);
$filteredForB = $userCtrl->filterProfile($profileA, $userBId, $userAId);
logTest("إخفاء الهاتف (Nobody)", $filteredForB['phone'] === null, "رقم هاتف A يجب أن يكون مخفياً عن B");

// 3. اختبار إظهار الهاتف لجهات الاتصال فقط
$pdo->prepare("UPDATE privacy_settings SET show_phone = 1 WHERE user_id = ?")->execute([$userAId]);
$filteredForB = $userCtrl->filterProfile($profileA, $userBId, $userAId);
logTest("إخفاء الهاتف (Contacts Only - Not Contact)", $filteredForB['phone'] === null, "رقم هاتف A يجب أن يكون مخفياً عن B لأنه ليس جهة اتصال");

$pdo->prepare("INSERT INTO contacts (user_id, contact_user_id) VALUES (?, ?)")->execute([$userAId, $userBId]);
$pdo->prepare("INSERT INTO contacts (user_id, contact_user_id) VALUES (?, ?)")->execute([$userBId, $userAId]);
$filteredForB = $userCtrl->filterProfile($profileA, $userBId, $userAId);
logTest("إظهار الهاتف (Contacts Only - Is Contact)", $filteredForB['phone'] !== null, "رقم هاتف A يجب أن يظهر لـ B لأنه جهة اتصال");

// 4. اختبار الحظر (Blocking)
$pdo->prepare("INSERT INTO blocks (user_id, blocked_user_id, created_at) VALUES (?, ?, datetime('now'))")->execute([$userAId, $userBId]);
$filteredForB = $userCtrl->filterProfile($profileA, $userBId, $userAId);
logTest("خصوصية الحظر (Blocked User)", $filteredForB['phone'] === null && $filteredForB['avatar'] === null, "B المحظور يجب ألا يرى أي بيانات حساسة لـ A");
logTest("خصوصية الحظر (Blocked User Name)", $filteredForB['display_name'] === 'User', "B المحظور يجب أن يرى فقط الاسم الأول لـ A (User)");

// 5. اختبار آخر ظهور (Last Seen)
$pdo->prepare("DELETE FROM blocks WHERE user_id = ? AND blocked_user_id = ?")->execute([$userAId, $userBId]);
$pdo->prepare("UPDATE privacy_settings SET show_last_seen = 0 WHERE user_id = ?")->execute([$userAId]);
$profileA['last_seen'] = date('Y-m-d H:i:s');
$profileA['is_online'] = 1;
$presenceFiltered = $userCtrl->applyPresencePrivacy($profileA, $userBId);
logTest("إخفاء آخر ظهور (Nobody)", $presenceFiltered['last_seen'] === null, "آخر ظهور لـ A يجب أن يكون مخفياً عن B");
logTest("إخفاء الحالة المتصلة (Nobody)", $presenceFiltered['is_online'] == false, "الحالة المتصلة لـ A يجب أن تكون مخفية عن B عند اختيار 'لا أحد' لآخر الظهور");

// 6. اختبار الوصول غير المصرح به للمحادثات (IDOR)
// سنحاكي وظيفة requireMember
function testConversationAccess($convId, $userId) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1');
    $stmt->execute([$convId, $userId]);
    return (bool)$stmt->fetch();
}

$pdo->exec("INSERT INTO conversations (uuid, type, created_by, created_at) VALUES ('test-conv', 'private', $userAId, datetime('now'))");
$convId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, 'owner')")->execute([$convId, $userAId]);

logTest("أمن المحادثات (IDOR)", testConversationAccess($convId, $userBId) === false, "B يجب ألا يملك وصولاً لمحادثة A");

// تنظيف
$pdo->exec("DELETE FROM users WHERE id IN ($userAId, $userBId)");
$pdo->exec("DELETE FROM contacts WHERE user_id IN ($userAId, $userBId) OR contact_user_id IN ($userAId, $userBId)");
$pdo->exec("DELETE FROM blocks WHERE user_id IN ($userAId, $userBId) OR blocked_user_id IN ($userAId, $userBId)");
$pdo->exec("DELETE FROM conversations WHERE id = $convId");
$pdo->exec("DELETE FROM conversation_members WHERE conversation_id = $convId");

echo "--- انتهت الاختبارات ---\n";
