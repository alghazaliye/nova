<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$admin = requireAdminLogin();
requirePermission($admin, 'admins.manage');

$pageTitle = 'المشرفون والصلاحيات';
$pdo = getAdminDB();
$message = '';
$error = '';

// معالجة الإجراءات (إضافة، تعديل، حذف)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $action = $_POST['action'] ?? '';
    
    // إضافة مشرف جديد
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = (int)($_POST['role_id'] ?? 0);
        
        if (!$name || !$email || !$password || !$role_id) {
            $error = 'جميع الحقول مطلوبة';
        } elseif (strlen($password) < 8) {
            $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
        } else {
            try {
                // التحقق من عدم وجود بريد مكرر
                $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'البريد الإلكتروني موجود بالفعل';
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare(
                        'INSERT INTO admins (name, email, password_hash, role_id, is_active, created_at)
                         VALUES (?, ?, ?, ?, 1, datetime("now"))'
                    );
                    $stmt->execute([$name, $email, $password_hash, $role_id]);
                    $message = 'تم إضافة المشرف بنجاح';
                    logAudit($admin, 'CREATE', 'admin', (int)$pdo->lastInsertId(), "إضافة مشرف: $email");
                }
            } catch (Exception $e) {
                $error = 'خطأ في إضافة المشرف: ' . $e->getMessage();
            }
        }
    }
    
    // تعديل مشرف
    elseif ($action === 'edit') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0);
        
        if (!$admin_id || !$name || !$email || !$role_id) {
            $error = 'جميع الحقول مطلوبة';
        } else {
            try {
                // التحقق من عدم وجود بريد مكرر (ما عدا الحالي)
                $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1');
                $stmt->execute([$email, $admin_id]);
                if ($stmt->fetch()) {
                    $error = 'البريد الإلكتروني موجود بالفعل';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE admins SET name = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?'
                    );
                    $stmt->execute([$name, $email, $role_id, $is_active, $admin_id]);
                    $message = 'تم تحديث المشرف بنجاح';
                    logAudit($admin, 'UPDATE', 'admin', $admin_id, "تحديث مشرف: $email");
                }
            } catch (Exception $e) {
                $error = 'خطأ في تحديث المشرف: ' . $e->getMessage();
            }
        }
    }
    
    // حذف مشرف
    elseif ($action === 'delete') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        
        if ($admin_id === $admin['id']) {
            $error = 'لا يمكنك حذف حسابك الخاص';
        } elseif ($admin_id > 0) {
            try {
                $stmt = $pdo->prepare('SELECT email FROM admins WHERE id = ?');
                $stmt->execute([$admin_id]);
                $deleted_admin = $stmt->fetch();
                
                $stmt = $pdo->prepare('DELETE FROM admins WHERE id = ?');
                $stmt->execute([$admin_id]);
                $message = 'تم حذف المشرف بنجاح';
                logAudit($admin, 'DELETE', 'admin', $admin_id, "حذف مشرف: " . ($deleted_admin['email'] ?? 'unknown'));
            } catch (Exception $e) {
                $error = 'خطأ في حذف المشرف: ' . $e->getMessage();
            }
        }
    }
}

// جلب البيانات
    // تحقق من وجود عمود username قبل الاستعلام
    $checkCols = $pdo->query("PRAGMA table_info(admins)")->fetchAll();
    $hasUsername = false;
    foreach ($checkCols as $col) {
        if ($col['name'] === 'username') {
            $hasUsername = true;
            break;
        }
    }
    
    $usernameField = $hasUsername ? "a.username" : "'' as username";
    
    $admins = $pdo->query(
        "SELECT a.id, a.name, $usernameField, a.email, a.is_active, a.last_login_at, a.created_at, r.name role_name 
         FROM admins a JOIN roles r ON r.id = a.role_id ORDER BY a.created_at DESC"
    )->fetchAll();

$roles = $pdo->query(
    'SELECT r.id, r.name, r.description, COUNT(rp.permission_id) permission_count 
     FROM roles r LEFT JOIN role_permissions rp ON rp.role_id = r.id 
     GROUP BY r.id ORDER BY r.id'
)->fetchAll();

