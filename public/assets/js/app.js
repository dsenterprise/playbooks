const playbooksTheme = (() => {
    const storageKey = 'playbooks-theme';
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const storedTheme = () => {
        try { return localStorage.getItem(storageKey); } catch { return null; }
    };
    const apply = dark => {
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.classList.toggle('light', !dark);
    };
    const initial = storedTheme();
    apply(initial ? initial === 'dark' : media.matches);
    return {storageKey, media, storedTheme, apply};
})();

window.Playbooks = (() => {
    const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    /* Wird bei jedem Aufruf frisch gelesen: nach einer neuen Anmeldung steht ein
       anderes Merkmal in der Seite. */
    const csrfMerkmal = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    async function request(url, options = {}) {
        const schreibend = (options.method || 'GET').toUpperCase() !== 'GET';
        const merkmal = schreibend ? csrfMerkmal() : '';
        const settings = {...options, headers:{...(options.body ? {'Content-Type':'application/json'} : {}), ...(merkmal ? {'X-CSRF-Token': merkmal} : {}), ...(options.headers || {})}};
        const response = await fetch(url, settings);
        let data;
        try { data = await response.json(); } catch { throw new Error('Der Server hat keine gültige Antwort geliefert.'); }
        if (!response.ok || !data.ok) throw new Error(data.error || 'Die Anfrage ist fehlgeschlagen.');
        return data;
    }
    function confirm(message, title = 'Änderung bestätigen') {
        const dialog = document.querySelector('#confirmDialog');
        document.querySelector('#confirmTitle').textContent = title;
        document.querySelector('#confirmMessage').textContent = message;
        dialog.showModal();
        return new Promise(resolve => dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), {once:true}));
    }
    function toast(message, type = 'success') {
        const region = document.querySelector('#toastRegion');
        const item = document.createElement('div'); item.className = `toast ${type}`; item.textContent = message; region.append(item);
        setTimeout(() => item.remove(), 3600);
    }
    function icon(name) {
        const paths = {copy:'<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',trash:'<path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6"/>',chevron:'<path d="m9 18 6-6-6-6"/>',arrows:'<path d="m8 3-4 4 4 4M4 7h16M16 21l4-4-4-4M20 17H4"/>'};
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">${paths[name] || ''}</svg>`;
    }
    return {escape, request, confirm, toast, icon};
})();

document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.querySelector('.theme-toggle');
    const syncThemeToggle = () => {
        if (!themeToggle) return;
        const dark = document.documentElement.classList.contains('dark');
        themeToggle.setAttribute('aria-pressed', String(dark));
        themeToggle.setAttribute('aria-label', dark ? 'Helles Farbschema aktivieren' : 'Dunkles Farbschema aktivieren');
    };
    themeToggle?.addEventListener('click', () => {
        const dark = !document.documentElement.classList.contains('dark');
        playbooksTheme.apply(dark);
        try { localStorage.setItem(playbooksTheme.storageKey, dark ? 'dark' : 'light'); } catch {}
        syncThemeToggle();
    });
    playbooksTheme.media.addEventListener('change', event => {
        if (!playbooksTheme.storedTheme()) {
            playbooksTheme.apply(event.matches);
            syncThemeToggle();
        }
    });
    syncThemeToggle();

    const nav = document.querySelector('.main-nav');
    const toggle = nav?.querySelector('.nav-toggle');
    if (!nav || !toggle) return;
    const closeMenu = () => {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Navigation öffnen');
    };
    toggle.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Navigation schließen' : 'Navigation öffnen');
    });
    nav.querySelectorAll('.nav-item').forEach(link => link.addEventListener('click', closeMenu));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && nav.classList.contains('open')) {
            closeMenu();
            toggle.focus();
        }
    });
    window.matchMedia('(min-width: 769px)').addEventListener('change', event => { if (event.matches) closeMenu(); });
});
