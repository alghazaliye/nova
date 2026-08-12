<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'لوحة التحكم') ?> — <?= APP_NAME ?> Admin</title>
  <style>
    :root {
      --bg: #f4f6fa;
      --surface: #fff;
      --surface2: #f0f2f7;
      --text: #101828;
      --muted: #667085;
      --line: #e4e7ec;
      --primary: #5b5ce2;
      --primary2: #7c3aed;
      --good: #12b76a;
      --warn: #f79009;
      --bad: #f04438;
      --shadow: 0 10px 30px rgba(16, 24, 40, .07)
    }

    [data-theme=dark] {
      --bg: #080d18;
      --surface: #111827;
      --surface2: #1b2535;
      --text: #f2f4f7;
      --muted: #98a2b3;
      --line: #263244;
      --shadow: 0 12px 35px rgba(0, 0, 0, .25)
    }

    * { box-sizing: border-box }
    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font-family: system-ui, -apple-system, "Segoe UI", Tahoma, Arial, sans-serif;
      transition: background 0.3s, color 0.3s;
    }
    button, input, select, textarea { font: inherit }
    button { cursor: pointer; border: 0; color: inherit; background: none }

    .layout { display: flex; min-height: 100vh }
    .sidebar {
      width: 270px;
      background: var(--surface);
      border-left: 1px solid var(--line);
      position: fixed;
      right: 0; top: 0; bottom: 0;
      z-index: 20;
      padding: 18px 14px;
      overflow: auto;
      transition: .25s
    }
    .brand { display: flex; align-items: center; gap: 10px; padding: 4px 8px 22px }
    .logo {
      width: 44px; height: 44px;
      border-radius: 15px;
      background: linear-gradient(135deg, var(--primary), var(--primary2));
      color: #fff;
      display: grid; place-items: center;
      font-weight: 900
    }
    .brand b { font-size: 20px }
    .brand small { display: block; color: var(--muted); font-size: 11px }
    .navtitle { font-size: 11px; color: var(--muted); font-weight: 800; padding: 15px 10px 6px }
    .nav a {
      display: flex; align-items: center; gap: 11px;
      padding: 12px; border-radius: 14px; margin: 3px 0;
      color: var(--muted); text-decoration: none; font-size: 14px;
      transition: 0.2s;
    }
    .nav a span:first-child { font-size: 19px; width: 25px; text-align: center }
    .nav a:hover, .nav a.active { background: var(--surface2); color: var(--primary); font-weight: 800 }
    .count { margin-right: auto; background: var(--bad); color: #fff; border-radius: 9px; padding: 2px 6px; font-size: 10px }

    .main { margin-right: 270px; flex: 1; min-width: 0 }
    .top {
      height: 72px;
      background: var(--surface);
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0 26px;
      position: sticky;
      top: 0; z-index: 10
    }
    .menu { display: none; font-size: 24px }
    .top h1 { font-size: 20px; margin: 0; flex: 1 }
    .top .search { width: min(340px, 34vw); height: 42px }
    .search {
      display: flex; align-items: center; gap: 8px;
      background: var(--surface2); border: 1px solid var(--line);
      border-radius: 13px; padding: 0 12px
    }
    .search input { border: 0; outline: 0; background: transparent; color: var(--text); width: 100% }
    .top-actions { display: flex; gap: 5px }
    .icon { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; font-size: 19px }
    .icon:hover { background: var(--surface2) }

    .content { padding: 24px; max-width: 1600px; margin: auto }
    .pagehead { display: flex; align-items: center; gap: 10px; margin-bottom: 20px }
    .pagehead h2 { margin: 0; flex: 1; font-size: 23px }
    .pagehead p { margin: 5px 0 0; color: var(--muted); font-size: 13px }

    .btn { padding: 10px 15px; border-radius: 12px; background: var(--surface2); font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn.primary { background: var(--primary); color: #fff }
    .btn.danger { background: #fee4e2; color: #b42318 }
    .btn.sm { padding: 6px 10px; font-size: 12px; border-radius: 8px; }

    .card { background: var(--surface); border: 1px solid var(--line); border-radius: 18px; box-shadow: var(--shadow) }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 18px }
    .stat { padding: 18px; display: flex; gap: 13px; align-items: center }
    .stat .ico { width: 50px; height: 50px; border-radius: 16px; background: var(--surface2); display: grid; place-items: center; font-size: 23px }
    .stat b { font-size: 24px }
    .stat small { display: block; color: var(--muted); margin-top: 3px }
    .trend { font-size: 11px; color: var(--good); font-weight: 800 }

    .grid2 { display: grid; grid-template-columns: 1.5fr 1fr; gap: 18px }
    .panel { padding: 18px }
    .panel h3 { margin: 0 0 15px; font-size: 16px }

    .chart { height: 245px; display: flex; align-items: end; gap: 12px; padding: 15px 8px 5px; border-bottom: 1px solid var(--line) }
    .bar { flex: 1; background: linear-gradient(180deg, var(--primary), var(--primary2)); border-radius: 8px 8px 3px 3px; min-height: 5px; position: relative }
    .bar span { position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 10px; color: var(--muted); white-space: nowrap; }

    .tablewrap { overflow: auto }
    .table { width: 100%; border-collapse: collapse; min-width: 700px }
    .table th, .table td { text-align: right; padding: 13px 10px; border-bottom: 1px solid var(--line); font-size: 13px }
    .table th { color: var(--muted); font-size: 11px }
    .table tr:hover { background: var(--surface2) }

    .user { display: flex; align-items: center; gap: 9px }
    .avatar { width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #ddd9ff, #9ca3ff); display: grid; place-items: center; font-weight: 900; color: #333 }
    .status { padding: 5px 9px; border-radius: 9px; font-size: 11px; font-weight: 800 }
    .status.online { background: #dcfae6; color: #087443 }
    .status.offline { background: var(--surface2); color: var(--muted) }
    .status.blocked { background: #fee4e2; color: #b42318 }

    .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px }
    .filters .search { width: 260px; height: 40px }
    .select { height: 40px; border: 1px solid var(--line); background: var(--surface); color: var(--text); border-radius: 12px; padding: 0 10px; outline: 0; }

    .pagination { display: flex; gap: 6px; align-items: center; margin-top: 16px; }
    .page-btn { padding: 7px 13px; border-radius: 10px; background: var(--surface2); color: var(--text); text-decoration: none; font-size: 13px; font-weight: 700; }
    .page-btn.active { background: var(--primary); color: #fff; }

    .alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 14px; font-weight: 600; }
    .alert-success { background: #dcfae6; color: #087443; border: 1px solid #08744333; }
    .alert-danger { background: #fee4e2; color: #b42318; border: 1px solid #b4231833; }

    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 12px; font-weight: 800; margin-bottom: 6px; color: var(--muted); }
    .form-control {
      width: 100%; height: 44px;
      border: 1px solid var(--line); background: var(--surface2);
      color: var(--text); border-radius: 12px; padding: 0 12px; outline: 0;
    }

    @media (max-width: 1000px) {
      .stats { grid-template-columns: repeat(2, 1fr) }
      .grid2 { grid-template-columns: 1fr }
    }
    @media (max-width: 760px) {
      .sidebar { transform: translateX(100%) }
      .sidebar.open { transform: none }
      .main { margin-right: 0 }
      .menu { display: block }
      .top { padding: 0 14px }
      .top .search { display: none }
      .content { padding: 15px }
      .stats { grid-template-columns: 1fr 1fr }
      .pagehead { align-items: flex-start; flex-wrap: wrap }
    }
  </style>
</head>
<body>
  <div class="layout">
