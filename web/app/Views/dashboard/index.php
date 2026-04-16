<div class="page-head">
    <div>
        <div class="text-uppercase text-muted fw-bold small mb-2">Overview</div>
        <h1>Dashboard</h1>
        <div class="subtle">Live campaign totals, lead progress, and engine health.</div>
    </div>
    <?php if (!empty($engine)): ?>
        <span class="status <?= ($engine['status'] ?? '') === 'running' ? 'running' : 'failed' ?>">
            <i class="bi bi-cpu me-1"></i>
            Engine <?= htmlspecialchars((string) $engine['status']) ?>
        </span>
    <?php endif; ?>
</div>

<?php
$metricLabels = [
    'total_uploaded_leads' => ['label' => 'Uploaded leads', 'icon' => 'bi-people'],
    'processed_leads' => ['label' => 'Processed leads', 'icon' => 'bi-check2-circle'],
    'dialed_leads' => ['label' => 'Dialed leads', 'icon' => 'bi-telephone-outbound'],
    'queued_leads' => ['label' => 'Queued leads', 'icon' => 'bi-list-check'],
    'answered_calls' => ['label' => 'Answered calls', 'icon' => 'bi-telephone-inbound'],
    'failed_calls' => ['label' => 'Failed calls', 'icon' => 'bi-exclamation-triangle'],
];
?>

<section class="dashboard-metrics">
    <?php foreach ($metricLabels as $key => $meta): ?>
        <div class="metric">
            <div class="d-flex align-items-center justify-content-between">
                <strong><?= htmlspecialchars($meta['label']) ?></strong>
                <i class="bi <?= htmlspecialchars($meta['icon']) ?> text-muted"></i>
            </div>
            <span><?= (int) (($global ?? [])[$key] ?? 0) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<section class="panel mt-4">
    <div class="section-title">
        <div>
            <h2>Campaigns</h2>
            <p class="section-kicker">Campaign-wise lead and call performance.</p>
        </div>
        <a class="btn btn-primary" href="/campaigns/create">
            <i class="bi bi-plus-circle me-2"></i>Create campaign
        </a>
    </div>

    <div class="table-wrap">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Processed</th>
                    <th class="text-end">Dialed</th>
                    <th class="text-end">Answered</th>
                    <th class="text-end">Failed</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns ?? [] as $campaign): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($campaign['name']) ?></div>
                            <div class="small text-muted">Campaign #<?= (int) $campaign['id'] ?></div>
                        </td>
                        <td><span class="status <?= htmlspecialchars($campaign['status']) ?>"><?= htmlspecialchars($campaign['status']) ?></span></td>
                        <td class="text-end"><?= (int) $campaign['total_leads'] ?></td>
                        <td class="text-end"><?= (int) $campaign['processed_leads'] ?></td>
                        <td class="text-end"><?= (int) $campaign['dialed_leads'] ?></td>
                        <td class="text-end"><?= (int) $campaign['answered_calls'] ?></td>
                        <td class="text-end"><?= (int) $campaign['failed_calls'] ?></td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="/campaigns/show?id=<?= (int) $campaign['id'] ?>">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            No campaigns yet. Create one to start importing leads.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

