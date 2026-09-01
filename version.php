<?php
/**
 * Einzige Quelle der aktuellen Versionsangabe.
 *
 * Vorher stand die Nummer zweimal fest im Quelltext: in `index.php` als
 * „Version 0.6" und in `about.php` als oberster Eintrag der Liste („v0.7").
 * Beide sind auseinandergelaufen — die Startseite zeigte eine Version, die
 * es nicht mehr gab. Genau das kann eine doppelte Angabe, und zwar immer.
 *
 * Beim Anheben einer Version wird NUR diese Datei geändert. Der beschreibende
 * Satz zur Version gehört weiterhin in die Liste in `about.php` — das ist
 * redaktioneller Text, keine Angabe, die sich ableiten liesse.
 */

return [
    'version'    => '0.7',
    'build_date' => '02. August 2026',
    // Maschinenlesbar für das <time datetime="…">-Attribut.
    'build_iso'  => '2026-08-02',
];
