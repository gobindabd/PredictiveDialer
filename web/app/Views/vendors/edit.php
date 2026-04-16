<?php use App\Core\Csrf; ?>

<div class="page-head">
    <div>
        <div class="text-uppercase text-muted fw-bold small mb-2">Telephony</div>
        <h1>Edit Trunk</h1>
        <div class="subtle"><span class="mono"><?= htmlspecialchars($vendor['trunk_name']) ?></span> · PJSIP endpoint</div>
    </div>
    <a class="btn btn-outline-primary" href="/vendors">
        <i class="bi bi-arrow-left me-2"></i>Back to trunks
    </a>
</div>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>Connection Settings</h2>
            <p class="section-kicker">Saving updates the vendor table and syncs PJSIP configuration.</p>
        </div>
        <span class="status <?= $vendor['is_active'] ? 'running' : 'failed' ?>"><?= $vendor['is_active'] ? 'active' : 'inactive' ?></span>
    </div>

    <form method="post" action="/vendors/update">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="vendor_id" value="<?= (int) $vendor['id'] ?>">

        <div class="row g-3">
            <div class="col-md-6 col-xl-4">
                <label class="form-label">Display name</label>
                <input class="form-control" name="name" value="<?= htmlspecialchars($vendor['name']) ?>" required>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">Realtime endpoint</label>
                <input class="form-control mono" name="trunk_name" value="<?= htmlspecialchars($vendor['trunk_name']) ?>" required>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">SIP domain</label>
                <input class="form-control mono" name="sip_domain" value="<?= htmlspecialchars((string) $vendor['sip_domain']) ?>" required>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">SIP username</label>
                <input class="form-control mono" name="sip_username" value="<?= htmlspecialchars((string) $vendor['sip_username']) ?>" required>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">SIP password</label>
                <input class="form-control" name="sip_password" type="password" placeholder="Leave blank to keep current password">
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">Inbound context</label>
                <input class="form-control mono" name="context" value="<?= htmlspecialchars((string) $vendor['context']) ?>">
            </div>
        </div>

        <div class="soft-card mt-4">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Transport</label>
                    <input class="form-control mono" name="transport" value="<?= htmlspecialchars((string) $vendor['transport']) ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Codecs</label>
                    <input class="form-control mono" name="codecs" value="<?= htmlspecialchars((string) $vendor['codecs']) ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">From user</label>
                    <input class="form-control mono" name="from_user" value="<?= htmlspecialchars((string) $vendor['from_user']) ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">From domain</label>
                    <input class="form-control mono" name="from_domain" value="<?= htmlspecialchars((string) $vendor['from_domain']) ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Dial prefix</label>
                    <input class="form-control" name="dial_prefix" value="<?= htmlspecialchars((string) $vendor['dial_prefix']) ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Max calls</label>
                    <input class="form-control" name="max_concurrent_calls" type="number" value="<?= (int) $vendor['max_concurrent_calls'] ?>" min="1">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">CPS limit</label>
                    <input class="form-control" name="cps_limit" type="number" value="<?= htmlspecialchars((string) $vendor['cps_limit']) ?>" min="0.1" step="0.1">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Priority</label>
                    <input class="form-control" name="priority" type="number" value="<?= (int) $vendor['priority'] ?>">
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between gap-3 mt-4 flex-wrap">
            <label class="form-check m-0">
                <input class="form-check-input" type="checkbox" name="is_active" <?= $vendor['is_active'] ? 'checked' : '' ?>>
                <span class="form-check-label fw-bold">Active trunk</span>
            </label>
            <button class="btn btn-primary px-4" type="submit">
                <i class="bi bi-save me-2"></i>Save changes
            </button>
        </div>
    </form>
</section>
