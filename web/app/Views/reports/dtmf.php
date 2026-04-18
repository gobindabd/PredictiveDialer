<?php
/**
 * Call Analysis Report (DTMF Yes / No).
 *
 * Variables injected by ReportController::dtmf():
 *   array   $rows        - paginated result rows
 *   array   $summary     - aggregate totals: total_calls, total_duration, total_yes, total_no
 *   array   $campaigns   - all campaigns for the filter dropdown
 *   int     $total       - total matching DTMF rows (for pagination)
 *   int     $page        - current page number
 *   int     $perPage     - rows per page
 *   int     $totalPages  - total page count
 *   string  $dateFrom    - active date_from filter
 *   string  $dateTo      - active date_to filter
 *   int     $campaignId  - active campaign_id filter (0 = all)
 *   string  $dtmfDigit   - active dtmf_digit filter ('' = all)
 *   string  $phoneSearch - active phone search
 */

// ── Helper closures ────────────────────────────────────────────────────────────

$fmtDate = static function (?string $v): string {
    if (!$v) {
        return '—';
    }
    $ts = strtotime($v);
    return $ts ? date('Y-m-d H:i:s', $ts) : $v;
};

$fmtDuration = static function (int|string|null $secs): string {
    $secs = (int) $secs;
    if ($secs <= 0) {
        return '0s';
    }
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $s = $secs % 60;
    return ($h ? "{$h}h " : '') . ($m ? "{$m}m " : '') . "{$s}s";
};

$statusClass = static function (?string $status): string {
    return match (strtolower((string) $status)) {
        'answered', 'completed'  => 'answered',
        'no_answer', 'no answer' => 'no_answer',
        'busy'                   => 'busy',
        'failed', 'cancelled'    => 'failed',
        default                  => '',
    };
};

// Build a query string that preserves all active filters plus arbitrary overrides.
$filterParams = static function (array $overrides = []) use (
    $dateFrom, $dateTo, $campaignId, $dtmfDigit, $phoneSearch
): string {
    $base = array_filter([
        'date_from'   => $dateFrom,
        'date_to'     => $dateTo,
        'campaign_id' => $campaignId ?: null,
        'dtmf_digit'  => $dtmfDigit,
        'phone'       => $phoneSearch,
    ], fn($v) => $v !== null && $v !== '' && $v !== 0);

    return http_build_query(array_merge($base, $overrides));
};

$from = ($page - 1) * $perPage + 1;
$to   = min($page * $perPage, $total);

// Summary shorthand
$totalCalls    = (int) ($summary['total_calls']    ?? 0);
$totalDuration = (int) ($summary['total_duration'] ?? 0);
$totalYes      = (int) ($summary['total_yes']      ?? 0);
$totalNo       = (int) ($summary['total_no']       ?? 0);

// All-calls summary shorthand
$acTotal     = (int) ($allSummary['total_calls']     ?? 0);
$acAnswered  = (int) ($allSummary['answered_calls']  ?? 0);
$acCompleted = (int) ($allSummary['completed_calls'] ?? 0);
$acNoAnswer  = (int) ($allSummary['no_answer_calls'] ?? 0);
$acBusy      = (int) ($allSummary['busy_calls']      ?? 0);
$acFailed    = (int) ($allSummary['failed_calls']    ?? 0);
$acDuration  = (int) ($allSummary['total_duration']  ?? 0);
$acNoDtmf    = max(0, $acAnswered - $totalCalls);

// Digit label lookup used in both table and filter dropdown
$digitLabel = static function (string $digit): string {
    return match ($digit) {
        '1'     => '1 — Yes',
        '2'     => '2 — No',
        default => $digit,
    };
};
?>

<!-- ── Page header ──────────────────────────────────────────────────────────── -->
<div class="page-head">
    <div>
        <p class="section-kicker">Reports</p>
        <h1>Call Analysis Report</h1>
        <p class="subtle mb-0">
            Customer responses per call &mdash;
            <span style="color:var(--ok); font-weight:700;">1&nbsp;=&nbsp;YES</span>
            &nbsp;&nbsp;
            <span style="color:var(--danger); font-weight:700;">2&nbsp;=&nbsp;NO</span>
        </p>
    </div>
    <?php if ($totalCalls > 0): ?>
        <a class="btn btn-outline-primary"
           href="/reports/dtmf?<?= htmlspecialchars($filterParams(['export' => 'csv'])) ?>">
            <i class="bi bi-download me-1"></i>
            Export CSV
        </a>
    <?php endif; ?>
</div>

