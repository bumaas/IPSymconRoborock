<?php

declare(strict_types=1);

/*
 * Ein gescheiterter Gerätebefehl aus RequestAction hinterlässt keinen falschen Zustand.
 *
 * Drei Befunde aus dem Code-Review von 2.4 build 103:
 *   1. Die Statusvariable wurde vor dem Befehl gesetzt und nach einer Ablehnung oder ohne Antwort
 *      nicht zurückgesetzt — sie zeigte bis zum nächsten Update einen Wert, den der Sauger nicht hat.
 *   2. Jede Antwort mit "error" galt als „vom Sauger abgelehnt", auch die, die die IO selbst so
 *      verpackt, weil die Message-ID nicht passt (Roborock IO, _validateResponse). Dann ist offen,
 *      ob der Befehl ausgeführt wurde — eine Ablehnung zu melden, ist falsch.
 *   3. Nach einem unbeantworteten Befehl liefen die Folgeabfragen (get_status, get_map_v1 samt
 *      Cloud-Abruf) trotzdem sofort weiter, jede mit eigenem Timeout und Wiederholung.
 *
 * Fixtures:
 *   - tests/fixtures/antwort_ok.json — echte Antwort der IO auf change_sound_volume (nuc, 04.10.2026)
 *   - tests/fixtures/antwort_abgelehnt.json — echte Antwort der IO, als der S6 MaxV load_multi_map
 *     mit [99] ablehnte (Roborock_RequestRawData am nuc, 04.10.2026)
 *   - tests/fixtures/antwort_fremde_id.json — die Hülle, die _validateResponse bei falscher
 *     Message-ID um eine Antwort legt (['error' => $result]); darin die echte Antwort aus antwort_ok.json
 *     mit anderer ID. Nicht live mitgeschnitten: der Fall entsteht nur bei überlappenden Anfragen.
 *
 * Aufruf: php tests/check-action-failure.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

function aktion(RoborockHarness $m, string $ident, mixed $wert): string
{
    try {
        IPS_RequestAction($m->id(), $ident, $wert);
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    return '';
}

function wert(RoborockHarness $m, string $ident): mixed
{
    return GetValue(IPS_GetObjectIDByIdent($ident, $m->id()));
}

$ok         = file_get_contents(__DIR__ . '/fixtures/antwort_ok.json');
$abgelehnt  = file_get_contents(__DIR__ . '/fixtures/antwort_abgelehnt.json');
$fremdeId   = file_get_contents(__DIR__ . '/fixtures/antwort_fremde_id.json');

$m = neueInstanz(ioAntwort: $ok, ioAntworten: ['miIO.info' => file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json')]);
IPS_SetProperty($m->id(), 'volume', true);
IPS_ApplyChanges($m->id());
pruefe(status($m) === 102, 'Instanz aktiv, Status ' . status($m));

// Ausgangslage: Sauger bestätigt
$meldung = aktion($m, 'volume', 50);
pruefe($meldung === '' && wert($m, 'volume') === 50, 'Lautstärke 50 bestätigt: ' . $meldung);
$meldung = aktion($m, 'command', 5);
pruefe($meldung === '' && wert($m, 'command') === 5, 'Befehl 5 (Sauger finden) bestätigt: ' . $meldung);

echo "\nBefund 1: Variable bleibt bei Fehlschlag auf dem bestätigten Wert\n";
$m->ioAntwort = $abgelehnt;
$meldung = aktion($m, 'volume', 40);
pruefe(str_contains($meldung, '-10005'), 'Ablehnung wird mit dem Fehlercode des Saugers gemeldet: ' . $meldung);
pruefe(wert($m, 'volume') === 50, 'Lautstärke nach Ablehnung weiter 50: ' . json_encode(wert($m, 'volume')));

$m->ioAntwort = 'false';
$meldung = aktion($m, 'volume', 30);
pruefe($meldung !== '', 'Lautstärke ohne Antwort wird gemeldet: ' . $meldung);
pruefe(wert($m, 'volume') === 50, 'Lautstärke ohne Antwort weiter 50: ' . json_encode(wert($m, 'volume')));

$meldung = aktion($m, 'command', 0);
pruefe($meldung !== '', 'Start ohne Antwort wird gemeldet: ' . $meldung);
pruefe(wert($m, 'command') === 5, 'Befehl ohne Antwort weiter 5: ' . json_encode(wert($m, 'command')));

echo "\nBefund 2: falsche Message-ID ist keine Ablehnung\n";
$m->ioAntwort = $fremdeId;
$meldung = aktion($m, 'command', 0);
pruefe($meldung !== '', 'unklare Antwort wird gemeldet: ' . $meldung);
pruefe(!str_contains($meldung, 'rejected') && !str_contains($meldung, 'abgelehnt'), 'unklare Antwort wird nicht als Ablehnung gemeldet: ' . $meldung);
pruefe(!str_contains($meldung, 'Check the value') && !str_contains($meldung, 'Prüfe den Wert'), 'kein Rat, den Wert zu ändern: ' . $meldung);

echo "\nBefund 3: nach einem gescheiterten Befehl keine Folgeabfragen\n";
foreach (['ohne Antwort' => 'false', 'Ablehnung' => $abgelehnt, 'falsche ID' => $fremdeId] as $fall => $antwort) {
    $m->ioAntwort = $antwort;
    $m->anfragen  = [];
    aktion($m, 'command', 0);
    pruefe($m->anfragen === ['app_start'], "Start ($fall): nur app_start gesendet: " . json_encode($m->anfragen));
    // am nuc gesehen (build 104): der Karten-Timer lief nach einem gescheiterten Start alle 10 s an,
    // bis das nächste Update (5 min) ihn wieder abschaltete
    pruefe($m->timerIntervall('RoborockTimerUpdate_Map') === 0, "Start ($fall): Karten-Timer bleibt aus: " . $m->timerIntervall('RoborockTimerUpdate_Map'));
}

echo "\nGegenprobe: bestätigter Start fragt den Status nach\n";
$m->ioAntwort                 = $ok;
$m->ioAntworten['get_status'] = 'false'; // Status und Karte bleiben aus — geprüft wird nur, dass sie
$m->ioAntworten['get_map_v1'] = 'false'; // nachgefragt werden (eine echte get_status-Antwort gibt es nicht als Fixture)
$m->anfragen                  = [];
$meldung = aktion($m, 'command', 0);
pruefe($meldung === '' && wert($m, 'command') === 0, 'Start bestätigt: ' . $meldung);
pruefe(in_array('get_status', $m->anfragen, true), 'get_status folgt: ' . json_encode($m->anfragen));

ergebnis();
