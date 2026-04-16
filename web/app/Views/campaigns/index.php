<?php
$formatDate = static function (?string $value): string {
    if (!$value) {
        return 'Not scheduled';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, Y H:i', $timestamp) : $value;
};
?>

<div class="page-head">
    <div>
        <p class="section-kicker">Campaign Operations</p>
        <h1>Campaigns</h1>
        <p class="subtle mb-0">Create campaigns, monitor state, and open a campaign to upload or reuse leads.</p>
    </div>
    <a class="btn btn-primary" href="/campaigns/create">
        <i class="bi bi-plus-lg me-1"></i>
        Create Campaign
    </a>
</div>

<div class="panel">
    <div class="section-title">
        <div>
            <h2>Campaign List</h2>
            <p class="section-kicker">Latest 100 campaigns</p>
        </div>
    </div>

    <?php if (empty($campaigns)): ?>
        <div class="soft-card text-center py-5">
            <i class="bi bi-megaphone fs-1 text-muted"></i>
            <h2 class="mt-3">No campaigns yet</h2>
            <p class="subtle">Create your first campaign before importing leads.</p>
            <a class="btn btn-primary" href="/campaigns/create">Create Campaign</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Assigned Trunk</th>
                        <th>Scheduled</th>
                        <th>Started</th>
                        <th>Completed</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                        <?php $status = (string) ($campaign['status'] ?? 'draft'); ?>
                        <tr>
                            <td class="text-muted">#<?= (int) $campaign['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($campaign['name']) ?></strong>
                            </td>
                            <td>
                                <span class="status <?= htmlspecialchars($status) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($campaign['trunk_name'])): ?>
                                    <span class="badge text-bg-light border mono"><?= htmlspecialchars($campaign['trunk_name']) ?></span>
                                    <div class="small text-muted"><?= htmlspecialchars((string) $campaign['vendor_name']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Auto select</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($formatDate($campaign['scheduled_at'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($formatDate($campaign['started_at'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($formatDate($campaign['completed_at'] ?? null)) ?></td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm" href="/campaigns/show?id=<?= (int) $campaign['id'] ?>">
                                    Open
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
