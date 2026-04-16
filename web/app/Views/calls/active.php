<?php
$formatDate = static function (?string $value): string {
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, Y H:i:s', $timestamp) : $value;
};
?>

<div class="page-head">
    <div>
        <p class="section-kicker">Live Monitor</p>
        <h1>Active Calls</h1>
        <p class="subtle mb-0">Calls refresh automatically every two seconds.</p>
    </div>
    <span class="status running" id="refresh-state">
        <i class="bi bi-arrow-repeat me-1"></i>
        Live
    </span>
</div>

<div class="panel">
    <div class="section-title">
        <div>
            <h2>Current Call Activity</h2>
            <p class="section-kicker"><span id="active-call-count"><?= count($calls) ?></span> active calls</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table table-hover align-middle" id="active-calls">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Campaign</th>
                    <th>Phone</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Vendor</th>
                    <th>Dialed</th>
                    <th>Answered</th>
                </tr>
            </thead>
            <tbody id="active-calls-body">
                <?php if (empty($calls)): ?>
                    <tr data-empty-row="1">
                        <td colspan="8">
                            <div class="soft-card text-center py-5">
                                <i class="bi bi-activity fs-1 text-muted"></i>
                                <h2 class="mt-3">No active calls</h2>
                                <p class="subtle mb-0">Running campaign calls will appear here.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($calls as $call): ?>
                        <?php $name = trim(($call['first_name'] ?? '') . ' ' . ($call['last_name'] ?? '')); ?>
                        <?php $status = (string) ($call['status'] ?? ''); ?>
                        <tr>
                            <td class="text-muted">#<?= (int) $call['id'] ?></td>
                            <td><strong><?= htmlspecialchars($call['campaign_name']) ?></strong></td>
                            <td class="mono"><?= htmlspecialchars($call['phone_number']) ?></td>
                            <td><?= htmlspecialchars($name !== '' ? $name : '-') ?></td>
                            <td><span class="status <?= htmlspecialchars($status) ?>"><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></span></td>
                            <td><?= htmlspecialchars((string) ($call['vendor_name'] ?: '-')) ?></td>
                            <td><?= htmlspecialchars($formatDate($call['dialed_at'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($formatDate($call['answered_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const body = document.getElementById('active-calls-body');
const count = document.getElementById('active-call-count');
const refreshState = document.getElementById('refresh-state');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));
}

function displayDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
}

function emptyRow() {
    return `
        <tr data-empty-row="1">
            <td colspan="8">
                <div class="soft-card text-center py-5">
                    <i class="bi bi-activity fs-1 text-muted"></i>
                    <h2 class="mt-3">No active calls</h2>
                    <p class="subtle mb-0">Running campaign calls will appear here.</p>
                </div>
            </td>
        </tr>
    `;
}

function renderCalls(calls) {
    count.textContent = calls.length;
    if (calls.length === 0) {
        body.innerHTML = emptyRow();
        return;
    }

    body.innerHTML = calls.map((call) => {
        const name = `${call.first_name ?? ''} ${call.last_name ?? ''}`.trim() || '-';
        const status = call.status ?? '';
        return `
            <tr>
                <td class="text-muted">#${Number(call.id)}</td>
                <td><strong>${escapeHtml(call.campaign_name)}</strong></td>
                <td class="mono">${escapeHtml(call.phone_number)}</td>
                <td>${escapeHtml(name)}</td>
                <td><span class="status ${escapeHtml(status)}">${escapeHtml(status.replaceAll('_', ' '))}</span></td>
                <td>${escapeHtml(call.vendor_name || '-')}</td>
                <td>${escapeHtml(displayDate(call.dialed_at))}</td>
                <td>${escapeHtml(displayDate(call.answered_at))}</td>
            </tr>
        `;
    }).join('');
}

setInterval(async () => {
    try {
        const response = await fetch('/api/calls/active', {headers: {'Accept': 'application/json'}});
        if (!response.ok) {
            throw new Error('refresh failed');
        }

        const data = await response.json();
        renderCalls(data.calls || []);
        refreshState.className = 'status running';
        refreshState.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Live';
    } catch (error) {
        refreshState.className = 'status failed';
        refreshState.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Refresh failed';
    }
}, 2000);
</script>
