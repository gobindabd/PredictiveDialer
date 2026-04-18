<?php use App\Core\Csrf; ?>

<div class="page-head">
    <div>
        <p class="section-kicker" style="margin:0 0 4px;">Campaign #<?= (int) $campaign['id'] ?></p>
        <h1 style="margin-bottom:10px;"><?= htmlspecialchars($campaign['name']) ?></h1>
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;font-size:14px;">
            <span class="status <?= htmlspecialchars($campaign['status']) ?>"><?= htmlspecialchars($campaign['status']) ?></span>
            <?php if (!empty($campaign['vendor_name'])): ?>
                <span class="subtle"><i class="bi bi-hdd-network me-1"></i><?= htmlspecialchars($campaign['vendor_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($campaign['scheduled_at'])): ?>
                <span class="subtle"><i class="bi bi-calendar me-1"></i>Scheduled: <?= htmlspecialchars($campaign['scheduled_at']) ?></span>
            <?php endif; ?>
            <?php if (!empty($campaign['started_at'])): ?>
                <span class="subtle"><i class="bi bi-play-circle me-1"></i>Started: <?= htmlspecialchars($campaign['started_at']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions">
        <a class="button secondary" href="/campaigns"><i class="bi bi-arrow-left me-1"></i>Back to campaigns</a>
    </div>
</div>

<?php if (!empty($notice)): ?>
    <div class="notice"><?= htmlspecialchars((string) $notice) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars((string) $error) ?></div>
<?php endif; ?>

<section class="grid">
    <?php foreach (['total','pending','queued','dialing','answered','completed','failed','no_answer','busy'] as $key): ?>
        <div class="metric">
            <strong><?= htmlspecialchars(str_replace('_', ' ', $key)) ?></strong>
            <span><?= (int) ($stats[$key] ?? 0) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<?php
$cs = $campaign['status'];
// Which actions are valid from the current status (mirrors CampaignService::TRANSITIONS).
$canStart  = in_array($cs, ['draft','scheduled','stopped','completed','failed','paused'], true);
$canPause  = $cs === 'running';
$canResume = $cs === 'paused';
$canStop   = in_array($cs, ['running','paused'], true);
$isBusy    = $cs === 'stopping';
?>
<section class="panel">
    <h2>Campaign Controls</h2>
    <?php if ($isBusy): ?>
        <p class="subtle" style="margin:0 0 12px;">
            <i class="bi bi-hourglass-split me-1"></i>
            Campaign is stopping — waiting for active calls to finish…
        </p>
    <?php endif; ?>
    <div class="actions" id="campaign-controls">
        <form class="inline campaign-api-form" method="post" action="/api/campaigns/start">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button <?= !$canStart ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>
                <i class="bi bi-play-fill me-1"></i>Start
            </button>
        </form>
        <form class="inline campaign-api-form" method="post" action="/api/campaigns/pause">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button class="secondary" <?= !$canPause ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>
                <i class="bi bi-pause-fill me-1"></i>Pause
            </button>
        </form>
        <form class="inline campaign-api-form" method="post" action="/api/campaigns/resume">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button class="secondary" <?= !$canResume ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>
                <i class="bi bi-skip-forward-fill me-1"></i>Resume
            </button>
        </form>
        <form class="inline campaign-api-form" method="post" action="/api/campaigns/stop">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button class="danger" <?= !$canStop ? 'disabled style="opacity:.45;cursor:not-allowed;"' : '' ?>>
                <i class="bi bi-stop-fill me-1"></i>Stop
            </button>
        </form>
        <form class="inline" method="post" action="/leads/reset-campaign"
              onsubmit="return confirm('Reset all completed, failed, no-answer, busy, and answered leads to pending? Call history is kept.');">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button class="secondary"><i class="bi bi-arrow-repeat me-1"></i>Reuse dialed leads</button>
        </form>
    </div>
</section>
<script>
document.querySelectorAll('.campaign-api-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('button');
        btn.disabled = true;
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.ok) {
                window.location.reload();
            } else {
                alert('Action failed: ' + (data.error || 'unknown error'));
                btn.disabled = false;
            }
        })
        .catch(function () {
            alert('Request failed. Please try again.');
            btn.disabled = false;
        });
    });
});
</script>

