<?php

declare(strict_types=1);

/* Einstieg. Angemeldet geht es direkt zu den Vorlagen, sonst zur Anmeldung.
   Eine eigene Startseite braucht ein Werkzeug nicht. */

require_once __DIR__ . '/../includes/session.php';
requirePlaybookLogin();

header('Location: /templates.php', true, 302);
exit;
