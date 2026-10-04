<?php

declare(strict_types=1);

/*
 * Unbekannte Blocktypen in der Karte: eine Warnung je Typ und Instanz, nicht je Abruf.
 *
 * Anlass (03.10.2026, Forum t/46511/853, mike256): Nach einem Firmware-Update liefert ein Qrevo
 * den Blocktyp 34. Der Parser meldete ihn bei JEDEM Kartenabruf als Warnung — während einer
 * Reinigung holt der Map-Timer die Karte alle 10 s, das Log lief voll („The blocktype 34 is not
 * yet supported" alle 16 s).
 *
 * Block 34 ist NONCEDATA (Liste aus {type, unixTime}, reine Metadaten; Nummerierung nach
 * copystring/ioBroker.roborock, src/lib/map/v1/MapParser.ts) und wird jetzt als bekannt
 * übergangen. Weil weitere neue Typen folgen werden (dort sind sie bis 60 definiert), meldet das
 * Modul einen unbekannten Typ nur noch beim ersten Auftreten als Warnung, danach im Debug.
 *
 * Fixture: tests/fixtures/karte_a10_nuc_2026-10-03.gz — echte Karte des Roborock S6 MaxV
 * (roborock.vacuum.a10) auf dem nuc, unverändert über „Karte herunterladen (Rohdaten)" gezogen.
 * Eine echte Karte mit Block 34 fehlt noch (bei mike256 erbeten).
 *
 * Aufruf: php tests/check-map-blocktypes.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

echo "Parser: echte Karte (S6 MaxV)\n";
$warnungen = [];
$karte     = new RRMapFileParser(
    gzdecode(file_get_contents(__DIR__ . '/fixtures/karte_a10_nuc_2026-10-03.gz')),
    static function (string $m, string $d): void {},
    static function (string $m, string $d) use (&$warnungen): void { $warnungen[] = $d; }
);
pruefe($karte->isValid(), 'Karte gültig (SHA1-Digest stimmt)');
pruefe($warnungen === [], 'keine Warnung beim Parsen: ' . json_encode($warnungen));
$hatListe = method_exists($karte, 'getUnknownBlockTypes');
pruefe($hatListe, 'Parser liefert die unbekannten Blocktypen (getUnknownBlockTypes)');
if ($hatListe) {
    pruefe($karte->getUnknownBlockTypes() === [], 'keine unbekannten Blocktypen: ' . json_encode($karte->getUnknownBlockTypes()));
}

echo "\nModul: unbekannter Typ wird je Instanz nur einmal als Warnung gemeldet\n";
$m       = neueInstanz();
$melden  = 'ReportUnknownMapBlockTypes';
$hatMeld = method_exists($m, $melden);
pruefe($hatMeld, 'Modul hat ' . $melden . '()');
if ($hatMeld) {
    $aufruf = static fn(array $typen) => (new ReflectionMethod(Roborock::class, $melden))->invoke($m, $typen);
    $vorher = count(IPS\LogServer::getLogMessages((string)$m->id()));

    $aufruf([99 => ['headerLength' => 12, 'dataLength' => 70]]);
    $aufruf([99 => ['headerLength' => 12, 'dataLength' => 70]]);   // nächster Kartenabruf, 10 s später
    $aufruf([99 => ['headerLength' => 12, 'dataLength' => 74]]);   // gleicher Typ, andere Länge
    $aufruf([99 => ['headerLength' => 12, 'dataLength' => 70], 98 => ['headerLength' => 8, 'dataLength' => 16]]);

    $neu = array_slice(IPS\LogServer::getLogMessages((string)$m->id()), $vorher);
    $warn = array_values(array_filter($neu, static fn(array $l): bool => $l['Type'] === KL_WARNING));
    pruefe(count($warn) === 2, 'genau zwei Warnungen (Typ 99 und Typ 98), sind ' . count($warn) . ': ' . json_encode(array_column($warn, 'Message')));
    pruefe(isset($warn[0]) && str_contains($warn[0]['Message'], '99') && str_contains($warn[0]['Message'], '70'), 'erste Warnung nennt Typ 99 und die Datenlänge');
    pruefe(isset($warn[1]) && str_contains($warn[1]['Message'], '98'), 'zweite Warnung nennt Typ 98');

    $m2 = neueInstanz();
    $vorher2 = count(IPS\LogServer::getLogMessages((string)$m2->id()));
    (new ReflectionMethod(Roborock::class, $melden))->invoke($m2, [99 => ['headerLength' => 12, 'dataLength' => 70]]);
    $warn2 = array_filter(array_slice(IPS\LogServer::getLogMessages((string)$m2->id()), $vorher2), static fn(array $l): bool => $l['Type'] === KL_WARNING);
    pruefe(count($warn2) === 1, 'andere Instanz meldet Typ 99 eigenständig, Warnungen: ' . count($warn2));
}

ergebnis();