<section class="panel">
    <h2>Upload Leads</h2>
    <form method="post" action="/leads/upload" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
        <div class="actions">
            <label>
                <span>CSV file</span>
                <input type="file" name="csv_file" accept=".csv" required>
            </label>
            <button type="submit">Upload leads</button>
        </div>
        <p class="subtle">Required column: phone_number. Optional columns: first_name, last_name.</p>
    </form>
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>Leads</h2>
            <p class="section-kicker"><?= number_format($totalLeads) ?> total &mdash; page <?= (int) $page ?> of <?= (int) $totalPages ?></p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Phone</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Last Dialed</th>
                    <th>Calls</th>
                    <th>Last Error</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td><?= (int) $lead['id'] ?></td>
                        <td><?= htmlspecialchars($lead['phone_number']) ?></td>
                        <td><?= htmlspecialchars(trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''))) ?></td>
                        <td><span class="status <?= htmlspecialchars($lead['status']) ?>"><?= htmlspecialchars($lead['status']) ?></span></td>
                        <td><?= (int) $lead['attempts'] ?></td>
                        <td><?= htmlspecialchars((string) ($lead['last_dialed_at'] ?? '')) ?></td>
                        <td><?= (int) $lead['call_count'] ?></td>
                        <td class="subtle" style="font-size:12px;max-width:220px;word-break:break-word;">
                            <?= htmlspecialchars((string) ($lead['last_failure_reason'] ?? '')) ?>
                        </td>
                        <td>
                            <div class="actions" style="flex-wrap:nowrap;">
                                <a class="button secondary" href="/leads/edit?id=<?= (int) $lead['id'] ?>">Edit</a>
                                <form class="inline" method="post" action="/leads/reset" onsubmit="return confirm('Reset this lead to pending for reuse?');">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
                                    <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>">
                                    <button class="secondary">Reset</button>
                                </form>
                                <form class="inline" method="post" action="/leads/delete" onsubmit="return confirm('Permanently delete this lead and its call history?');">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
                                    <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>">
                                    <button class="danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$leads): ?>
                    <tr><td colspan="9">No leads imported yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="subtle" style="margin-top:12px;">
        Showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $totalLeads)) ?> of <?= number_format($totalLeads) ?> leads.
        Resetting a lead keeps call history but makes it dialable again.
    </p>

    <?php if ($totalPages > 1): ?>
        <?php
        $baseUrl = '/campaigns/show?id=' . (int) $campaign['id'];
        $prevPage = $page - 1;
        $nextPage = $page + 1;
        $window   = 2; // pages either side of current
        ?>
        <nav style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:16px;">
            <a class="button secondary<?= $page <= 1 ? ' disabled' : '' ?>"
               href="<?= $page > 1 ? $baseUrl . '&page=' . $prevPage : '#' ?>"
               style="<?= $page <= 1 ? 'opacity:.45;pointer-events:none;' : '' ?>">
                <i class="bi bi-chevron-left"></i>
            </a>

            <?php
            $showPages = [];
            $showPages[] = 1;
            for ($i = max(2, $page - $window); $i <= min($totalPages - 1, $page + $window); $i++) {
                $showPages[] = $i;
            }
            if ($totalPages > 1) {
                $showPages[] = $totalPages;
            }
            $showPages = array_unique($showPages);
            sort($showPages);

            $prev = null;
            foreach ($showPages as $p):
                if ($prev !== null && $p - $prev > 1): ?>
                    <span class="subtle" style="padding:0 4px;">…</span>
                <?php endif; ?>
                <a class="button <?= $p === $page ? '' : 'secondary' ?>"
                   href="<?= $baseUrl . '&page=' . $p ?>"
                   style="min-width:36px;<?= $p === $page ? 'pointer-events:none;' : '' ?>">
                    <?= $p ?>
                </a>
            <?php
                $prev = $p;
            endforeach; ?>

            <a class="button secondary<?= $page >= $totalPages ? ' disabled' : '' ?>"
               href="<?= $page < $totalPages ? $baseUrl . '&page=' . $nextPage : '#' ?>"
               style="<?= $page >= $totalPages ? 'opacity:.45;pointer-events:none;' : '' ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </nav>
    <?php endif; ?>
</section>

<?php if ($errors): ?>
    <section class="panel">
        <h2>Recent Upload Errors</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Row</th><th>Field</th><th>Value</th><th>Error</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($errors as $error): ?>
                        <tr>
                            <td><?= (int) $error['row_num'] ?></td>
                            <td><?= htmlspecialchars((string) $error['field_name']) ?></td>
                            <td><?= htmlspecialchars((string) $error['raw_value']) ?></td>
                            <td><?= htmlspecialchars($error['error_message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
