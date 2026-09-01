<?php if (!isset($_SESSION['user_id'])): ?>
<section id="login-section" class="form-card" aria-labelledby="login-title">
    <p class="eyebrow">Geschützter Bereich</p>
    <h2 id="login-title">Anmelden</h2>
    <p class="muted">Melde dich an, um Templates und Durchführungen zu verwalten.</p>
    <form id="login-form" class="form-grid">
        <label class="field"><span>Benutzername</span><input id="login-username" name="username" autocomplete="username" required></label>
        <label class="field"><span>Passwort</span><input id="login-password" name="password" type="password" autocomplete="current-password" required></label>
        <div class="field-wide"><button class="btn btn-primary" type="submit">Anmelden</button></div>
    </form>
    <p id="login-message" class="muted" role="status" aria-live="polite"></p>
</section>
<script src="assets/js/login.js"></script>
<?php endif; ?>
