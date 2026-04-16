<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\LeadService;

class LeadController extends Controller
{
    public function edit(): void
    {
        $this->requireRole(['admin', 'manager']);

        $leadId = (int) ($_GET['id'] ?? 0);
        $service = new LeadService($this->db);
        $lead = $service->find($leadId);

        if (!$lead) {
            http_response_code(404);
            echo 'Lead not found';
            return;
        }

        $this->view('leads/edit', ['lead' => $lead]);
    }

    public function update(): void
    {
        $this->requireRole(['admin', 'manager']);
        Csrf::verify($_POST['_csrf'] ?? null);

        $leadId = (int) ($_POST['lead_id'] ?? 0);
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);

        try {
            (new LeadService($this->db))->update($leadId, $_POST);
            header('Location: /campaigns/show?id=' . $campaignId . '&notice=lead-updated');
        } catch (\Throwable $e) {
            header('Location: /leads/edit?id=' . $leadId . '&error=' . urlencode($e->getMessage()));
        }
    }

    public function reset(): void
    {
        $this->requireRole(['admin', 'manager']);
        Csrf::verify($_POST['_csrf'] ?? null);

        $leadId = (int) ($_POST['lead_id'] ?? 0);
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);

        try {
            (new LeadService($this->db))->resetForReuse($leadId);
            header('Location: /campaigns/show?id=' . $campaignId . '&notice=lead-reset');
        } catch (\Throwable $e) {
            header('Location: /campaigns/show?id=' . $campaignId . '&error=' . urlencode($e->getMessage()));
        }
    }

    public function resetCampaign(): void
    {
        $this->requireRole(['admin', 'manager']);
        Csrf::verify($_POST['_csrf'] ?? null);

        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        $count = (new LeadService($this->db))->resetCampaignTerminalLeads($campaignId);

        header('Location: /campaigns/show?id=' . $campaignId . '&notice=' . urlencode($count . ' leads reset'));
    }
}
