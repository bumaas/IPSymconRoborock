<?php

declare(strict_types=1);

/*
 * Roborock_RunSelfTest(): Probelauf ohne Wirkung (MCP-Regeln 5 und 15).
 *
 * Eine KI findet den Selbsttest über den einheitlichen Namen <Präfix>_RunSelfTest
 * (IPS_GetFunctionListByModuleID) und darf ihn ohne Rückfrage aufrufen — deshalb muss er
 * wirklich nichts verändern: keinen Status, keine Variable, keinen Timer. Gerade die
 * Erreichbarkeitsprüfung (miIO.info) schreibt sonst über ihren Callback Firmware, SSID, IP …
 * in die Statusvariablen, und ein Sauger, der nicht antwortet, setzte den Status 206.
 *
 * Er liefert Text: je Prüfung eine Zeile mit ✔ (in Ordnung), ✘ (Störung, mit nächstem Schritt)
 * oder – (Hinweis), am Ende das Ergebnis.
 *
 * Fixtures: tests/fixtures/miio_info_a10.json und tests/fixtures/antwort_ok.json
 * (siehe check-status-log.php und check-request-action.php).
 *
 * Aufruf: php tests/check-self-test.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

/** Zustand der Instanz, den der Selbsttest nicht verändern darf */
function zustand(RoborockHarness $m): array
{
    $variablen = [];
    foreach (IPS_GetChildrenIDs($m->id()) as $kind) {
        if (IPS_VariableExists($kind)) {
            $v = IPS_GetVariable($kind);
            $variablen[IPS_GetObject($kind)['ObjectIdent']] = [$v['VariableValue'], $v['VariableUpdated']];
        }
    }
    return [
        'status'    => status($m),
        'variablen' => $variablen,
        'timer'     => [$m->timerIntervall('RoborockTimerUpdate'), $m->timerIntervall('RoborockTimerUpdate_Map')],
    ];
}

$miioInfo = file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json');
$ok       = file_get_contents(__DIR__ . '/fixtures/antwort_ok.json');

$m = neueInstanz(ioAntwort: $ok, ioAntworten: ['miIO.info' => $miioInfo]);
IPS_SetProperty($m->id(), 'extended_info', true); // Firmware, SSID … als Statusvariablen
IPS_ApplyChanges($m->id());
$hat = method_exists($m, 'RunSelfTest');
pruefe($hat, 'Roborock_RunSelfTest() vorhanden');
if (!$hat) {
    ergebnis();
}

echo "\nSauger antwortet\n";
// Variablen sollen sich vom Selbsttest unterscheiden lassen: Werte, die miIO.info überschreiben würde
foreach (['fw_ver', 'ssid', 'local_ip'] as $ident) {
    $vid = @IPS_GetObjectIDByIdent($ident, $m->id());
    if ($vid) {
        SetValue($vid, 'vorher');
    }
}
$vorher = zustand($m);
$text   = $m->RunSelfTest();
echo preg_replace('/^/m', '        | ', $text), "\n";
pruefe(zustand($m) === $vorher, 'nichts verändert (Status, Variablen, Timer)');
pruefe(str_contains($text, '192.168.178.144'), 'nennt die IP-Adresse');
pruefe(str_contains($text, 'roborock.vacuum.a10') && str_contains($text, '3.5.8_6246'), 'nennt Modell und Firmware aus der Antwort des Saugers');
pruefe(!str_contains($text, '✘'), 'keine Störung gemeldet');
pruefe(str_contains($text, 'Xiaomi'), 'Hinweis: ohne Xiaomi-Konto keine Karte und keine Raumnamen');
pruefe(!str_contains($text, '00000000000000000000000000000000'), 'gibt das Token nicht aus');

echo "\nSauger antwortet nicht\n";
$m->ioAntworten = [];
$m->ioAntwort   = 'false';
$vorher = zustand($m);
$text   = $m->RunSelfTest();
echo preg_replace('/^/m', '        | ', $text), "\n";
pruefe(zustand($m) === $vorher, 'nichts verändert — insbesondere kein Status 206: ' . status($m));
pruefe(preg_match('/^✘ .*192\.168\.178\.144/m', $text) === 1, 'Störung mit IP-Adresse gemeldet');

echo "\nInstanz steht noch auf 206, der Sauger antwortet aber wieder\n";
$m206 = neueInstanz();
pruefe(status($m206) === 206, 'Ausgangslage Status 206, ist ' . status($m206));
$m206->ioAntworten = ['miIO.info' => $miioInfo];
$vorher = zustand($m206);
$text   = $m206->RunSelfTest();
pruefe(zustand($m206) === $vorher, 'nichts verändert — der Status bleibt 206 bis zur nächsten Aktualisierung');
pruefe(preg_match('/^– .*60 s/m', $text) === 1, 'Hinweis: Status wird bei der nächsten Aktualisierung (spätestens 60 s) zurückgesetzt');

echo "\nRoborock IO nicht aktiv\n";
$m->parentAktiv = false;
$m->anfragen    = [];
$vorher = zustand($m);
$text   = $m->RunSelfTest();
pruefe(zustand($m) === $vorher, 'nichts verändert');
pruefe(preg_match('/^✘ .*Roborock IO/m', $text) === 1, 'Störung der I/O-Instanz gemeldet');
pruefe($m->anfragen === [], 'ohne aktive IO keine Anfrage an den Sauger: ' . json_encode($m->anfragen));

echo "\nToken ungültig\n";
$m      = neueInstanz(token: 'zu-kurz');
$vorher = zustand($m);
$m->anfragen = [];
$text   = $m->RunSelfTest();
echo preg_replace('/^/m', '        | ', $text), "\n";
pruefe(zustand($m) === $vorher, 'nichts verändert');
pruefe(preg_match('/^✘ .*Roborock_SetDeviceToken/m', $text) === 1, 'Störung Token mit nächstem Schritt gemeldet');
pruefe($m->anfragen === [], 'ohne gültiges Token keine Anfrage an den Sauger: ' . json_encode($m->anfragen));

ergebnis();
