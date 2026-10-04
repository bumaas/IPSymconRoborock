<?php

declare(strict_types=1);

/*
 * RequestAction meldet jeden Fehlschlag beim Aufrufer (MCP-Regel 8).
 *
 * RequestAction ist void; ankommen kann beim Aufrufer — Skript, Visualisierung, KI über den
 * MCP-Server — nur ein trigger_error. Bis 2.4 build 95 lief alles still durch: ein unbekannter
 * Ident landete nur im Debug, ein Wert außerhalb der Darstellung ging ungeprüft an den Sauger,
 * und ein Befehl an einen Sauger, der nicht antwortet, meldete nichts.
 *
 * Gerätebefehle aus RequestAction gehen jetzt sofort (immediate) an den Sauger statt in die
 * Warteschlange der IO — nur so ist das Ergebnis bekannt. Aus Skripten war das schon so:
 * am nuc gemessen (04.10.2026, SENDER "RunScript"): "immediate":true. Die Visualisierung ruft
 * laut Doku mit SENDER "Action"/"WebFront" auf, das landete bisher in der Warteschlange.
 *
 * Fixtures: tests/fixtures/miio_info_a10.json (siehe check-status-log.php) und
 * tests/fixtures/antwort_ok.json — echte Antwort der IO auf change_sound_volume (nuc, 04.10.2026).
 *
 * Aufruf: php tests/check-request-action.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

/** RequestAction ausführen; liefert die Meldung eines trigger_error oder '' */
function aktion(RoborockHarness $m, string $ident, mixed $wert): string
{
    try {
        IPS_RequestAction($m->id(), $ident, $wert);
    } catch (Throwable $e) { // auch TypeError: kommt beim Aufrufer an, ist aber keine brauchbare Meldung
        return $e->getMessage();
    }
    return '';
}

$ok = file_get_contents(__DIR__ . '/fixtures/antwort_ok.json');

$m = neueInstanz(ioAntwort: $ok, ioAntworten: ['miIO.info' => file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json')]);
IPS_SetProperty($m->id(), 'volume', true);
IPS_ApplyChanges($m->id());
pruefe(status($m) === 102, 'Instanz aktiv, Status ' . status($m));

echo "\nungültige Aufrufe: Fehler, nichts geht an den Sauger\n";
$m->anfragen = [];
$meldung = aktion($m, 'gibt_es_nicht', 1);
pruefe($meldung !== '' && str_contains($meldung, 'gibt_es_nicht'), 'unbekannter Ident wird gemeldet: ' . $meldung);

$meldung = aktion($m, 'command', 9);
pruefe($meldung !== '' && str_contains($meldung, '9') && str_contains($meldung, '5'), 'Befehl 9 wird mit den erlaubten Werten (0 … 5) abgelehnt: ' . $meldung);

$meldung = aktion($m, 'volume', 150);
pruefe($meldung !== '' && str_contains($meldung, '150') && str_contains($meldung, '100'), 'Lautstärke 150 wird mit dem Bereich (0 … 100) abgelehnt: ' . $meldung);

$meldung = aktion($m, 'volume', 'laut');
pruefe(str_contains($meldung, 'laut'), 'Lautstärke "laut" (keine Zahl) wird mit dem Wert abgelehnt: ' . $meldung);
pruefe($m->anfragen === [], 'kein ungültiger Wert ging an den Sauger: ' . json_encode($m->anfragen));

echo "\ngültige Aufrufe, Sauger antwortet\n";
$m->anfragen = [];
$m->sofort   = [];
$meldung = aktion($m, 'volume', 50);
pruefe($meldung === '', 'Lautstärke 50: kein Fehler ' . $meldung);
pruefe(($m->anfragen[0] ?? '') === 'change_sound_volume', 'Befehl change_sound_volume gesendet: ' . json_encode($m->anfragen));
pruefe(($m->sofort[0] ?? false) === true, 'Befehl ging sofort an den Sauger (nicht in die Warteschlange)');

$meldung = aktion($m, 'command', 5);
pruefe($meldung === '', 'Befehl 5 (Sauger finden): kein Fehler ' . $meldung);

echo "\nSauger antwortet nicht\n";
$m->ioAntwort = 'false';
$meldung = aktion($m, 'volume', 40);
pruefe($meldung !== '' && str_contains($meldung, '192.168.178.144'), 'Lautstärke ohne Antwort wird gemeldet, mit IP: ' . $meldung);

$meldung = aktion($m, 'command', 2);
pruefe($meldung !== '', 'Stopp ohne Antwort wird gemeldet: ' . $meldung);

echo "\nAktionen ohne Gerätebefehl\n";
$m->anfragen = [];
$meldung = aktion($m, 'ReloadForm', true);
pruefe($meldung === '' && $m->anfragen === [], 'ReloadForm (Formular-Knopf) bleibt ohne Fehler und ohne Befehl: ' . $meldung);

ergebnis();
