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
        $id      = (int) ($_GET['id'] ?? 0);
        $perPage = 50;
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $campaign = $this->db->fetch(
            <<<'SQL'
            SELECT c.*, v.name AS vendor_name
            FROM campaigns c
            LEFT JOIN vendors v ON v.id = c.vendor_id
            WHERE c.id = ?
            SQL,
            [$id]
        );
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

        $totalLeads = (int) ($stats['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalLeads / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        $leads = $this->db->fetchAll(
            <<<'SQL'
            SELECT
                leads.*,
                COALESCE(agg.call_count, 0)    AS call_count,
                agg.last_failure_reason
            FROM leads
            LEFT JOIN (
                SELECT
                    c.lead_id,
                    COUNT(*)                   AS call_count,
                    MAX(IF(c.dialed_at = mx.max_dialed, c.failure_reason, NULL))
                                               AS last_failure_reason
                FROM calls c
                INNER JOIN (
                    SELECT lead_id, MAX(dialed_at) AS max_dialed
                    FROM calls
                    GROUP BY lead_id
                ) mx ON mx.lead_id = c.lead_id
                GROUP BY c.lead_id
            ) agg ON agg.lead_id = leads.id
            WHERE leads.campaign_id = ?
            ORDER BY leads.id DESC
            LIMIT ? OFFSET ?
            SQL,
            [$id, $perPage, $offset]
        );

        $this->view('campaigns/show', [
            'campaign'   => $campaign,
            'errors'     => $errors,
            'stats'      => $stats,
            'leads'      => $leads,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
            'totalLeads' => $totalLeads,
            'notice'     => $_GET['notice'] ?? null,
            'error'      => $_GET['error'] ?? null,
        ]);
    }
}
