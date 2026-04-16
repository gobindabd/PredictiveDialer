<?php

namespace App\Controllers;

use App\Core\Controller;

class ReportController extends Controller
{
    private const PER_PAGE = 50;

    public function dtmf(): void
    {
        $this->requireAuth();

        $dateFrom    = trim($_GET['date_from'] ?? '');
        $dateTo      = trim($_GET['date_to'] ?? '');
        $campaignId  = (int) ($_GET['campaign_id'] ?? 0);
        $dtmfDigit   = $_GET['dtmf_digit'] ?? '';
        $phoneSearch = trim($_GET['phone'] ?? '');
        $page        = max(1, (int) ($_GET['page'] ?? 1));

        // Sanitise digit — must be a single 0-9 character or empty (= all)
        if ($dtmfDigit !== '' && !preg_match('/^[0-9]$/', $dtmfDigit)) {
            $dtmfDigit = '';
        }

        [$whereSql, $params] = $this->buildWhere(
            $dateFrom, $dateTo, $campaignId, $dtmfDigit, $phoneSearch
        );

        $campaigns = $this->db->fetchAll(
            'SELECT id, name FROM campaigns ORDER BY name'
        );

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->exportCsv($whereSql, $params);
            return;
        }

        // ── Aggregate summary (full filtered set, not just current page) ──────
        // Groups by call_id so each call is counted once; MAX(billsec) avoids
        // double-counting duration when a call has multiple DTMF rows.
        $summary = $this->db->fetch(
            "SELECT
                COUNT(*)                        AS total_calls,
                COALESCE(SUM(billsec), 0)       AS total_duration,
                COALESCE(SUM(total_yes), 0)     AS total_yes,
                COALESCE(SUM(total_no), 0)      AS total_no
             FROM (
                 SELECT
                     cd.call_id,
                     MAX(ca.billsec)             AS billsec,
                     SUM(cd.digit = '1')         AS total_yes,
                     SUM(cd.digit = '2')         AS total_no
                 FROM call_dtmf cd
                 JOIN calls ca ON ca.id = cd.call_id
                 JOIN leads  l  ON l.id  = cd.lead_id
                 JOIN campaigns c ON c.id = ca.campaign_id
                 LEFT JOIN vendors v ON v.id = ca.vendor_id
                 $whereSql
                 GROUP BY cd.call_id
             ) AS per_call",
            $params
        ) ?: ['total_calls' => 0, 'total_duration' => 0, 'total_yes' => 0, 'total_no' => 0];

        // ── Pagination ────────────────────────────────────────────────────────
        $countRow = $this->db->fetch(
            "SELECT COUNT(*) AS cnt
             FROM call_dtmf cd
             JOIN calls ca ON ca.id = cd.call_id
             JOIN leads  l  ON l.id  = cd.lead_id
             JOIN campaigns c ON c.id = ca.campaign_id
             LEFT JOIN vendors v ON v.id = ca.vendor_id
             $whereSql",
            $params
        );
        $total      = (int) ($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * self::PER_PAGE;

        $rows = $this->db->fetchAll(
            "SELECT
                l.id            AS lead_id,
                l.phone_number,
                l.first_name,
                l.last_name,
                c.name          AS campaign_name,
                ca.dialed_at,
                ca.answered_at,
                ca.ended_at,
                ca.billsec,
                cd.digit,
                cd.sequence_no,
                COALESCE(ca.disposition, ca.status) AS call_status,
                v.trunk_name,
                v.name          AS vendor_name
             FROM call_dtmf cd
             JOIN calls ca ON ca.id = cd.call_id
             JOIN leads  l  ON l.id  = cd.lead_id
             JOIN campaigns c ON c.id = ca.campaign_id
             LEFT JOIN vendors v ON v.id = ca.vendor_id
             $whereSql
             ORDER BY ca.dialed_at DESC
             LIMIT " . self::PER_PAGE . " OFFSET $offset",
            $params
        );

        $this->view('reports/dtmf', [
            'rows'        => $rows,
            'summary'     => $summary,
            'campaigns'   => $campaigns,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => self::PER_PAGE,
            'totalPages'  => $totalPages,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'campaignId'  => $campaignId,
            'dtmfDigit'   => $dtmfDigit,
            'phoneSearch' => $phoneSearch,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a safe parameterised WHERE clause from the active filters.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildWhere(
        string $dateFrom,
        string $dateTo,
        int    $campaignId,
        string $dtmfDigit,
        string $phoneSearch
    ): array {
        $conditions = [];
        $params     = [];

        if ($dateFrom !== '' && strtotime($dateFrom)) {
            $conditions[] = 'ca.dialed_at >= ?';
            $params[]     = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '' && strtotime($dateTo)) {
            $conditions[] = 'ca.dialed_at <= ?';
            $params[]     = $dateTo . ' 23:59:59';
        }

        if ($campaignId > 0) {
            $conditions[] = 'ca.campaign_id = ?';
            $params[]     = $campaignId;
        }

        if ($dtmfDigit !== '') {
            $conditions[] = 'cd.digit = ?';
            $params[]     = $dtmfDigit;
        }

        if ($phoneSearch !== '') {
            $conditions[] = 'l.phone_number LIKE ?';
            $params[]     = '%' . $phoneSearch . '%';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }

    /**
     * Stream the full result set (no pagination) as a CSV download.
     */
    private function exportCsv(string $whereSql, array $params): void
    {
        $rows = $this->db->fetchAll(
            "SELECT
                l.id            AS lead_id,
                l.phone_number,
                l.first_name,
                l.last_name,
                c.name          AS campaign_name,
                ca.dialed_at,
                ca.answered_at,
                ca.ended_at,
                ca.billsec,
                cd.digit,
                cd.sequence_no,
                COALESCE(ca.disposition, ca.status) AS call_status,
                v.trunk_name,
                v.name          AS vendor_name
             FROM call_dtmf cd
             JOIN calls ca ON ca.id = cd.call_id
             JOIN leads  l  ON l.id  = cd.lead_id
             JOIN campaigns c ON c.id = ca.campaign_id
             LEFT JOIN vendors v ON v.id = ca.vendor_id
             $whereSql
             ORDER BY ca.dialed_at DESC",
            $params
        );

        $filename = 'dtmf_report_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');

        // UTF-8 BOM so Excel opens it correctly
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Lead ID',
            'Phone Number',
            'First Name',
            'Last Name',
            'Campaign',
            'Dialed Time',
            'Answered Time',
            'End Time',
            'Duration (sec)',
            'DTMF Digit',
            'Response',
            'Sequence',
            'Call Status',
            'Trunk',
            'Vendor',
        ]);

        foreach ($rows as $row) {
            $digit    = (string) ($row['digit'] ?? '');
            $response = match ($digit) {
                '1'     => 'YES',
                '2'     => 'NO',
                default => $digit,
            };

            fputcsv($out, [
                $row['lead_id'],
                $row['phone_number'],
                $row['first_name'] ?? '',
                $row['last_name']  ?? '',
                $row['campaign_name'],
                $row['dialed_at']   ?? '',
                $row['answered_at'] ?? '',
                $row['ended_at']    ?? '',
                $row['billsec'],
                $digit,
                $response,
                $row['sequence_no'],
                $row['call_status'] ?? '',
                $row['trunk_name']  ?? '',
                $row['vendor_name'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }
}