<!-- ── Filters ──────────────────────────────────────────────────────────────── -->
<div class="panel mb-3">
    <form method="get" action="/reports/dtmf" id="filter-form">
        <div class="form-row">
            <label>
                <span>Date From</span>
                <input type="date" name="date_from" class="form-control"
                       value="<?= htmlspecialchars($dateFrom) ?>">
            </label>
            <label>
                <span>Date To</span>
                <input type="date" name="date_to" class="form-control"
                       value="<?= htmlspecialchars($dateTo) ?>">
            </label>
            <label>
                <span>Campaign</span>
                <select name="campaign_id" class="form-select">
                    <option value="">All campaigns</option>
                    <?php foreach ($campaigns as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"
                            <?= $campaignId === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Response</span>
                <select name="dtmf_digit" class="form-select">
                    <option value="">All responses</option>
                    <?php for ($d = 0; $d <= 9; $d++): ?>
                        <option value="<?= $d ?>"
                            <?= $dtmfDigit === (string) $d ? 'selected' : '' ?>>
                            <?= htmlspecialchars($digitLabel((string) $d)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>
                <span>Phone Number</span>
                <input type="text" name="phone" class="form-control" placeholder="Search phone…"
                       value="<?= htmlspecialchars($phoneSearch) ?>">
            </label>
        </div>
        <div class="mt-3 d-flex gap-2 align-items-center">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel me-1"></i>
                Apply Filters
            </button>
            <a href="/reports/dtmf" class="btn btn-outline-primary">
                <i class="bi bi-x-circle me-1"></i>
                Clear
            </a>
        </div>
    </form>
</div>

<!-- ── All Calls section ─────────────────────────────────────────────────── -->
<div class="panel">
    <div class="section-title mb-3">
        <div>
            <h2>All Calls</h2>
            <p class="section-kicker">Every dialed call in the selected date range — regardless of key-press response</p>
        </div>
    </div>

    <!-- Summary metrics -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:12px; margin-bottom:20px;">
        <div class="metric" style="border-left:4px solid var(--accent);">
            <strong><i class="bi bi-telephone-outbound me-1"></i>Total Dialed</strong>
            <span><?= number_format($acTotal) ?></span>
        </div>
        <div class="metric" style="border-left:4px solid var(--ok);">
            <strong><i class="bi bi-telephone-inbound me-1"></i>Answered</strong>
            <span style="color:var(--ok);"><?= number_format($acAnswered) ?></span>
            <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                <?= $acTotal > 0 ? number_format($acAnswered / $acTotal * 100, 1) . '%' : '—' ?>
            </div>
        </div>
        <div class="metric" style="border-left:4px solid #f59e0b;">
            <strong><i class="bi bi-telephone-minus me-1"></i>No Answer</strong>
            <span style="color:#f59e0b;"><?= number_format($acNoAnswer) ?></span>
        </div>
        <div class="metric" style="border-left:4px solid #94a3b8;">
            <strong><i class="bi bi-telephone-x me-1"></i>Busy</strong>
            <span style="color:#94a3b8;"><?= number_format($acBusy) ?></span>
        </div>
        <div class="metric" style="border-left:4px solid var(--danger);">
            <strong><i class="bi bi-exclamation-circle me-1"></i>Failed</strong>
            <span style="color:var(--danger);"><?= number_format($acFailed) ?></span>
        </div>
        <div class="metric" style="border-left:4px solid #6c7d9e;">
            <strong><i class="bi bi-clock me-1"></i>Total Duration</strong>
            <span style="font-size:20px;"><?= htmlspecialchars($fmtDuration($acDuration)) ?></span>
        </div>
        <div class="metric" style="border-left:4px solid #c084fc;">
            <strong><i class="bi bi-keyboard me-1"></i>No Key Pressed</strong>
            <span style="color:#c084fc;"><?= number_format($acNoDtmf) ?></span>
            <div style="font-size:12px;color:var(--muted);margin-top:4px;">answered, no DTMF</div>
        </div>
    </div>

    <?php if (empty($allCallsRows)): ?>
        <div class="soft-card text-center py-4">
            <i class="bi bi-telephone-x fs-1 text-muted"></i>
            <p class="subtle mt-2 mb-0">No calls found in the selected date range.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width:70px;">Lead ID</th>
                        <th>Phone Number</th>
                        <th>Name</th>
                        <th>Campaign</th>
                        <th>Dialed At</th>
                        <th>Answered At</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Trunk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allCallsRows as $row): ?>
                        <tr>
                            <td class="text-muted">#<?= (int) $row['lead_id'] ?></td>
                            <td class="mono"><?= htmlspecialchars($row['phone_number']) ?></td>
                            <td>
                                <?php
                                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                echo $name !== '' ? htmlspecialchars($name) : '<span class="text-muted">—</span>';
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['campaign_name']) ?></td>
                            <td class="mono"><?= htmlspecialchars($fmtDate($row['dialed_at'] ?? null)) ?></td>
                            <td class="mono"><?= htmlspecialchars($fmtDate($row['answered_at'] ?? null)) ?></td>
                            <td class="mono"><?= htmlspecialchars($fmtDate($row['ended_at'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($fmtDuration($row['billsec'] ?? 0)) ?></td>
                            <td>
                                <?php $cs = (string) ($row['call_status'] ?? ''); ?>
                                <span class="status <?= htmlspecialchars($statusClass($cs)) ?>">
                                    <?= htmlspecialchars(str_replace(['_', '-'], ' ', $cs) ?: '—') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['trunk_name'])): ?>
                                    <span class="badge text-bg-light border mono"><?= htmlspecialchars($row['trunk_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($allCallsRows) >= 200): ?>
            <p class="subtle mt-2" style="font-size:12px;">Showing the 200 most recent calls. Use date filters to narrow the range.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ── Results table ────────────────────────────────────────────────────────── -->
<div class="panel">
    <div class="section-title">
        <div>
            <h2>Call Responses</h2>
            <?php if ($total > 0): ?>
                <p class="section-kicker">
                    Showing <?= number_format($from) ?>–<?= number_format($to) ?>
                    of <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?>
                </p>
            <?php else: ?>
                <p class="section-kicker">No records match the current filters.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="soft-card text-center py-5">
            <i class="bi bi-telephone-x fs-1 text-muted"></i>
            <h2 class="mt-3">No call responses found</h2>
            <p class="subtle">Adjust the filters above or wait for calls to complete.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width:70px;">Lead ID</th>
                        <th>Phone Number</th>
                        <th>Name</th>
                        <th>Campaign</th>
                        <th>Dialed At</th>
                        <th>Answered At</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th style="width:100px; text-align:center;">Response</th>
                        <th>Call Status</th>
                        <th>Trunk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $digit = (string) ($row['digit'] ?? '');

                        // YES / NO badge style
                        if ($digit === '1') {
                            $responseBg    = '#dff1e5';
                            $responseColor = 'var(--ok)';
                            $responseLabel = 'YES';
                            $responseIcon  = 'bi-check-circle-fill';
                        } elseif ($digit === '2') {
                            $responseBg    = '#f8e4e4';
                            $responseColor = 'var(--danger)';
                            $responseLabel = 'NO';
                            $responseIcon  = 'bi-x-circle-fill';
                        } else {
                            $responseBg    = '#e5f7fb';
                            $responseColor = 'var(--accent)';
                            $responseLabel = htmlspecialchars($digit);
                            $responseIcon  = 'bi-hash';
                        }
                        ?>
                        <tr>
                            <td class="text-muted">#<?= (int) $row['lead_id'] ?></td>
                            <td class="mono"><?= htmlspecialchars($row['phone_number']) ?></td>
                            <td>
                                <?php
                                $name = trim(
                                    ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')
                                );
                                echo $name !== ''
                                    ? htmlspecialchars($name)
                                    : '<span class="text-muted">—</span>';
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['campaign_name']) ?></td>
                            <td class="mono"><?= htmlspecialchars($fmtDate($row['dialed_at'] ?? null)) ?></td>
                            <td class="mono"><?= htmlspecialchars($fmtDate($row['answered_at'] ?? null)) ?></td>
                            <td class="mono"><?= htmlspecialchars($fmtDate($row['ended_at'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($fmtDuration($row['billsec'] ?? 0)) ?></td>
                            <td style="text-align:center;">
                                <span style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:5px;
                                    padding:4px 10px;
                                    border-radius:20px;
                                    background:<?= $responseBg ?>;
                                    color:<?= $responseColor ?>;
                                    font-weight:800;
                                    font-size:13px;
                                    letter-spacing:.03em;
                                    white-space:nowrap;
                                ">
                                    <i class="bi <?= $responseIcon ?>"></i>
                                    <?= $responseLabel ?>
                                </span>
                            </td>
                            <td>
                                <?php $cs = (string) ($row['call_status'] ?? ''); ?>
                                <span class="status <?= htmlspecialchars($statusClass($cs)) ?>">
                                    <?= htmlspecialchars(str_replace(['_', '-'], ' ', $cs) ?: '—') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['trunk_name'])): ?>
                                    <span class="badge text-bg-light border mono">
                                        <?= htmlspecialchars($row['trunk_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ─────────────────────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-3 d-flex align-items-center justify-content-between">
                <span class="text-muted" style="font-size:13px;">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link"
                           href="/reports/dtmf?<?= htmlspecialchars($filterParams(['page' => $page - 1])) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    $window = 3;
                    $start  = max(1, $page - $window);
                    $end    = min($totalPages, $page + $window);
                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link"
                               href="/reports/dtmf?<?= htmlspecialchars($filterParams(['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link"
                               href="/reports/dtmf?<?= htmlspecialchars($filterParams(['page' => $p])) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link"
                               href="/reports/dtmf?<?= htmlspecialchars($filterParams(['page' => $totalPages])) ?>">
                                <?= $totalPages ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link"
                           href="/reports/dtmf?<?= htmlspecialchars($filterParams(['page' => $page + 1])) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ── Summary ──────────────────────────────────────────────────────────────── -->
<div class="panel mt-0" style="border-top:3px solid var(--accent);">
    <div class="section-title mb-3">
        <div>
            <h2>Report Summary</h2>
            <p class="section-kicker">Aggregated totals across all matching calls<?= ($dateFrom || $dateTo) ? ' in the selected date range' : '' ?></p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:14px;">

        <!-- Total Calls -->
        <div class="metric" style="border-left:4px solid var(--accent);">
            <strong><i class="bi bi-telephone me-1"></i>Total Calls</strong>
            <span><?= number_format($totalCalls) ?></span>
            <div style="font-size:12px; color:var(--muted); margin-top:4px;">Unique calls with a response</div>
        </div>

        <!-- Total Duration -->
        <div class="metric" style="border-left:4px solid #6c7d9e;">
            <strong><i class="bi bi-clock me-1"></i>Total Duration</strong>
            <span style="font-size:20px;"><?= htmlspecialchars($fmtDuration($totalDuration)) ?></span>
            <div style="font-size:12px; color:var(--muted); margin-top:4px;"><?= number_format($totalDuration) ?> seconds billable</div>
        </div>

        <!-- Total YES -->
        <div class="metric" style="border-left:4px solid var(--ok);">
            <strong style="color:var(--ok);">
                <i class="bi bi-check-circle-fill me-1"></i>YES Responses
            </strong>
            <span style="color:var(--ok);"><?= number_format($totalYes) ?></span>
            <div style="font-size:12px; color:var(--muted); margin-top:4px;">
                <?php if ($totalCalls > 0): ?>
                    <?= number_format($totalYes / $totalCalls * 100, 1) ?>% of calls
                <?php else: ?>
                    —
                <?php endif; ?>
            </div>
        </div>

        <!-- Total NO -->
        <div class="metric" style="border-left:4px solid var(--danger);">
            <strong style="color:var(--danger);">
                <i class="bi bi-x-circle-fill me-1"></i>NO Responses
            </strong>
            <span style="color:var(--danger);"><?= number_format($totalNo) ?></span>
            <div style="font-size:12px; color:var(--muted); margin-top:4px;">
                <?php if ($totalCalls > 0): ?>
                    <?= number_format($totalNo / $totalCalls * 100, 1) ?>% of calls
                <?php else: ?>
                    —
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php if ($totalCalls > 0): ?>
        <!-- YES / NO proportion bar -->
        <?php
        $yesPct = $totalCalls > 0 ? round($totalYes / $totalCalls * 100, 1) : 0;
        $noPct  = $totalCalls > 0 ? round($totalNo  / $totalCalls * 100, 1) : 0;
        ?>
        <div class="mt-4">
            <div style="font-size:13px; font-weight:700; margin-bottom:6px; color:var(--muted);">
                RESPONSE BREAKDOWN
            </div>
            <div style="
                display:flex;
                height:28px;
                border-radius:6px;
                overflow:hidden;
                background:#f2f4f7;
                font-size:12px;
                font-weight:800;
            ">
                <?php if ($yesPct > 0): ?>
                    <div style="
                        width:<?= $yesPct ?>%;
                        background:var(--ok);
                        color:white;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        transition:width .3s;
                        min-width:<?= $yesPct > 8 ? '0' : '44px' ?>;
                    ">
                        <?= $yesPct >= 8 ? "YES {$yesPct}%" : '' ?>
                    </div>
                <?php endif; ?>
                <?php if ($noPct > 0): ?>
                    <div style="
                        width:<?= $noPct ?>%;
                        background:var(--danger);
                        color:white;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        transition:width .3s;
                        min-width:<?= $noPct > 8 ? '0' : '44px' ?>;
                    ">
                        <?= $noPct >= 8 ? "NO {$noPct}%" : '' ?>
                    </div>
                <?php endif; ?>
                <?php
                $otherPct = max(0, 100 - $yesPct - $noPct);
                if ($otherPct > 0):
                ?>
                    <div style="
                        flex:1;
                        background:#cbd5e1;
                        color:#344054;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                    ">
                        <?= $otherPct >= 8 ? "Other {$otherPct}%" : '' ?>
                    </div>
                <?php endif; ?>
            </div>
            <div style="display:flex; gap:16px; margin-top:8px; font-size:12px; color:var(--muted);">
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--ok);margin-right:4px;"></span>YES</span>
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--danger);margin-right:4px;"></span>NO</span>
                <?php if ($otherPct > 0): ?>
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#cbd5e1;margin-right:4px;"></span>Other digits</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
