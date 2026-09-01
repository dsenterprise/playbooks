<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars($pageTitle ?? 'Playbooks', ENT_QUOTES, 'UTF-8') ?> · NEX Framework</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/runs.css">
    <link rel="stylesheet" href="/assets/css/navigation.css">
    <link rel="stylesheet" href="/assets/css/versioning.css">
    <script src="/assets/js/app.js"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Zum Inhalt springen</a>
<header class="app-header">
    <div class="container header-inner">
        <a class="brand" href="/" aria-label="Playbooks Startseite">
            <img src="/assets/img/logo.png" alt="NEX Platforms" class="brand-logo" width="180" height="40">
            <span>Playbooks</span>
        </a>
        <div class="header-navigation">
            <nav class="main-nav" aria-label="Hauptnavigation"><?php include __DIR__ . '/nav.php'; ?></nav>
            <button class="theme-toggle" type="button" aria-label="Dunkles Farbschema aktivieren" aria-pressed="false" title="Farbschema wechseln">
                <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/></svg>
                <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20.4 15.5A8.5 8.5 0 0 1 8.5 3.6 8.5 8.5 0 1 0 20.4 15.5Z"/></svg>
            </button>
        </div>
    </div>
</header>
<main id="main-content" class="app-main container">
