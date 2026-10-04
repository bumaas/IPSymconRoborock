<?php

declare(strict_types=1);

/*
 * Rückgaben der Skriptfunktionen (Blindtest 04.10.2026, Projekt „Eigenes“).
 *
 * Eine KI ruft die Roborock_*-Funktionen über den MCP-Server auf und liest ihre Rückgabe. Drei Befunde:
 *   1. Funktionen, die die Antwort des Saugers unverändert zurückgeben (DisableDND, Reset_*, SetDNDTimer,
 *      ZoneClean* …), enthielten das Geräte-Token im Klartext: RequestData() gab array_merge($buffer, $io)
 *      zurück, und der Auftrag an die IO trägt das Token. Die Debug-Maskierung (build 99) deckt diesen Weg
 *      nicht ab (MCP-Regel 13).
 *   3. Roborock_Get_DND_Mode() lieferte nur Start und Ende — ob Nicht stören eingeschaltet ist, stand
 *      nur im Debug.
 *   4. Es gab keinen öffentlichen Lese-Weg für die Lautstärke am Sauger (Get_SoundVolume war privat).
 *
 * Aus Skripten gehen diese Aufrufe sofort an den Sauger (SENDER "RunScript", am nuc gemessen). $_IPS gibt
 * es im CLI-PHP nicht; der Test schaltet deshalb sendImmediately ein, wie RequestAction es tut.
 *
 * Fixtures: tests/fixtures/antwort_ok.json; tests/fixtures/get_dnd_timer_a10.json und
 * tests/fixtures/get_sound_volume_a10.json — echte Antworten des S6 MaxV (nuc, 04.10.2026,
 * Roborock_RequestRawData).
 *
 * Aufruf: php tests/check-return-values.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

const TOKEN = '0123456789abcdef0123456789abcdef';

/** öffentliche Funktion aufrufen, als käme der Aufruf aus einem Skript */
function ausSkript(RoborockHarness $m, string $funktion, mixed ...$argumente): mixed
{
    $sofort = new ReflectionProperty(Roborock::class, 'sendImmediately');
    $sofort->setValue($m, true);
    try {
        return $m->$funktion(...$argumente);
    } finally {
        $sofort->setValue($m, false);
    }
}

$m = neueInstanz(token: TOKEN, ioAntwort: file_get_contents(__DIR__ . '/fixtures/antwort_ok.json'), ioAntworten: [
    'miIO.info'        => file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json'),
    'get_dnd_timer'    => file_get_contents(__DIR__ . '/fixtures/get_dnd_timer_a10.json'),
    'get_sound_volume' => file_get_contents(__DIR__ . '/fixtures/get_sound_volume_a10.json'),
]);
IPS_SetProperty($m->id(), 'dnd_mode', true);
IPS_SetProperty($m->id(), 'volume', true);
IPS_ApplyChanges($m->id());
pruefe(status($m) === 102, 'Instanz aktiv, Status ' . status($m));

echo "\nBefund 1: kein Token in Rückgaben\n";
foreach (['DisableDND' => [], 'Reset_Filter' => [], 'SetDNDTimer' => [22, 0, 8, 0]] as $funktion => $argumente) {
    $rueckgabe = ausSkript($m, $funktion, ...$argumente);
    $text      = json_encode($rueckgabe);
    pruefe(is_array($rueckgabe) && ($rueckgabe['result'] ?? null) === ['ok'], "$funktion: Antwort des Saugers kommt zurück: " . $text);
    pruefe(!str_contains((string)$text, TOKEN) && !array_key_exists('token', (array)$rueckgabe), "$funktion: kein Token in der Rückgabe");
}

echo "\nBefund 3: Get_DND_Mode sagt, ob Nicht stören an ist\n";
$dnd = ausSkript($m, 'Get_DND_Mode');
pruefe(($dnd['start'] ?? '') === '23:00' && ($dnd['end'] ?? '') === '07:00', 'Start und Ende: ' . json_encode($dnd));
pruefe(($dnd['enabled'] ?? null) === true, 'enabled = true: ' . json_encode($dnd));

echo "\nBefund 4: Lautstärke am Sauger lesen\n";
pruefe(method_exists($m, 'Get_SoundVolume') && (new ReflectionMethod($m, 'Get_SoundVolume'))->isPublic(), 'Roborock_Get_SoundVolume() ist öffentlich');
if ((new ReflectionMethod($m, 'Get_SoundVolume'))->isPublic()) {
    $volumen = ausSkript($m, 'Get_SoundVolume');
    pruefe($volumen === 100, 'liefert die Lautstärke des Saugers (100): ' . json_encode($volumen));
}

ergebnis();
