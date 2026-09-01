<?php $activePage = $activePage ?? ''; ?>
<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" aria-label="Navigation öffnen">
    <svg class="nav-toggle-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    <svg class="nav-toggle-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
</button>
<div class="nav-links" id="primary-navigation">
    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="/" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="/templates.php" class="nav-item <?= $activePage === 'templates' ? 'active' : '' ?>">Templates</a>
    <a href="/runs.php" class="nav-item <?= $activePage === 'runs' ? 'active' : '' ?>">Runs</a>
    <a href="/api/auth.php?logout" class="nav-item">Logout</a>
    <?php endif; ?>
</div>
