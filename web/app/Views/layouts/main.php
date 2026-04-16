<?php

use App\Core\Csrf;

$user = $_SESSION['user'] ?? null;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navItems = [
    ['href' => '/', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    ['href' => '/campaigns', 'label' => 'Campaigns', 'icon' => 'bi-megaphone'],
    ['href' => '/vendors', 'label' => 'Trunks',  'icon' => 'bi-hdd-network'],
    ['href' => '/prompts', 'label' => 'Prompts', 'icon' => 'bi-volume-up'],
    ['href' => '/calls/active',  'label' => 'Active Calls',  'icon' => 'bi-activity'],
    ['href' => '/reports/dtmf', 'label' => 'DTMF Report',   'icon' => 'bi-123'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Predictive Dialer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ink: #182230;
            --muted: #667085;
            --line: #d0d5dd;
            --panel: #ffffff;
            --page: #f5f7fb;
            --nav: #092b30;
            --nav-soft: #123c43;
            --accent: #147386;
            --danger: #a33a3a;
            --ok: #2d7a46;
        }
        * { box-sizing: border-box; }
        body { font-family: Inter, Arial, sans-serif; margin: 0; color: var(--ink); background: var(--page); }
        .app-shell { min-height: 100vh; display: grid; grid-template-columns: 280px 1fr; }
        aside {
            background: linear-gradient(180deg, var(--nav) 0%, #082328 100%);
            color: white;
            padding: 22px 18px;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .brand { color: white; font-weight: 800; font-size: 20px; text-decoration: none; display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .brand-badge { width: 42px; height: 42px; border-radius: 8px; background: #e5f7fb; color: var(--nav); display: grid; place-items: center; font-weight: 900; }
        nav { display: grid; gap: 7px; }
        nav a { color: #c9dce0; padding: 12px 14px; text-decoration: none; font-weight: 700; border-radius: 8px; display: flex; align-items: center; gap: 12px; }
        nav a:hover, nav a.active { background: rgba(255,255,255,.11); color: white; }
        nav i { font-size: 18px; width: 22px; text-align: center; }
        .content-side { min-width: 0; }
        .topbar { min-height: 68px; background: rgba(255,255,255,.92); border-bottom: 1px solid #e4e7ec; display: flex; align-items: center; justify-content: flex-end; padding: 0 32px; position: sticky; top: 0; z-index: 10; backdrop-filter: blur(10px); }
        main { padding: 34px; max-width: 1480px; margin: 0 auto; width: 100%; }
        h1 { margin: 0 0 8px; font-size: 34px; font-weight: 800; letter-spacing: 0; }
        h2 { margin: 0; font-size: 21px; font-weight: 800; }
        .table { margin: 0; }
        .table thead th { background: #f2f4f7; color: #344054; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--line); }
        .table td { vertical-align: middle; }
        .form-control, .form-select { border-radius: 8px; border-color: #cbd5e1; min-height: 44px; }
        .form-control:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 .2rem rgba(20,115,134,.12); }
        .btn { border-radius: 8px; font-weight: 700; }
        .btn-primary, button, .button { background: var(--accent); color: white; border-color: var(--accent); }
        .btn-outline-primary, button.secondary, .button.secondary { background: white; color: var(--accent); border-color: var(--accent); }
        button.danger { background: var(--danger); border-color: var(--danger); }
        form.inline { display: inline; }
        .page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
        .subtle { color: var(--muted); }
        .panel { background: var(--panel); border: 1px solid #e4e7ec; border-radius: 8px; padding: 24px; margin-bottom: 22px; box-shadow: 0 10px 30px rgba(16,24,40,.04); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .dashboard-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .metric { background: white; padding: 16px; border: 1px solid #e4e7ec; border-radius: 8px; box-shadow: 0 8px 20px rgba(16,24,40,.03); }
        .metric strong { display: block; color: var(--muted); font-size: 13px; margin-bottom: 6px; text-transform: uppercase; }
        .metric span { font-size: 24px; font-weight: 800; }
        .actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .status { display: inline-flex; align-items: center; padding: 4px 9px; border-radius: 6px; background: #e9eef5; font-weight: 700; }
        .status.running, .status.answered, .status.completed { background: #dff1e5; color: var(--ok); }
        .status.draft, .status.scheduled, .status.queued, .status.pending { background: #e7f0fb; color: #175cd3; }
        .status.paused, .status.stopping, .status.stopped { background: #fff2d6; color: #946200; }
        .status.failed, .status.busy, .status.no_answer { background: #f8e4e4; color: var(--danger); }
        .notice { background: #e6f4eb; color: #245a36; border: 1px solid #b8dec4; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .error { color: #8a1f1f; background: #fbeaea; border: 1px solid #efc1c1; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .table-wrap { overflow-x: auto; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        label { font-weight: 700; }
        label span { display: block; margin-bottom: 4px; }
        .checkline { display: inline-flex; gap: 8px; align-items: center; margin: 12px 0; }
        .section-title { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .section-kicker { color: var(--muted); font-size: 14px; margin: 4px 0 0; }
        .soft-card { background: #f8fafc; border: 1px solid #e4e7ec; border-radius: 8px; padding: 16px; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .avatar { width: 36px; height: 36px; border-radius: 8px; background: #e7f5f8; color: var(--accent); display: grid; place-items: center; font-weight: 800; }
        @media (max-width: 860px) {
            .app-shell { grid-template-columns: 1fr; }
            aside { position: static; height: auto; }
            nav { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
            .dashboard-metrics { grid-template-columns: 1fr; }
            main { padding: 18px; }
        }
        @media (min-width: 861px) and (max-width: 1180px) {
            .dashboard-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <aside>
        <a class="brand" href="/">
            <span class="brand-badge">PD</span>
            <span>Predictive Dialer</span>
        </a>
        <nav>
            <?php foreach ($navItems as $item): ?>
                <?php $active = $item['href'] === '/' ? $currentPath === '/' : str_starts_with($currentPath, $item['href']); ?>
                <a class="<?= $active ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
                    <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="content-side">
        <div class="topbar">
            <?php if ($user): ?>
                <span class="avatar me-2"><?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?></span>
                <span class="me-3 text-muted"><?= htmlspecialchars($user['username']) ?></span>
                <form action="/logout" method="post" class="inline">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <button type="submit" class="btn btn-outline-primary">Logout</button>
                </form>
            <?php endif; ?>
        </div>
        <main>
            <?php require $viewFile; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