// حالة النظام
$system_status = [];
$system_status['database'] = 'متصل ✓';
$system_status['admins_count'] = count($admins);
$system_status['roles_count'] = count($roles);

// إحصائيات قاعدة البيانات
try {
    $dbStats = $pdo->query(
        'SELECT 
            (SELECT COUNT(*) FROM users) as users_count,
            (SELECT COUNT(*) FROM conversations) as conversations_count,
            (SELECT COUNT(*) FROM messages) as messages_count,
            (SELECT COUNT(*) FROM stories) as stories_count'
    )->fetch();
    
    $system_status['users_count'] = $dbStats['users_count'] ?? 0;
    $system_status['conversations_count'] = $dbStats['conversations_count'] ?? 0;
    $system_status['messages_count'] = $dbStats['messages_count'] ?? 0;
    $system_status['stories_count'] = $dbStats['stories_count'] ?? 0;
} catch (Exception $e) {
    error_log('Database stats error: ' . $e->getMessage());
}

// سجل الأخطاء الأخيرة
$error_logs = $pdo->query(
    'SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 20'
)->fetchAll() ?: [];

// سجل النشاط الحديث
$recent_activity = $pdo->query(
    'SELECT a.id, a.admin_id, a.action, a.entity_type, a.description, a.created_at, ad.name as admin_name
	     FROM audit_logs a
	     LEFT JOIN admins ad ON ad.id = a.admin_id
	     ORDER BY a.created_at DESC LIMIT 15'
	)->fetchAll() ?: [];
	
	$actionMap = [
	    'CREATE' => 'إضافة',
	    'UPDATE' => 'تعديل',
	    'DELETE' => 'حذف',
	    'LOGIN' => 'دخول',
	    'LOGOUT' => 'خروج',
	    'REPORT_RESOLVED' => 'حل بلاغ',
	    'REPORT_REJECTED' => 'رفض بلاغ',
	    'REPORT_BAN_USER' => 'حظر مستخدم',
	    'REPORT_SUSPEND_USER' => 'تعليق مستخدم',
	];
	$entityMap = [
	    'admin' => 'مشرف',
	    'user' => 'مستخدم',
	    'report' => 'بلاغ',
	    'message' => 'رسالة',
	    'plan' => 'باقة',
	    'subscription' => 'اشتراك',
	];
	?>

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="pagehead">
    <div>
        <h2>المشرفون والصلاحيات</h2>
        <p>إدارة حسابات المشرفين والأدوار والصلاحيات</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success" style="margin: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 4px; border-left: 4px solid #28a745;">
        ✓ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger" style="margin: 20px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px; border-left: 4px solid #dc3545;">
        ✗ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- حالة النظام والمراقبة الحية -->
<div class="grid4" style="margin: 20px;">
    <div class="card" style="padding: 15px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h4 style="margin-bottom: 10px;">المستخدمون</h4>
        <p style="font-size: 28px; font-weight: bold;"><?= $system_status['users_count'] ?></p>
        <small>إجمالي المستخدمين</small>
    </div>
    <div class="card" style="padding: 15px; text-align: center; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h4 style="margin-bottom: 10px;">المحادثات</h4>
        <p style="font-size: 28px; font-weight: bold;"><?= $system_status['conversations_count'] ?></p>
        <small>إجمالي المحادثات</small>
    </div>
    <div class="card" style="padding: 15px; text-align: center; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h4 style="margin-bottom: 10px;">الرسائل</h4>
        <p style="font-size: 28px; font-weight: bold;"><?= $system_status['messages_count'] ?></p>
        <small>إجمالي الرسائل</small>
    </div>
    <div class="card" style="padding: 15px; text-align: center; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h4 style="margin-bottom: 10px;">الحالات</h4>
        <p style="font-size: 28px; font-weight: bold;"><?= $system_status['stories_count'] ?></p>
        <small>إجمالي الحالات</small>
    </div>
</div>

