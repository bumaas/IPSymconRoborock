<?php

declare(strict_types=1);

/*
 * Klartext neben den HTML-Tabellen, Altlast gekennzeichnet (MCP-Regeln 9 und 14).
 *
 * Eine HTML-Variable bläht jede Suchantwort einer KI auf und ist schlecht zu lesen. Neben
 * „Wartung“ und „Reinigungsaufzeichnungen“ führt das Modul deshalb je eine Klartext-Variable
 * (Abstimmung Burkhard 04.10.2026: Klartext zusätzlich, HTML bleibt). Die Variable
 * „Aktuelle Koordinaten“ (Ident coordinates) pflegt das Modul nicht mehr. Sie stammt aus dem
 * alten Karten-Upload für gerootete Geräte (am nuc zuletzt geändert 12/2021). Sie wird
 * im Formular genannt und nur per Knopf mit Rückfrage gelöscht, ihr Name bleibt unangetastet.
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
$m->variableSetzen('cleaning_records_text', '');
IPS_ApplyChanges($m->id());
$nachUpdate = (string)wert($m, 'cleaning_records_text');
pruefe($nachUpdate === $text, 'Klartext nach dem Übernehmen wieder da (3 Reinigungen): ' . json_encode($nachUpdate, JSON_UNESCAPED_UNICODE));

echo "\nAltlast „Aktuelle Koordinaten“\n";
// Store-Review Punkt 11 (02.11.2026): Der Name gehört nach dem Anlegen dem Anwender. Bis 2.5 build 111
// hängte das Modul „(veraltet)“ an. Jetzt nennt das Formular die Variable samt Knopf zum Löschen mit
// Bestätigung, das Log einmal.

/** Formularelemente mit $schluessel === $wert, rekursiv */
function formElemente(RoborockHarness $m, string $schluessel, string $wert): array
{
    $form     = json_decode(IPS_GetConfigurationForm($m->id()), true, 512, JSON_THROW_ON_ERROR);
    $treffer  = [];
    $sammle   = static function (array $knoten) use (&$sammle, &$treffer, $schluessel, $wert): void {
        if (($knoten[$schluessel] ?? null) === $wert) {
            $treffer[] = $knoten;
        }
        foreach ($knoten as $kind) {
            if (is_array($kind)) {
                $sammle($kind);
            }
        }
    };
    $sammle($form);
    return $treffer;
}

$knopfVorher = formElemente($m, 'name', 'DeleteObsoleteVariables');
pruefe($knopfVorher === [] || ($knopfVorher[0]['visible'] ?? true) === false, 'ohne Altlast kein sichtbarer Löschknopf');

$alt = IPS_CreateVariable(VARIABLETYPE_STRING);
IPS_SetParent($alt, $m->id());
IPS_SetIdent($alt, 'coordinates');
IPS_SetName($alt, 'Meine Koordinaten');
$logAb = logAnzahl($m);
IPS_ApplyChanges($m->id());
IPS_ApplyChanges($m->id());
pruefe(IPS_ObjectExists($alt), 'Variable bleibt beim Übernehmen erhalten');
pruefe(IPS_GetName($alt) === 'Meine Koordinaten', 'Name unverändert: ' . IPS_GetName($alt));

$meldungen = array_values(array_filter(logSeit($m, $logAb), static fn(array $e): bool => str_contains($e['Message'], 'Meine Koordinaten')));
pruefe(count($meldungen) === 1, 'genau eine Logmeldung nach zweimal Übernehmen (' . count($meldungen) . '): ' . ($meldungen[0]['Message'] ?? ''));

$hinweis = array_filter(formElemente($m, 'type', 'Label'), static fn(array $l): bool => str_contains((string)($l['caption'] ?? ''), 'Meine Koordinaten'));
pruefe(count($hinweis) === 1, 'Formular nennt die Variable mit ihrem Namen');
$knopf = formElemente($m, 'name', 'DeleteObsoleteVariables');
pruefe(count($knopf) === 1 && ($knopf[0]['visible'] ?? true) !== false, 'Löschknopf sichtbar');
pruefe(($knopf[0]['confirm'] ?? '') !== '', 'Löschknopf fragt nach: ' . ($knopf[0]['confirm'] ?? ''));

// was der Knopf ausführt, so wie die Konsole es nach dem „Ja“ tut
$onClick = (string)($knopf[0]['onClick'] ?? '');
pruefe(preg_match('/^IPS_RequestAction\(\$id, "(\w+)", true\);$/', $onClick, $treffer) === 1, 'Knopf ruft eine Aktion der Instanz: ' . $onClick);
try {
    IPS_RequestAction($m->id(), $treffer[1] ?? 'fehlt', true);
} catch (Throwable $e) {
    pruefe(false, 'Aktion des Knopfs scheitert: ' . $e->getMessage());
}
pruefe(!IPS_ObjectExists($alt), 'nach dem Knopf ist die Variable gelöscht');
$knopf = formElemente($m, 'name', 'DeleteObsoleteVariables');
pruefe($knopf === [] || ($knopf[0]['visible'] ?? true) === false, 'danach kein sichtbarer Löschknopf mehr');

ergebnis();
