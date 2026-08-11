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
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    'INSERT INTO admins (name, email, password_hash, role_id, is_active, created_at)
                     VALUES (?, ?, ?, ?, 1, NOW())'
                );
                $stmt->execute([$name, $email, $password_hash, $role_id]);
                $message = 'تم إضافة المشرف بنجاح';
                logAudit($admin, 'CREATE', 'admin', $pdo->lastInsertId(), "إضافة مشرف: $email");
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
                $stmt = $pdo->prepare(
                    'UPDATE admins SET name = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?'
                );
                $stmt->execute([$name, $email, $role_id, $is_active, $admin_id]);
                $message = 'تم تحديث المشرف بنجاح';
                logAudit($admin, 'UPDATE', 'admin', $admin_id, "تحديث مشرف: $email");
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
$admins = $pdo->query(
    'SELECT a.id, a.name, a.email, a.is_active, a.last_login_at, a.created_at, r.name role_name 
     FROM admins a JOIN roles r ON r.id = a.role_id ORDER BY a.created_at DESC'
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

// سجل الأخطاء الأخيرة
$error_logs = $pdo->query(
    'SELECT * FROM audit_logs WHERE action IN ("ERROR", "FAILED") ORDER BY created_at DESC LIMIT 10'
)->fetchAll() ?: [];

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
    <div class="alert alert-success" style="margin: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 4px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger" style="margin: 20px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- حالة النظام -->
<div class="grid4" style="margin: 20px;">
    <div class="card" style="padding: 15px; text-align: center; background: #f8f9fa;">
        <h4>حالة قاعدة البيانات</h4>
        <p style="font-size: 18px; color: #28a745;"><?= $system_status['database'] ?></p>
    </div>
    <div class="card" style="padding: 15px; text-align: center; background: #f8f9fa;">
        <h4>عدد المشرفين</h4>
        <p style="font-size: 18px; color: #007bff;"><?= $system_status['admins_count'] ?></p>
    </div>
    <div class="card" style="padding: 15px; text-align: center; background: #f8f9fa;">
        <h4>عدد الأدوار</h4>
        <p style="font-size: 18px; color: #17a2b8;"><?= $system_status['roles_count'] ?></p>
    </div>
    <div class="card" style="padding: 15px; text-align: center; background: #f8f9fa;">
        <h4>الخادم</h4>
        <p style="font-size: 18px; color: #6c757d;">متشغل ✓</p>
    </div>
</div>

<!-- نموذج إضافة مشرف جديد -->
<div class="card panel" style="margin: 20px; padding: 20px;">
    <h3>إضافة مشرف جديد</h3>
    <form method="POST" style="display: grid; gap: 15px;">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label for="name">الاسم:</label>
                <input type="text" id="name" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="email">البريد الإلكتروني:</label>
                <input type="email" id="email" name="email" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="password">كلمة المرور:</label>
                <input type="password" id="password" name="password" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="role_id">الدور:</label>
                <select id="role_id" name="role_id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">اختر دور</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">إضافة مشرف</button>
    </form>
</div>

<!-- جدول المشرفين -->
<div class="card panel tablewrap" style="margin: 20px; padding: 20px;">
    <h3>حسابات المشرفين</h3>
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                <th style="padding: 10px; text-align: right;">الاسم</th>
                <th style="padding: 10px; text-align: right;">البريد</th>
                <th style="padding: 10px; text-align: right;">الدور</th>
                <th style="padding: 10px; text-align: right;">الحالة</th>
                <th style="padding: 10px; text-align: right;">آخر دخول</th>
                <th style="padding: 10px; text-align: right;">الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($admins as $a): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px;"><?= htmlspecialchars($a['name']) ?></td>
                    <td style="padding: 10px;"><?= htmlspecialchars($a['email']) ?></td>
                    <td style="padding: 10px;"><?= htmlspecialchars($a['role_name']) ?></td>
                    <td style="padding: 10px;">
                        <span style="padding: 5px 10px; border-radius: 4px; background: <?= $a['is_active'] ? '#d4edda' : '#f8d7da' ?>; color: <?= $a['is_active'] ? '#155724' : '#721c24' ?>;">
                            <?= $a['is_active'] ? 'نشط' : 'معطل' ?>
                        </span>
                    </td>
                    <td style="padding: 10px;">
                        <?= $a['last_login_at'] ? date('d/m/Y H:i', strtotime($a['last_login_at'])) : 'لم يدخل بعد' ?>
                    </td>
                    <td style="padding: 10px;">
                        <button onclick="editAdmin(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>', '<?= htmlspecialchars($a['email']) ?>', <?= $a['is_active'] ?>)" style="padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px;">تعديل</button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                            <button type="submit" onclick="return confirm('هل أنت متأكد من حذف هذا المشرف؟')" style="padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- جدول الأدوار -->
<div class="card panel tablewrap" style="margin: 20px; padding: 20px;">
    <h3>الأدوار والصلاحيات</h3>
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                <th style="padding: 10px; text-align: right;">الدور</th>
                <th style="padding: 10px; text-align: right;">الوصف</th>
                <th style="padding: 10px; text-align: right;">عدد الصلاحيات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $r): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px;"><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                    <td style="padding: 10px;"><?= htmlspecialchars($r['description'] ?? '') ?></td>
                    <td style="padding: 10px;"><?= (int)$r['permission_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- سجل الأخطاء الأخيرة -->
<?php if (!empty($error_logs)): ?>
    <div class="card panel tablewrap" style="margin: 20px; padding: 20px;">
        <h3>سجل الأخطاء الأخيرة</h3>
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px; text-align: right;">الإجراء</th>
                    <th style="padding: 10px; text-align: right;">الوصف</th>
                    <th style="padding: 10px; text-align: right;">الوقت</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($error_logs as $log): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px;"><?= htmlspecialchars($log['action']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($log['description'] ?? '') ?></td>
                        <td style="padding: 10px;"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- نموذج التعديل المخفي -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3>تعديل المشرف</h3>
        <form method="POST" style="display: grid; gap: 15px;">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="editAdminId" name="admin_id" value="">
            
            <div>
                <label for="editName">الاسم:</label>
                <input type="text" id="editName" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="editEmail">البريد الإلكتروني:</label>
                <input type="email" id="editEmail" name="email" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div>
                <label for="editIsActive">الحالة:</label>
                <select id="editIsActive" name="is_active" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="1">نشط</option>
                    <option value="0">معطل</option>
                </select>
            </div>
            <div>
                <label for="editRole">الدور:</label>
                <select id="editRole" name="role_id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">حفظ</button>
                <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function editAdmin(id, name, email, isActive) {
    document.getElementById('editAdminId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editEmail').value = email;
    document.getElementById('editIsActive').value = isActive ? '1' : '0';
    document.getElementById('editModal').style.display = 'block';
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
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
