<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;

class AudioPromptService
{
    public function __construct(private Database $db, private array $config)
    {
    }

    public function store(array $file, string $name, int $userId): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Prompt upload failed.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp3', 'wav'], true)) {
            throw new RuntimeException('Only MP3 and WAV files are allowed.');
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = rtrim($this->config['prompt_original_path'], '/') . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Unable to store prompt.');
        }

        $asteriskFilename = $this->convertForAsterisk($target);
        if ($asteriskFilename === null) {
            @unlink($target);
            throw new RuntimeException('Audio conversion failed. Ensure ffmpeg is installed and the file is a valid audio format.');
        }

        return $this->db->insert(
            <<<'SQL'
            INSERT INTO audio_prompts (
                name, original_filename, stored_filename, asterisk_filename, file_type, uploaded_by
            ) VALUES (?, ?, ?, ?, ?, ?)
            SQL,
            [$name, $file['name'], $stored, $asteriskFilename, $ext, $userId]
        );
    }

    public function delete(int $id): ?string
    {
        $prompt = $this->db->fetch('SELECT * FROM audio_prompts WHERE id = ?', [$id]);
        if (!$prompt) {
            return 'Prompt not found.';
        }

        // Block deletion if a non-terminal campaign is actively using this prompt
        $blocking = $this->db->fetch(
            "SELECT id FROM campaigns
             WHERE audio_prompt_id = ?
               AND status IN ('running','paused','scheduled','stopping')
             LIMIT 1",
            [$id]
        );
        if ($blocking) {
            return 'Cannot delete: an active or scheduled campaign is using this prompt. Stop the campaign first.';
        }

        // Detach from any completed/stopped campaigns (FK would block the delete otherwise)
        $this->db->execute(
            'UPDATE campaigns SET audio_prompt_id = NULL WHERE audio_prompt_id = ?',
            [$id]
        );

        $this->db->execute('DELETE FROM audio_prompts WHERE id = ?', [$id]);

        // Remove both the original and the Asterisk-converted file
        $origPath = rtrim($this->config['prompt_original_path'], '/') . '/' . $prompt['stored_filename'];
        $astFile  = (string) ($prompt['asterisk_filename'] ?? '');
        $astPath  = $astFile !== ''
            ? rtrim($this->config['prompt_asterisk_path'], '/') . '/' . $astFile
            : '';

        if (is_file($origPath)) {
            @unlink($origPath);
        }
        if ($astPath !== '' && is_file($astPath)) {
            @unlink($astPath);
        }

        return null;
    }

    private function convertForAsterisk(string $sourcePath): ?string
    {
        $outputName = pathinfo($sourcePath, PATHINFO_FILENAME) . '-asterisk.wav';
        $outputPath = rtrim($this->config['prompt_asterisk_path'], '/') . '/' . $outputName;

        $command = sprintf(
            'ffmpeg -y -i %s -ar 8000 -ac 1 -sample_fmt s16 %s 2>&1',
            escapeshellarg($sourcePath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($outputPath)) {
            return null;
        }

        return $outputName;
    }
}
