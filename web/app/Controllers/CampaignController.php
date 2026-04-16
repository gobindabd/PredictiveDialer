<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\CampaignService;

class CampaignController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $campaigns = $this->db->fetchAll(
            <<<'SQL'
            SELECT c.*, v.name AS vendor_name, v.trunk_name
            FROM campaigns c
            LEFT JOIN vendors v ON v.id = c.vendor_id
            ORDER BY c.created_at DESC
            LIMIT 100
            SQL
        );
        $this->view('campaigns/index', ['campaigns' => $campaigns]);
    }

    public function create(): void
    {
        $this->requireRole(['admin', 'manager']);
        $vendors = $this->db->fetchAll("SELECT id, name FROM vendors WHERE is_active = 1 ORDER BY name");
        $prompts = $this->db->fetchAll("SELECT id, name FROM audio_prompts WHERE is_active = 1 ORDER BY name");
        $this->view('campaigns/create', ['vendors' => $vendors, 'prompts' => $prompts]);
    }

    public function store(): void
    {
        $this->requireRole(['admin', 'manager']);
        Csrf::verify($_POST['_csrf'] ?? null);
        $service = new CampaignService($this->db);
        $id = $service->create($_POST, (int) ($this->currentUser()['id'] ?? 0));
        header('Location: /campaigns/show?id=' . $id);
    }

    public function show(): void
    {
        $this->requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $campaign = $this->db->fetch("SELECT * FROM campaigns WHERE id = ?", [$id]);
        if (!$campaign) {
            http_response_code(404);
            echo 'Campaign not found';
            return;
        }
        $errors = $this->db->fetchAll(
            <<<'SQL'
            SELECT e.*
            FROM lead_import_errors e
            JOIN lead_imports i ON i.id = e.import_id
            WHERE i.campaign_id = ?
            ORDER BY e.id DESC
            LIMIT 100
            SQL,
            [$id]
        );

        $stats = $this->db->fetch(
            <<<'SQL'
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'pending'), 0) AS pending,
                COALESCE(SUM(status = 'queued'), 0) AS queued,
                COALESCE(SUM(status = 'dialing'), 0) AS dialing,
                COALESCE(SUM(status = 'answered'), 0) AS answered,
                COALESCE(SUM(status = 'completed'), 0) AS completed,
                COALESCE(SUM(status = 'failed'), 0) AS failed,
                COALESCE(SUM(status = 'no_answer'), 0) AS no_answer,
                COALESCE(SUM(status = 'busy'), 0) AS busy
            FROM leads
            WHERE campaign_id = ?
            SQL,
            [$id]
        ) ?: [];

        $leads = $this->db->fetchAll(
            <<<'SQL'
            SELECT
                leads.*,
                (
                    SELECT COUNT(*)
                    FROM calls
                    WHERE calls.lead_id = leads.id
                ) AS call_count,
                (
                    SELECT calls.failure_reason
                    FROM calls
                    WHERE calls.lead_id = leads.id
                    ORDER BY calls.dialed_at DESC
                    LIMIT 1
                ) AS last_failure_reason
            FROM leads
            WHERE leads.campaign_id = ?
            ORDER BY leads.id DESC
            LIMIT 250
            SQL,
            [$id]
        );

        $this->view('campaigns/show', [
            'campaign' => $campaign,
            'errors' => $errors,
            'stats' => $stats,
            'leads' => $leads,
            'notice' => $_GET['notice'] ?? null,
            'error' => $_GET['error'] ?? null,
        ]);
    }
}
