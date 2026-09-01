<?php

declare(strict_types=1);

function startPlaybookSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_name('playbooks_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function requirePlaybookLogin(): void
{
    startPlaybookSession();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php', true, 302);
        exit;
    }
}
