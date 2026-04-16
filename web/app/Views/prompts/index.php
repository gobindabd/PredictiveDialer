<?php use App\Core\Csrf; ?>

<?php
$formatDate = static function (?string $value): string {
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, Y H:i', $ts) : $value;
};
?>

<div class="page-head">
    <div>
        <p class="section-kicker">Call Audio</p>
        <h1>Audio Prompts</h1>
        <p class="subtle mb-0">Upload MP3 or WAV prompts for answered calls and DTMF collection.</p>
    </div>
</div>

<?php if (!empty($notice)): ?>
    <div class="notice"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars((string) $notice) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="error"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars((string) $error) ?></div>
<?php endif; ?>

<!-- ── Upload form ─────────────────────────────────────────────────────────── -->
<form method="post" action="/prompts" enctype="multipart/form-data" class="panel">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="section-title">
        <div>
            <h2>Upload Prompt</h2>
            <p class="section-kicker">Accepted formats: MP3 and WAV. Files are converted to 8 kHz mono for Asterisk.</p>
        </div>
    </div>

    <div class="form-row">
        <label>
            <span>Name</span>
            <input class="form-control" name="name" required maxlength="120" placeholder="Welcome prompt">
        </label>

        <label>
            <span>MP3 or WAV file</span>
            <input class="form-control" type="file" name="prompt_file" accept=".mp3,.wav,audio/*" required>
        </label>
    </div>

    <div class="actions mt-4">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-cloud-arrow-up me-1"></i>Upload Prompt
        </button>
    </div>
</form>

<!-- ── Prompt library ──────────────────────────────────────────────────────── -->
<div class="panel">
    <div class="section-title">
        <div>
            <h2>Prompt Library</h2>
            <p class="section-kicker"><?= count($prompts) ?> prompt<?= count($prompts) !== 1 ? 's' : '' ?> available for campaigns.</p>
        </div>
    </div>

    <?php if (empty($prompts)): ?>
        <div class="soft-card text-center py-5">
            <i class="bi bi-volume-up fs-1 text-muted"></i>
            <h2 class="mt-3">No prompts uploaded yet</h2>
            <p class="subtle mb-0">Upload a prompt above before assigning audio to a campaign.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Original File</th>
                        <th style="width:70px;">Type</th>
                        <th style="width:150px;">Uploaded</th>
                        <th style="width:80px;">Status</th>
                        <th style="width:260px;">Preview</th>
                        <th class="text-end" style="width:90px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prompts as $prompt): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($prompt['name']) ?></strong></td>
                            <td class="mono small text-muted"><?= htmlspecialchars($prompt['original_filename']) ?></td>
                            <td>
                                <span class="status"><?= htmlspecialchars(strtoupper((string) $prompt['file_type'])) ?></span>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($formatDate($prompt['created_at'] ?? null)) ?></td>
                            <td>
                                <?php if ($prompt['is_active']): ?>
                                    <span class="status completed">active</span>
                                <?php else: ?>
                                    <span class="status stopped">inactive</span>
                                <?php endif; ?>
                            </td>

                            <!-- Inline audio player (preload=none: no data fetched until user presses play) -->
                            <td>
                                <audio
                                    controls
                                    preload="none"
                                    src="/prompts/serve?id=<?= (int) $prompt['id'] ?>"
                                    style="height:32px; width:240px; border-radius:6px; accent-color:var(--accent);"
                                >
                                    Your browser does not support the audio element.
                                </audio>
                            </td>

                            <!-- Delete -->
                            <td class="text-end">
                                <form method="post" action="/prompts/delete" class="inline"
                                      onsubmit="return confirm('Delete \u201c<?= addslashes(htmlspecialchars($prompt['name'])) ?>\u201d?\n\nThis cannot be undone. Campaigns using this prompt will have their audio cleared.')">
                                    <input type="hidden" name="_csrf"      value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="prompt_id" value="<?= (int) $prompt['id'] ?>">
                                    <button type="submit" class="btn btn-sm danger"
                                            title="Delete prompt">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
