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
        /* Die eigene Anmeldeseite schickt weiter, das Anmeldefeld in der Seite
           laedt neu. Das Ziel steht am Formular, damit es genau EINEN Handler
           gibt - zwei Handler auf demselben Formular senden zwei Anfragen. */
        const ziel = event.target.dataset.nachAnmeldung || '';
        message.textContent = ziel
            ? 'Anmeldung erfolgreich. Weiterleitung…'
            : 'Anmeldung erfolgreich. Seite wird neu geladen…';
        if (ziel) { window.location.href = ziel; } else { window.location.reload(); }
    } catch (error) {
        message.textContent = error.message;
        button.disabled = false;
    }
});
