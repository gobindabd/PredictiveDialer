<?php use App\Core\Csrf; ?>

<div class="page-head">
    <div>
        <div class="text-uppercase text-muted fw-bold small mb-2">Telephony</div>
        <h1>Trunks</h1>
        <div class="subtle">Manage SIP carriers and sync them into Asterisk PJSIP.</div>
    </div>
</div>

<?php if (!empty($notice)): ?>
    <div class="notice"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars((string) $notice) ?></div>
<?php endif; ?>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>Add SIP Trunk</h2>
            <p class="section-kicker">Creates the PJSIP endpoint, auth, AOR, and identify rows in the realtime tables.</p>
        </div>
    </div>

    <form method="post" action="/vendors">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

        <div class="row g-3">
            <div class="col-md-6 col-xl-4">
                <label class="form-label">Display name</label>
                <input class="form-control" name="name" required placeholder="Bangladesh SIP Trunk">
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">Realtime endpoint</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-hdd-network"></i></span>
                    <input class="form-control mono" name="trunk_name" value="bdtrunk" required>
                </div>
                <div class="form-text">Used by Asterisk as the PJSIP endpoint name.</div>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">SIP domain</label>
                <input class="form-control mono" name="sip_domain" required placeholder="110.76.128.122">
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">SIP username</label>
                <input class="form-control mono" name="sip_username" required>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">SIP password</label>
                <input class="form-control" name="sip_password" type="password" required>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label">Inbound context</label>
                <input class="form-control mono" name="context" value="from-carrier">
            </div>
        </div>

        <div class="soft-card mt-4">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Transport</label>
                    <input class="form-control mono" name="transport" value="transport-udp">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Codecs</label>
                    <input class="form-control mono" name="codecs" value="alaw,ulaw">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">From user</label>
                    <input class="form-control mono" name="from_user" placeholder="defaults to username">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">From domain</label>
                    <input class="form-control mono" name="from_domain" placeholder="defaults to domain">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Dial prefix</label>
                    <input class="form-control" name="dial_prefix" placeholder="optional">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Max calls</label>
                    <input class="form-control" name="max_concurrent_calls" type="number" value="10" min="1">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">CPS limit</label>
                    <input class="form-control" name="cps_limit" type="number" value="1" min="0.1" step="0.1">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label">Priority</label>
                    <input class="form-control" name="priority" type="number" value="100">
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between gap-3 mt-4 flex-wrap">
            <label class="form-check m-0">
                <input class="form-check-input" type="checkbox" name="is_active" checked>
                <span class="form-check-label fw-bold">Active trunk</span>
            </label>
            <button class="btn btn-primary px-4" type="submit">
                <i class="bi bi-plus-circle me-2"></i>Add trunk
            </button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>Configured Trunks</h2>
            <p class="section-kicker">Saved carriers are available to campaigns immediately.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Endpoint</th>
                    <th>Domain</th>
                    <th>Username</th>
                    <th>Limits</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $vendor): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($vendor['name']) ?></div>
                            <div class="small text-muted">Priority <?= (int) $vendor['priority'] ?></div>
                        </td>
                        <td><span class="badge text-bg-light border mono"><?= htmlspecialchars($vendor['trunk_name']) ?></span></td>
                        <td class="mono"><?= htmlspecialchars((string) $vendor['sip_domain']) ?></td>
                        <td class="mono"><?= htmlspecialchars((string) $vendor['sip_username']) ?></td>
                        <td>
                            <div><?= (int) $vendor['max_concurrent_calls'] ?> max calls</div>
                            <div class="small text-muted"><?= htmlspecialchars((string) $vendor['cps_limit']) ?> CPS</div>
                        </td>
                        <td><span class="status <?= $vendor['is_active'] ? 'running' : 'failed' ?>"><?= $vendor['is_active'] ? 'active' : 'inactive' ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="/vendors/edit?id=<?= (int) $vendor['id'] ?>">
                                <i class="bi bi-pencil-square me-1"></i>Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$vendors): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No trunks configured yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
