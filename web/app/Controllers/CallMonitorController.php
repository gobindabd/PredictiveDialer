<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DashboardService;

class CallMonitorController extends Controller
{
    public function active(): void
    {
        $this->requireAuth();
        $calls = (new DashboardService($this->db))->activeCalls();
        $this->view('calls/active', ['calls' => $calls]);
    }
}
