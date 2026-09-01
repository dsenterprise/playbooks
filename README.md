# Playbooks

Struktur für die Zusammenarbeit mit KI. Ein Playbook gliedert ein wiederkehrendes
Vorhaben in **Phasen**, fragt je Phase die nötigen Angaben ab und verbindet sie mit
den passenden Prompts und vorbereiteten Ergebnisdateien.

Der Gewinn ist nicht der einzelne Prompt. Der Gewinn ist, dass **Eingabe, Prompt und
Ergebnis zusammenbleiben** — und beim nächsten Mal wieder da sind.

## Wofür das gut ist

Der Prompt, mit dem es gut lief, steckt in einem Chat von vor drei Wochen. Die Datei,
die dabei herauskam, liegt im Download-Ordner. Die Angaben, die eingesetzt wurden,
stehen nirgends mehr. Beim nächsten Mal fängt man wieder ungefähr bei dreißig Prozent an.

Ein Playbook hält alles drei an einer Stelle:

- **Vorlage** — ein Vorhaben, in Phasen gegliedert, mit Feldern und Prompts
- **Durchführung** — eine konkrete Ausführung mit ausgefüllten Angaben
- **Ergebnisse** — benannte Dateien statt Textblöcke im Verlauf

## Absichtlich schlicht

- **PHP 8.4**, kein Übersetzungsschritt, keine npm-Abhängigkeiten
- **Keine Datenbank.** Alles liegt als JSON-Datei unter `storage/database/`. Sichern heißt kopieren.
- **Normales CSS und normales JavaScript.** Wer hineinschaut, sieht, was passiert.
- **Keine Aufrufe nach draussen.** Editor und Schriften liegen unter `assets/vendor/`. Im Betrieb spricht die Seite mit keinem fremden Server.

Wenn das Werkzeug selbst zum Projekt wird, ist es kein Werkzeug mehr.

## Installation

Voraussetzung ist PHP 8.4 und ein Webserver, dessen Wurzelverzeichnis auf den
Ordner `public/` zeigt. Alles Übrige liegt bewusst darüber: Ablage, Ergebnisse,
Seitengerüst und Werkzeuge sind dadurch über den Webserver gar nicht erreichbar,
auch bei fehlerhafter Konfiguration.

    git clone <repository> playbooks
    cd playbooks

    # Ablage beschreibbar machen (Benutzer des Webservers)
    chown -R www-data:www-data storage
    chmod 750 storage/database storage/results

    # Ersten Benutzer anlegen — im Quelltext steht bewusst kein Zugang
    php bin/benutzer-anlegen.php admin

Danach die Seite aufrufen und anmelden. Es gibt keine öffentliche Startseite: ohne
Anmeldung führt jeder Aufruf zur Anmeldeseite.

**Mitgeliefert ist ein Start-Playbook:** *Playbook-Template entwerfen* — fünf Phasen, mit
denen aus einem Namen und einem Satz ein vollständiges eigenes Playbook entsteht. Es ist
zugleich das beste Beispiel für den Aufbau: Felder, Prompts mit Variablen und zwei
Ergebnisdateien. Löschen kann man es jederzeit; danach ist die Ablage leer und gehört dir.

### Zum Ausprobieren ohne Webserver

    php -S 127.0.0.1:8080 -t public

### Beispiel für nginx

    server {
        server_name playbooks.example.org;
        root /pfad/zu/playbooks/public;
        index index.php;

        # Versteckte Dateien bleiben draussen. Mehr braucht es nicht: Ablage,
        # Ergebnisse, Seitengeruest und Werkzeuge liegen ueber diesem Verzeichnis
        # und sind von hier aus ohnehin unerreichbar.
        location ~ /[.]                           { deny all; }

        location /   { try_files $uri $uri/ /index.php?$query_string; }
        location ~ [.]php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        }
    }

Für den Betrieb über HTTPS empfehlen sich zusätzlich
`Strict-Transport-Security`, `X-Content-Type-Options: nosniff`,
`X-Frame-Options: SAMEORIGIN` und eine `Referrer-Policy`.

## Sicherheit

- **Kein Zugang im Quelltext.** Fehlt `storage/database/users.json`, ist keine Anmeldung
  möglich. Passwörter werden als bcrypt-Hash abgelegt.
- **Bremse gegen Durchprobieren.** Fehlversuche werden je Absender gezählt: ab dem
  dritten wird verzögert, ab dem zehnten für 15 Minuten abgewiesen. Der Absender wird
  nur als Hash gespeichert — es liegen keine Adressen in der Ablage.
- **Sitzung.** Cookie mit `Secure`, `HttpOnly` und `SameSite=Strict`, strikter Modus,
  neue Sitzungskennung nach der Anmeldung, CSRF-Token für schreibende Zugriffe.
- **Die Schnittstelle verlangt eine Sitzung.** Ohne Anmeldung antwortet sie mit 401.

- **Nichts Schützenswertes im Wurzelverzeichnis.** Nur `public/` wird ausgeliefert.
  Ablage, Ergebnisse, Seitengerüst und Werkzeuge liegen darüber und sind über den
  Webserver nicht erreichbar — ohne dass eine Regel sie sperren müsste.
- **Erzeugte Dateien tragen `.md` oder `.json`.** Die Endung wird beim Speichern an
  den Typ gebunden und beim Schreiben ein zweites Mal geprüft.

`storage/` enthält die Inhalte der laufenden Installation und gehört nicht ins
Repository — die mitgelieferte `.gitignore` hält es draußen.

## Aufbau

    public/          das Wurzelverzeichnis des Webservers
      index.php        Einstieg (leitet auf die Vorlagen bzw. die Anmeldung)
      login.php        Anmeldung
      templates.php    Liste der Vorlagen
      template.php     Vorlage bearbeiten: Basis, Phasen, Dateien
      runs.php         Liste der Durchführungen
      run.php          Eine Durchführung ausfüllen und Ergebnisse erzeugen
      categories.php   Kategorien
      api/             Schnittstelle (JSON)
      assets/          CSS, JavaScript, Editor, Schriften

    includes/        Seitengerüst, Navigation, Sitzung und die internen
                     Bausteine der Schnittstelle (Ablage, Wächter, Versionen)
    storage/
      database/      JSON-Ablage (nur der Startbestand ist im Repository)
      results/       erzeugte Dateien, ein Ordner je Durchführung
    bin/             Werkzeuge für die Kommandozeile

## Herkunft

Die Playbooks sind aus einem größeren, selbst betriebenen System entstanden, in dem
Vorlagen die Arbeit von KI-Agenten steuern. Diese Fassung ist die eigenständige,
kleine Variante davon: für Arbeit, die man selbst macht, und für alle, die kein
Agentensystem betreiben wollen.

Entwickelt wurde sie mit einem lokal betriebenen Sprachmodell auf eigener Hardware.

## Lizenz

MIT — siehe `LICENSE`.

Mitgeliefert wird **CodeMirror 5** unter `assets/vendor/codemirror/` — ebenfalls MIT,
Copyright Marijn Haverbeke und andere. Der Lizenztext liegt daneben.

Die Schriften **DM Sans** und **Manrope** unter `assets/vendor/fonts/` stehen unter der
SIL Open Font License 1.1. Auch dort liegt der Lizenztext jeweils daneben als `OFL.txt`.
