<?php use App\Core\Csrf; ?>

<div class="auth-card">
    <div class="auth-brand">
        <div class="brand-mark">PD</div>
        <div>
            <h1>Predictive Dialer</h1>
            <p>Sign in to manage campaigns, trunks, leads, and live call activity.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

        <div class="mb-3">
            <label class="form-label">Username or email</label>
            <input class="form-control form-control-lg" name="username" autocomplete="username" required autofocus>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <input class="form-control form-control-lg" name="password" type="password" autocomplete="current-password" required>
        </div>

        <button class="btn btn-primary btn-lg w-100" type="submit">Login</button>
    </form>
</div>
