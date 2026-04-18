<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\CsvLeadImportService;
use RuntimeException;

class LeadImportController extends Controller
{
    public function upload(): void
    {
        $this->requireRole(['admin', 'manager']);
        Csrf::verify($_POST['_csrf'] ?? null);

        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('CSV upload failed.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new RuntimeException('Only CSV files are allowed.');
        }

        $stored = bin2hex(random_bytes(16)) . '.csv';
        $target = rtrim($this->config['csv_upload_path'], '/') . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Unable to store CSV upload.');
        }

        $importId = $this->db->insert(
            <<<'SQL'
            INSERT INTO lead_imports (campaign_id, original_filename, stored_filename, status, uploaded_by)
            VALUES (?, ?, ?, 'importing', ?)
            SQL,
            [$campaignId, $file['name'], $stored, $this->currentUser()['id'] ?? null]
        );

        try {
            $result = (new CsvLeadImportService($this->db))->validateAndImport($campaignId, $importId, $target);
            $msg = $result['valid'] . ' lead' . ($result['valid'] !== 1 ? 's' : '') . ' imported';
            if ($result['invalid'] > 0) {
                $msg .= ', ' . $result['invalid'] . ' skipped (see errors below)';
            }
            header('Location: /campaigns/show?id=' . $campaignId . '&notice=' . urlencode($msg));
        } catch (\Throwable $e) {
            $this->db->execute(
                "UPDATE lead_imports SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?",
                [substr($e->getMessage(), 0, 500), $importId]
            );
            header('Location: /campaigns/show?id=' . $campaignId . '&error=' . urlencode('Import failed: ' . $e->getMessage()));
        }
    }
}
