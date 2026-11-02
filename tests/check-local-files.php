<?php

declare(strict_types=1);

/*
 * Keine Aktion liest eine Datei, deren Pfad der Aufrufer bestimmt (Store-Review Punkt 1).
 *
 * Bis 2.5 build 110 nahm RequestAction den Ident "LoadMapFile" an, las den übergebenen Wert als
 * Dateipfad (file_exists, file_get_contents, gzdecode) und zeichnete den Inhalt als Karte. Jeder Pfad
 * auf dem System war zulässig, auch über IPS_RequestAction aus einem Skript oder über den MCP-Server.
 * Der einzige Aufrufer im Modul war auskommentiert, es war ein Entwicklungsrest.
 *
 * Fixture: tests/fixtures/karte_a10_nuc_2026-10-03.gz — echte Karte des S6 MaxV am nuc
 * (siehe check-map-blocktypes.php). Damit hätte der alte Zweig eine lesbare Datei vorgefunden.
 *
 * Aufruf: php tests/check-local-files.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

/** RequestAction ausführen; liefert die Meldung eines trigger_error oder '' */
function aktion(RoborockHarness $m, string $ident, mixed $wert): string
{
    try {
        IPS_RequestAction($m->id(), $ident, $wert);
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    return '';
}

$m = neueInstanz(ioAntwort: file_get_contents(__DIR__ . '/fixtures/antwort_ok.json'),
    ioAntworten: ['miIO.info' => file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json')]);
pruefe(status($m) === 102, 'Instanz aktiv, Status ' . status($m));

echo "\nLoadMapFile ist kein Ident mehr\n";
$karte   = realpath(__DIR__ . '/fixtures/karte_a10_nuc_2026-10-03.gz');
$meldung = aktion($m, 'LoadMapFile', $karte);
pruefe($meldung !== '' && str_contains($meldung, 'LoadMapFile'), 'vorhandene Kartendatei: Ident wird als unbekannt gemeldet: ' . $meldung);

$meldung = aktion($m, 'LoadMapFile', __DIR__ . '/fixtures/gibt_es_nicht.gz');
pruefe($meldung !== '' && str_contains($meldung, 'LoadMapFile'), 'fehlende Datei: Ident wird als unbekannt gemeldet: ' . $meldung);

echo "\nQuelltext\n";
$quelle = file_get_contents(__DIR__ . '/../Roborock Robot/module.php');
pruefe(!str_contains($quelle, 'loadMapFileFromFile'), 'loadMapFileFromFile gibt es nicht mehr');

ergebnis();
