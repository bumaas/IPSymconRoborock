<?php

declare(strict_types=1);

/*
 * Klartext neben den HTML-Tabellen, Altlast gekennzeichnet (MCP-Regeln 9 und 14).
 *
 * Eine HTML-Variable bläht jede Suchantwort einer KI auf und ist schlecht zu lesen. Neben
 * „Wartung“ und „Reinigungsaufzeichnungen“ führt das Modul deshalb je eine Klartext-Variable
 * (Abstimmung Burkhard 04.10.2026: Klartext zusätzlich, HTML bleibt). Die Variable
 * „Aktuelle Koordinaten“ (Ident coordinates) pflegt das Modul nicht mehr — sie stammt aus dem
 * alten Karten-Upload für gerootete Geräte (am nuc zuletzt geändert 12/2021); sie wird als
 * veraltet gekennzeichnet, löschen muss sie der Anwender.
 *
 * Fixtures: tests/fixtures/get_consumable_a10.json, tests/fixtures/get_clean_record_a10.json —
 * echte Antworten des S6 MaxV (nuc, 04.10.2026, per Roborock_RequestRawData), über ReceiveData
 * eingespielt wie aus der Warteschlange der IO.
 *
 * Aufruf: php tests/check-plain-text.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

function wert(RoborockHarness $m, string $ident): ?string
{
    $id = @IPS_GetObjectIDByIdent($ident, $m->id());
    return $id ? (string)GetValue($id) : null;
}

// Modell aus der echten miIO.info-Antwort (roborock.vacuum.a10): bestimmt die Namen der Verbrauchsteile
$m = neueInstanz(ioAntworten: ['miIO.info' => file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json')]);
IPS_SetProperty($m->id(), 'consumables', true);
IPS_SetProperty($m->id(), 'clean_time', true);
IPS_ApplyChanges($m->id());

echo "Wartung\n";
empfange($m, 'get_consumable', json_decode(file_get_contents(__DIR__ . '/fixtures/get_consumable_a10.json'), true));
$html = (string)wert($m, 'consumables');
$text = wert($m, 'consumables_text');
preg_match_all('/(\d+)%/', $html, $prozentHtml);
pruefe($prozentHtml[1] !== [], 'HTML-Tabelle enthält Prozentwerte (Prüfung greift): ' . implode(', ', $prozentHtml[1]));
pruefe($text !== null, 'Klartext-Variable consumables_text vorhanden');
pruefe($text !== null && !str_contains($text, '<'), 'Klartext ohne HTML: ' . $text);
preg_match_all('/(\d+) %/', (string)$text, $prozentText);
pruefe($prozentText[1] === $prozentHtml[1], 'Klartext nennt dieselben Werte wie die Tabelle');

echo "\nReinigungsaufzeichnungen\n";
foreach (json_decode(file_get_contents(__DIR__ . '/fixtures/get_clean_record_a10.json'), true) as $antwort) {
    empfange($m, 'get_clean_record', $antwort);
}
$text = wert($m, 'cleaning_records_text');
echo preg_replace('/^/m', '        | ', (string)$text), "\n";
pruefe($text !== null, 'Klartext-Variable cleaning_records_text vorhanden');
pruefe($text !== null && !str_contains($text, '<'), 'Klartext ohne HTML');
pruefe(count(explode("\n", (string)$text)) === 3, 'eine Zeile je Reinigung (3)');
pruefe(str_contains((string)$text, '10,1 m²') && str_contains((string)$text, '24,8 m²'), 'Flächen wie in der Tabelle (10,1 m², 24,8 m²)');
pruefe(substr_count((string)$text, 'not completed') === 1, 'eine Reinigung nicht abgeschlossen (die vom 21.09.)');

echo "\nnach dem Update auf 2.4: Klartext aus den gespeicherten Aufzeichnungen\n";
// Code-Review build 103: Die Klartext-Variable kam mit 2.4 neu hinzu und wurde nur bei einer neuen
// Reinigung gefüllt — bis dahin leer, obwohl das Attribut die letzten Reinigungen schon hielt.
// Nachgestellt: Variable leeren (Stand direkt nach dem Update), dann Übernehmen.
SetValue(IPS_GetObjectIDByIdent('cleaning_records_text', $m->id()), '');
IPS_ApplyChanges($m->id());
$nachUpdate = (string)wert($m, 'cleaning_records_text');
pruefe($nachUpdate === $text, 'Klartext nach dem Übernehmen wieder da (3 Reinigungen): ' . json_encode($nachUpdate, JSON_UNESCAPED_UNICODE));

echo "\nAltlast „Aktuelle Koordinaten“\n";
$alt = IPS_CreateVariable(VARIABLETYPE_STRING);
IPS_SetParent($alt, $m->id());
IPS_SetIdent($alt, 'coordinates');
IPS_SetName($alt, 'Current Coordinates');
IPS_ApplyChanges($m->id());
pruefe(IPS_ObjectExists($alt), 'Variable bleibt erhalten (löschen muss der Anwender)');
pruefe(str_ends_with(IPS_GetName($alt), '(obsolete)'), 'Name gekennzeichnet: ' . IPS_GetName($alt));
IPS_ApplyChanges($m->id());
pruefe(substr_count(IPS_GetName($alt), '(obsolete)') === 1, 'Kennzeichnung nur einmal, auch nach erneutem Übernehmen');

ergebnis();
