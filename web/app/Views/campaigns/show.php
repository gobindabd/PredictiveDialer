<?php use App\Core\Csrf; ?>

<div class="page-head">
    <div>
        <h1><?= htmlspecialchars($campaign['name']) ?></h1>
        <div class="subtle">
            Campaign #<?= (int) $campaign['id'] ?>
            · Status <span class="status <?= htmlspecialchars($campaign['status']) ?>"><?= htmlspecialchars($campaign['status']) ?></span>
        </div>
    </div>
    <div class="actions">
        <a class="button secondary" href="/campaigns">Back to campaigns</a>
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

<section class="panel">
    <h2>Campaign Controls</h2>
    <div class="actions" id="campaign-controls">
        <form class="inline campaign-api-form" method="post" action="/api/campaigns/start">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button>Start</button>
        </form>
        <form class="inline campaign-api-form" method="post" action="/api/campaigns/pause">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button class="secondary">Pause</button>
        </form>
        <form class="inline campaign-api-form" method="post" action="/api/campaigns/resume">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button class="secondary">Resume</button>
        </form>
        <form class="inline campaign-api-form" method="post" action="/api/campaigns/stop">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button class="danger">Stop</button>
        </form>
        <form class="inline" method="post" action="/leads/reset-campaign" onsubmit="return confirm('Reset all completed, failed, no answer, busy, and answered leads to pending? Call history will remain.');">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
            <button class="secondary">Reuse dialed leads</button>
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
    <h2>Leads</h2>
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
                            <a class="button secondary" href="/leads/edit?id=<?= (int) $lead['id'] ?>">Edit</a>
                            <form class="inline" method="post" action="/leads/reset" onsubmit="return confirm('Reset this lead to pending for reuse?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
                                <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>">
                                <button class="secondary">Reset</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$leads): ?>
                    <tr><td colspan="9">No leads imported yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="subtle">Showing latest 250 leads. Resetting a lead keeps old call records but makes the lead dialable again.</p>
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