<!-- نموذج إضافة مشرف جديد -->
<div class="card panel" style="margin: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 20px; color: #333;">➕ إضافة مشرف جديد</h3>
    <form method="POST" style="display: grid; gap: 15px;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label for="name" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">الاسم الكامل:</label>
                <input type="text" id="name" name="name" required placeholder="أدخل الاسم الكامل" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            <div>
                <label for="email" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">البريد الإلكتروني:</label>
                <input type="email" id="email" name="email" required placeholder="example@domain.com" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            <div>
                <label for="password" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">كلمة المرور:</label>
                <input type="password" id="password" name="password" required placeholder="كلمة مرور قوية (8+ أحرف)" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            <div>
                <label for="role_id" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">الدور:</label>
                <select id="role_id" name="role_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value="">اختر دور</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" style="padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">✓ إضافة مشرف</button>
    </form>
</div>

<!-- جدول المشرفين -->
<div class="card panel tablewrap" style="margin: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 20px; color: #333;">👥 حسابات المشرفين</h3>
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
	                <th style="padding: 12px; text-align: right; font-weight: bold;">الاسم</th>
	                <th style="padding: 12px; text-align: right; font-weight: bold;">اسم المستخدم</th>
	                <th style="padding: 12px; text-align: right; font-weight: bold;">البريد</th>
	                <th style="padding: 12px; text-align: right; font-weight: bold;">الدور</th>
	                <th style="padding: 12px; text-align: right; font-weight: bold;">الحالة</th>
	                <th style="padding: 12px; text-align: right; font-weight: bold;">آخر دخول</th>
	                <th style="padding: 12px; text-align: right; font-weight: bold;">الإجراءات</th>
            </tr>
        </thead>
        <tbody>
	            <?php foreach ($admins as $a): ?>
	                <tr style="border-bottom: 1px solid #ddd; hover: background: #f9f9f9;">
	                    <td style="padding: 12px;"><?= htmlspecialchars($a['name']) ?></td>
	                    <td style="padding: 12px;"><?= htmlspecialchars($a['username'] ?? '-') ?></td>
	                    <td style="padding: 12px;"><?= htmlspecialchars($a['email']) ?></td>
		                    <td style="padding: 12px;">
		                      <?php 
		                      $roleMap = [
		                          'Super Admin' => 'مدير خارق',
		                          'Admin' => 'مشرف',
		                          'Moderator' => 'مراقب',
		                          'Support' => 'دعم فني',
		                          'Analyst' => 'محلل بيانات'
		                      ]; 
		                      ?>
		                      <span style="background: #e7f3ff; color: #0066cc; padding: 4px 8px; border-radius: 3px;"><?= $roleMap[$a['role_name']] ?? htmlspecialchars($a['role_name']) ?></span>
		                    </td>
	                    <td style="padding: 12px;">
                        <span style="padding: 5px 10px; border-radius: 4px; background: <?= $a['is_active'] ? '#d4edda' : '#f8d7da' ?>; color: <?= $a['is_active'] ? '#155724' : '#721c24' ?>; font-weight: bold;">
                            <?= $a['is_active'] ? '🟢 نشط' : '🔴 معطل' ?>
                        </span>
                    </td>
                    <td style="padding: 12px;">
                        <small><?= $a['last_login_at'] ? date('d/m/Y H:i', strtotime($a['last_login_at'])) : '⏳ لم يدخل بعد' ?></small>
                    </td>
                    <td style="padding: 12px;">
                        <button onclick="editAdmin(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>', '<?= htmlspecialchars($a['email']) ?>', <?= $a['is_active'] ?>)" style="padding: 6px 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px; font-size: 12px;">✏️ تعديل</button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                            <button type="submit" onclick="return confirm('هل أنت متأكد من حذف هذا المشرف؟')" style="padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">🗑️ حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- سجل النشاط الحديث (المراقبة الحية) -->
