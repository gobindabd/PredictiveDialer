<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\CampaignService;

class CampaignApiController extends Controller
{
    private function change(string $status): void
    {
        $this->requireRole(['admin', 'manager']);
        Csrf::verify($_POST['_csrf'] ?? null);
        $id = (int) ($_POST['campaign_id'] ?? 0);
        $service = new CampaignService($this->db);
        $service->changeStatus($id, $status, (int) ($this->currentUser()['id'] ?? 0));
        $this->json(['ok' => true, 'campaign_id' => $id, 'status' => $status]);
    }

    public function start(): void
    {
        $this->change('running');
    }

    public function pause(): void
    {
        $this->change('paused');
    }

    public function resume(): void
    {
        $this->change('running');
    }

    public function stop(): void
    {
        $this->change('stopping');
    }
}
