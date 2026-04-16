<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\AudioPromptService;

class AudioPromptController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager']);
        $prompts = $this->db->fetchAll("SELECT * FROM audio_prompts ORDER BY created_at DESC");
        $this->view('prompts/index', [
            'prompts' => $prompts,
            'notice'  => $_GET['notice'] ?? null,
            'error'   => $_GET['error']  ?? null,
        ]);
    }

    public function store(): void
    {
        $this->requireRole(['admin', 'manager']);
        Csrf::verify($_POST['_csrf'] ?? null);

        $service = new AudioPromptService($this->db, $this->config);
        $service->store($_FILES['prompt_file'] ?? [], trim((string) $_POST['name']), (int) ($this->currentUser()['id'] ?? 0));

        header('Location: /prompts?notice=' . urlencode('Prompt uploaded successfully.'));
    }

    /**
     * Stream an audio file to the browser (supports Range requests for seeking).
     * Auth is required so uploaded files are never publicly accessible.
     */
    public function serve(): void
    {
        $this->requireAuth();

        $id     = (int) ($_GET['id'] ?? 0);
        $prompt = $this->db->fetch('SELECT * FROM audio_prompts WHERE id = ?', [$id]);

        if (!$prompt) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $path = rtrim($this->config['prompt_original_path'], '/') . '/' . $prompt['stored_filename'];

        if (!is_file($path)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $mime = $prompt['file_type'] === 'mp3' ? 'audio/mpeg' : 'audio/wav';
        $size = filesize($path);

        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-store');

        // Support HTTP Range so the browser audio player can seek
        if (!empty($_SERVER['HTTP_RANGE']) &&
            preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)
        ) {
            $start  = (int) $m[1];
            $end    = $m[2] !== '' ? (int) $m[2] : $size - 1;
            $end    = min($end, $size - 1);
            $length = $end - $start + 1;

            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$size}");
            header("Content-Length: {$length}");

            $fp = fopen($path, 'rb');
            fseek($fp, $start);
            $remaining = $length;
            while (!feof($fp) && $remaining > 0) {
                $chunk = min(8192, $remaining);
                echo fread($fp, $chunk);
                $remaining -= $chunk;
            }
            fclose($fp);
            return;
        }

        header('Content-Length: ' . $size);
        readfile($path);
    }

    public function delete(): void
    {
        $this->requireRole(['admin', 'manager']);
        Csrf::verify($_POST['_csrf'] ?? null);

        $service = new AudioPromptService($this->db, $this->config);
        $error   = $service->delete((int) ($_POST['prompt_id'] ?? 0));

        if ($error) {
            header('Location: /prompts?error=' . urlencode($error));
        } else {
            header('Location: /prompts?notice=' . urlencode('Prompt deleted.'));
        }
    }
}