<div class="card panel" style="margin: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 20px; color: #333;">📊 سجل النشاط الحديث (مراقبة حية)</h3>
    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead style="position: sticky; top: 0; background: #f8f9fa; border-bottom: 2px solid #ddd;">
                <tr>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">الوقت</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">المسؤول</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">الإجراء</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">النوع</th>
                    <th style="padding: 12px; text-align: right; font-weight: bold;">الوصف</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_activity as $activity): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><small><?= date('H:i:s', strtotime($activity['created_at'])) ?></small></td>
                        <td style="padding: 10px;"><small><?= htmlspecialchars($activity['admin_name'] ?? 'نظام') ?></small></td>
                        <td style="padding: 10px;">
                            <span style="background: <?php 
                                $action = $activity['action'];
                                echo ($action === 'CREATE') ? '#d4edda' : (($action === 'UPDATE') ? '#cfe2ff' : '#f8d7da');
                            ?>; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
	                                <?= $actionMap[$activity['action']] ?? htmlspecialchars($activity['action']) ?>
	                            </span>
	                        </td>
	                        <td style="padding: 10px;"><small><?= $entityMap[$activity['entity_type']] ?? htmlspecialchars($activity['entity_type'] ?? '-') ?></small></td>
                        <td style="padding: 10px;"><small><?= htmlspecialchars($activity['description'] ?? '-') ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="margin-top: 10px; color: #666; font-size: 12px;">🔄 يتم تحديث هذا السجل تلقائياً عند كل إجراء في النظام</p>
</div>

<!-- جدول الأدوار -->
<div class="card panel tablewrap" style="margin: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <h3 style="margin-bottom: 20px; color: #333;">🔐 الأدوار والصلاحيات</h3>
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                <th style="padding: 12px; text-align: right; font-weight: bold;">الدور</th>
                <th style="padding: 12px; text-align: right; font-weight: bold;">الوصف</th>
                <th style="padding: 12px; text-align: right; font-weight: bold;">عدد الصلاحيات</th>
            </tr>
        </thead>
        <tbody>
	            <?php foreach ($roles as $r): ?>
	                <tr style="border-bottom: 1px solid #ddd;">
		                    <td style="padding: 12px;">
		                      <?php 
		                      $roleMap = [
		                          'Super Admin' => 'مدير خارق',
		                          'Admin' => 'مشرف',
		                          'Moderator' => 'مراقب',
		                          'Support' => 'دعم فني',
		                          'Analyst' => 'محلل بيانات'
		                      ]; 
		                      ?>
		                      <strong><?= $roleMap[$r['name']] ?? htmlspecialchars($r['name']) ?></strong>
	                    </td>
                    <td style="padding: 12px;"><?= htmlspecialchars($r['description'] ?? '') ?></td>
                    <td style="padding: 12px;"><span style="background: #e7f3ff; color: #0066cc; padding: 4px 8px; border-radius: 3px; font-weight: bold;"><?= (int)$r['permission_count'] ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- نموذج التعديل المخفي -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <h3 style="margin-bottom: 20px; color: #333;">✏️ تعديل المشرف</h3>
        <form method="POST" style="display: grid; gap: 15px;">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="editAdminId" name="admin_id" value="">
            
            <div>
                <label for="editName" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">الاسم:</label>
                <input type="text" id="editName" name="name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="editEmail" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">البريد الإلكتروني:</label>
                <input type="email" id="editEmail" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="editIsActive" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">الحالة:</label>
                <select id="editIsActive" name="is_active" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="1">🟢 نشط</option>
                    <option value="0">🔴 معطل</option>
                </select>
            </div>
            <div>
                <label for="editRole" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">الدور:</label>
                <select id="editRole" name="role_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">✓ حفظ</button>
                <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">✕ إلغاء</button>
            </div>
        </form>
    </div>
</div>

<!-- تحديث تلقائي للمراقبة الحية -->
<script>
function editAdmin(id, name, email, isActive) {
    document.getElementById('editAdminId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editEmail').value = email;
    document.getElementById('editIsActive').value = isActive ? '1' : '0';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// تحديث تلقائي للمراقبة الحية كل 5 ثوان
setInterval(function() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            const newActivityTable = newDoc.querySelector('table');
            const currentActivityTable = document.querySelector('table');
            
            if (newActivityTable && currentActivityTable) {
                const newRows = newActivityTable.querySelectorAll('tbody tr');
                const currentRows = currentActivityTable.querySelectorAll('tbody tr');
                
                if (newRows.length > currentRows.length) {
                    location.reload();
                }
            }
        })
        .catch(err => console.log('Live update check: ' + err));
}, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
