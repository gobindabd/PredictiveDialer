<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $service = new DashboardService($this->db);
        $this->view('dashboard/index', $service->summary());
    }
}
