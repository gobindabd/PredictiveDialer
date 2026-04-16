<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Services\DashboardService;

class CallApiController extends Controller
{
    public function active(): void
    {
        $this->requireAuth();
        $this->json(['calls' => (new DashboardService($this->db))->activeCalls()]);
    }
}
