<?php

declare(strict_types=1);

/*
 * Entfallene Skriptfunktionen (MCP-Tauglichkeit, Schritt 5a; Abstimmung Burkhard 04.10.2026).
 *
 * Beim Vorbereiten der Funktions-Hinweise für KI-Assistenten zeigten sich defekte Funktionen.
 * Wer seit langem defekt ist und eine passende Statusvariable hat, entfällt (Hausregel: defekte
 * Wrapper dürfen entfernt werden, wenn die Fähigkeit über Statusvariablen erreichbar bleibt):
 *   - Get_Fan_Power, Get_Water_Quantity_Control: seit 2.2 #59 (03/2025) bei jedem Skriptaufruf
 *     Fatal (Rückgabetyp); Ersatz: Variablen „Saugleistung“/„Wassermenge“, Schalten per RequestAction
 *   - SetSoundLevel: seit RC1 (2018) ein Lesebefehl (get_current_sound); Ersatz: „Lautstärke“
 *   - Timer-Gruppe Set_Timer, EnableTimer, DisableTimer, DeleteTimer, Get_Timer_Details samt
 *     Option „Timer Details“: DeleteTimer seit RC1 ohne Timer-ID, Get_Timer_Details gab den
 *     internen Auftrag samt Geräte-Token zurück, die Variable wird seit 2.2 #65 (02/2026) nie
 *     befüllt. Zeitpläne richtet man in der Xiaomi-App ein.
 *   - GetTimezone: aus Skripten seit 2.2 #59 Fatal; bleibt intern für die Variable „Zeitzone“.
 *
 * Fixture: keine — geprüft werden die öffentliche Schnittstelle und das Aufräumen beim Übernehmen.
 *
 * Aufruf: php tests/check-removed-functions.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

echo "nicht mehr öffentlich\n";
foreach (['Get_Fan_Power', 'Get_Water_Quantity_Control', 'SetSoundLevel', 'Set_Timer', 'EnableTimer', 'DisableTimer', 'DeleteTimer', 'Get_Timer_Details', 'GetTimezone'] as $funktion) {
    $oeffentlich = method_exists(Roborock::class, $funktion) && (new ReflectionMethod(Roborock::class, $funktion))->isPublic();
    pruefe(!$oeffentlich, 'Roborock_' . $funktion . ' ist keine Skriptfunktion mehr');
}

echo "\nbestehende Instanz mit Variable „Timer Details“\n";
$m = neueInstanz();
$alt = IPS_CreateVariable(VARIABLETYPE_STRING);
IPS_SetParent($alt, $m->id());
IPS_SetIdent($alt, 'timer_details');
IPS_ApplyChanges($m->id());
pruefe(@IPS_GetObjectIDByIdent('timer_details', $m->id()) === false, 'Variable „Timer Details“ wird beim Übernehmen entfernt');

echo "\nZeitzone wird intern weiter befüllt\n";
$m = neueInstanz(ioAntworten: ['miIO.info' => file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json')]);
IPS_SetProperty($m->id(), 'timezone', true);
IPS_ApplyChanges($m->id());
$m->anfragen = [];
$m->Update();
pruefe(in_array('get_timezone', $m->anfragen, true), 'Update fragt die Zeitzone ab: ' . json_encode($m->anfragen));
pruefe(!in_array('get_timer', $m->anfragen, true), 'Update fragt keine Timer mehr ab');

ergebnis();
