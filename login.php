<?php
/**
 * login.php – Dedicated login entry point for the Playbooks project.
 *
 * This page provides a standalone login form without requiring authentication.
 * After successful login, the user is redirected to the dashboard (index.php).
 */

require_once __DIR__ . '/includes/session.php';
startPlaybookSession();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /', true, 302);
    exit;
}

$activePage = 'login';
$pageTitle = 'Anmelden';
include __DIR__ . '/includes/header.php';
?>
<section class="form-card" aria-labelledby="login-title" style="max-width:420px;margin:60px auto;">
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
<script>
// After login from this dedicated page, redirect to dashboard instead of reloading login.php
document.querySelector('#login-form')?.addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.submitter;
    const message = document.querySelector('#login-message');
    button.disabled = true;
    message.textContent = 'Anmeldung wird geprüft…';
    try {
        const response = await fetch('api/auth.php?login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                username: document.querySelector('#login-username').value,
                password: document.querySelector('#login-password').value,
            }),
            credentials: 'include',
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Anmeldung fehlgeschlagen.');
        message.textContent = 'Anmeldung erfolgreich. Weiterleitung…';
        window.location.href = '/';
    } catch (error) {
        message.textContent = error.message;
        button.disabled = false;
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
