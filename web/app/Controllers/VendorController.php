<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\VendorService;

class VendorController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager']);
        $vendors = $this->db->fetchAll("SELECT * FROM vendors ORDER BY priority ASC, id DESC");
        $this->view('vendors/index', ['vendors' => $vendors, 'notice' => $_GET['notice'] ?? null]);
    }

    public function edit(): void
    {
        $this->requireRole(['admin']);
        $vendor = (new VendorService($this->db))->find((int) ($_GET['id'] ?? 0));
        if (!$vendor) {
            http_response_code(404);
            echo 'Vendor not found';
            return;
        }
        $this->view('vendors/edit', ['vendor' => $vendor]);
    }

    public function store(): void
    {
        $this->requireRole(['admin']);
        Csrf::verify($_POST['_csrf'] ?? null);

        (new VendorService($this->db))->create($_POST, $this->config);

        header('Location: /vendors?notice=' . urlencode('Trunk saved.'));
    }

    public function update(): void
    {
        $this->requireRole(['admin']);
        Csrf::verify($_POST['_csrf'] ?? null);

        (new VendorService($this->db))->update((int) ($_POST['vendor_id'] ?? 0), $_POST, $this->config);

        header('Location: /vendors?notice=' . urlencode('Trunk updated.'));
    }
}
