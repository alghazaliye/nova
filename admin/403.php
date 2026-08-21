<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 — غير مصرح — NOVA Messenger Admin</title>
<style>
  :root{
    --bg:#0f1117; --card:#171923; --border:#252836;
    --text:#e8eaf2; --muted:#8b8fa3;
    --danger:#ef4444; --danger-bg:rgba(239,68,68,.08);
    --primary:#6c5ce7;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{
    font-family:'Segoe UI',Tahoma,sans-serif;
    background:linear-gradient(160deg,#0f1117 0%,#151827 60%,#1a1d2e 100%);
    color:var(--text); min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    padding:24px;
  }
  .err-card{
    background:var(--card); border:1px solid var(--border);
    border-radius:20px; padding:44px 36px; max-width:440px; width:100%;
    text-align:center; box-shadow:0 24px 60px rgba(0,0,0,.45);
  }
  .err-badge{
    width:92px;height:92px;border-radius:50%;
    background:var(--danger-bg); border:1px solid rgba(239,68,68,.35);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 22px; font-size:44px;
  }
  h1{font-size:42px;font-weight:800;letter-spacing:2px;color:var(--danger);margin-bottom:6px}
  h2{font-size:19px;font-weight:700;margin-bottom:10px}
  p{color:var(--muted);font-size:14.5px;line-height:1.7;margin-bottom:26px}
  .btn{
    display:inline-block; background:var(--primary); color:#fff;
    text-decoration:none; border:none; border-radius:12px;
    padding:13px 34px; font-size:15px; font-weight:700; cursor:pointer;
    transition:transform .15s, box-shadow .15s, background .15s;
  }
  .btn:hover{transform:translateY(-2px); box-shadow:0 10px 24px rgba(108,92,231,.35)}
  .hint{margin-top:20px;font-size:12.5px;color:var(--muted);opacity:.75}
</style>
</head>
<body>
<div class="err-card">
  <div class="err-badge">🔒</div>
  <h1>403</h1>
  <h2>غير مصرّح بالوصول</h2>
  <p>ليس لديك الصلاحية المطلوبة لعرض هذه الصفحة. إذا كنت تعتقد أن هذا خطأ، تواصل مع المشرف الأعلى أو تحقق من صلاحيات حسابك.</p>
  <a href="<?= htmlspecialchars(dirname($_SERVER['SCRIPT_NAME']) === '/' ? '/admin/' : dirname($_SERVER['SCRIPT_NAME']) . '/index.php') ?>" class="btn">العودة إلى لوحة التحكم</a>
  <div class="hint">تم تسجيل محاولة الوصول هذه في سجل العمليات.</div>
</div>
</body>
</html>
