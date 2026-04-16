<?php use App\Core\Csrf; ?>

<div class="page-head">
    <div>
        <h1>Edit Lead</h1>
        <div class="subtle">
            Lead #<?= (int) $lead['id'] ?>
            · Status <span class="status <?= htmlspecialchars($lead['status']) ?>"><?= htmlspecialchars($lead['status']) ?></span>
        </div>
    </div>
    <a class="button secondary" href="/campaigns/show?id=<?= (int) $lead['campaign_id'] ?>">Back to campaign</a>
</div>

<?php if (!empty($_GET['error'])): ?>
    <div class="error"><?= htmlspecialchars((string) $_GET['error']) ?></div>
<?php endif; ?>

<section class="panel">
    <form method="post" action="/leads/update">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>">
        <input type="hidden" name="campaign_id" value="<?= (int) $lead['campaign_id'] ?>">

        <div class="form-row">
            <label>
                <span>Phone number</span>
                <input name="phone_number" value="<?= htmlspecialchars($lead['phone_number']) ?>" required>
            </label>
            <label>
                <span>First name</span>
                <input name="first_name" value="<?= htmlspecialchars((string) $lead['first_name']) ?>">
            </label>
            <label>
                <span>Last name</span>
                <input name="last_name" value="<?= htmlspecialchars((string) $lead['last_name']) ?>">
            </label>
        </div>

        <div class="actions" style="margin-top: 16px;">
            <button type="submit">Save lead</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Reuse This Lead</h2>
    <p class="subtle">This keeps previous call history, clears the lead lifecycle fields, and sets the lead back to pending.</p>
    <form method="post" action="/leads/reset" onsubmit="return confirm('Reset this lead to pending for reuse?');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="campaign_id" value="<?= (int) $lead['campaign_id'] ?>">
        <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>">
        <button class="secondary">Reset to pending</button>
    </form>
</section>
