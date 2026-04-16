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

        $service = new CsvLeadImportService($this->db);
        $service->validateAndImport($campaignId, $importId, $target);

        header('Location: /campaigns/show?id=' . $campaignId);
    }
}
