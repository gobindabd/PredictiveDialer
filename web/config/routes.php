<?php

use App\Controllers\Api\CallApiController;
use App\Controllers\Api\CampaignApiController;
use App\Controllers\AudioPromptController;
use App\Controllers\AuthController;
use App\Controllers\CallMonitorController;
use App\Controllers\CampaignController;
use App\Controllers\DashboardController;
use App\Controllers\LeadController;
use App\Controllers\LeadImportController;
use App\Controllers\ReportController;
use App\Controllers\VendorController;

return function ($router): void {
    // Auth
    $router->get('/login',  AuthController::class, 'loginForm');
    $router->post('/login',  AuthController::class, 'login');
    $router->post('/logout', AuthController::class, 'logout');

    // Dashboard
    $router->get('/', DashboardController::class, 'index');

    // Campaigns
    $router->get('/campaigns',        CampaignController::class, 'index');
    $router->get('/campaigns/create', CampaignController::class, 'create');
    $router->post('/campaigns',        CampaignController::class, 'store');
    $router->get('/campaigns/show',   CampaignController::class, 'show');

    // Leads
    $router->post('/leads/upload',          LeadImportController::class, 'upload');
    $router->get('/leads/edit',             LeadController::class,       'edit');
    $router->post('/leads/update',          LeadController::class,       'update');
    $router->post('/leads/reset',           LeadController::class,       'reset');
    $router->post('/leads/reset-campaign',  LeadController::class,       'resetCampaign');
    $router->post('/leads/delete',          LeadController::class,       'delete');

    // Trunks (vendors)
    $router->get('/vendors',        VendorController::class, 'index');
    $router->get('/vendors/edit',   VendorController::class, 'edit');
    $router->post('/vendors',       VendorController::class, 'store');
    $router->post('/vendors/update', VendorController::class, 'update');

    // Audio prompts
    $router->get('/prompts',         AudioPromptController::class, 'index');
    $router->post('/prompts',        AudioPromptController::class, 'store');
    $router->get('/prompts/serve',   AudioPromptController::class, 'serve');
    $router->post('/prompts/delete', AudioPromptController::class, 'delete');

    // Live call monitor
    $router->get('/calls/active', CallMonitorController::class, 'active');

    // Reports
    $router->get('/reports/dtmf', ReportController::class, 'dtmf');

    // JSON APIs
    $router->post('/api/campaigns/start',  CampaignApiController::class, 'start');
    $router->post('/api/campaigns/pause',  CampaignApiController::class, 'pause');
    $router->post('/api/campaigns/resume', CampaignApiController::class, 'resume');
    $router->post('/api/campaigns/stop',   CampaignApiController::class, 'stop');
    $router->get('/api/calls/active',      CallApiController::class,     'active');
};
