<?php use App\Core\Csrf; ?>

<div class="page-head">
    <div>
        <p class="section-kicker">Campaign Setup</p>
        <h1>Create Campaign</h1>
        <p class="subtle mb-0">Choose the trunk, prompt, schedule, and pacing limits before importing leads.</p>
    </div>
    <a class="btn btn-outline-primary" href="/campaigns">
        <i class="bi bi-arrow-left me-1"></i>
        Back to Campaigns
    </a>
</div>

<form method="post" action="/campaigns" class="panel">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="section-title">
        <div>
            <h2>Campaign Details</h2>
            <p class="section-kicker">You can upload leads after the campaign is created.</p>
        </div>
    </div>

    <div class="form-row">
        <label>
            <span>Name</span>
            <input class="form-control" name="name" required maxlength="120" placeholder="Bangladesh April campaign">
        </label>

        <label>
            <span>Scheduled at</span>
            <input class="form-control" name="scheduled_at" type="datetime-local">
        </label>

        <label>
            <span>Vendor</span>
            <select class="form-select" name="vendor_id">
                <option value="">Auto select active trunk</option>
                <?php foreach ($vendors as $vendor): ?>
                    <option value="<?= (int) $vendor['id'] ?>"><?= htmlspecialchars($vendor['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Audio prompt</span>
            <select class="form-select" name="audio_prompt_id">
                <option value="">No prompt</option>
                <?php foreach ($prompts as $prompt): ?>
                    <option value="<?= (int) $prompt['id'] ?>"><?= htmlspecialchars($prompt['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <hr class="my-4">

    <div class="section-title">
        <div>
            <h2>Dialing Rules</h2>
            <p class="section-kicker">Keep limits conservative until the trunk is proven stable.</p>
        </div>
    </div>

    <div class="form-row">
        <label>
            <span>Max concurrent calls</span>
            <input class="form-control" name="max_concurrent_calls" type="number" value="10" min="1" max="500">
        </label>

        <label>
            <span>Target answer rate %</span>
            <input class="form-control" name="target_answer_rate" type="number" value="30" min="1" max="100">
        </label>

        <label>
            <span>Retry limit</span>
            <input class="form-control" name="retry_limit" type="number" value="1" min="0" max="20">
        </label>

        <label>
            <span>Retry delay minutes</span>
            <input class="form-control" name="retry_delay_minutes" type="number" value="60" min="1" max="10080">
        </label>
    </div>

    <div class="actions mt-4">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-check2-circle me-1"></i>
            Create Campaign
        </button>
        <a class="btn btn-outline-primary" href="/campaigns">Cancel</a>
    </div>
</form>
