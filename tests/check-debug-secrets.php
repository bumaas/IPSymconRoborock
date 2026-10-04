<?php

declare(strict_types=1);

/*
 * Keine Zugangsdaten im Debug (MCP-Regeln 10 und 13).
 *
 * Das Debug liest jede KI mit Lesezugriff über den MCP-Server (symcon_debug). Bis 2.4 build 98
 * standen dort im Klartext: das Geräte-Token in jedem Auftrag an den Sauger und in der Antwort auf
 * miIO.info (Robot und IO), beim Cloud-Login die Token ALLER Geräte des Xiaomi-Kontos sowie
 * ssecurity, serviceToken und _sign. Maskiert wird zentral in SendDebug beider Module
 * (libs/DebugMaskTrait.php): Werte unter diesen JSON-Schlüsseln und das bekannte Token selbst
 * werden zu "***" plus den letzten vier Zeichen — genug, um zu sehen, welches Token im Spiel ist.
 * Die Karten-URL bleibt unverändert (signiert, verfällt nach kurzer Zeit, für Anwender zum Testen
 * nützlich — Abstimmung Burkhard 04.10.2026).
 *
 * Fixtures: tests/fixtures/miio_info_a10.json, tests/fixtures/antwort_ok.json.
 *
 * Aufruf: php tests/check-debug-secrets.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

const TOKEN = '0123456789abcdef0123456789abcdef';

/** alle Debug-Meldungen einer Instanz als Text */
function debugText(int $id): string
{
    return implode("\n", array_map(
        static fn(array $d): string => $d['Message'] . ' | ' . $d['Data'],
        array_filter(IPS\DebugServer::getDebugMessages($id), static fn(array $d): bool => $d['Format'] === 0)
    ));
}

echo "Robot: Übernehmen, Befehl, Antwort mit Token\n";
$m = neueInstanz(
    token: TOKEN,
    ioAntwort: file_get_contents(__DIR__ . '/fixtures/antwort_ok.json'),
    ioAntworten: ['miIO.info' => file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json')]
);
IPS_SetProperty($m->id(), 'volume', true);
IPS_ApplyChanges($m->id());
IPS_RequestAction($m->id(), 'volume', 50);
$debug = debugText($m->id());
pruefe(str_contains($debug, 'change_sound_volume'), 'Debug enthält den Auftrag (Prüfung greift)');
pruefe(!str_contains($debug, TOKEN), 'Geräte-Token steht nirgends im Klartext');
pruefe(!str_contains($debug, str_repeat('0', 32)), 'Token aus der miIO.info-Antwort steht nirgends im Klartext');
pruefe(str_contains($debug, '***cdef'), 'maskiertes Token zeigt die letzten vier Zeichen (***cdef)');

echo "\nRobot: Cloud-Login-Daten\n";
$maske = new ReflectionMethod(Roborock::class, 'SendDebug');
$maske->invoke($m, 'GetTokenFromXiaomi', 'loginAccountData: ' . json_encode(['ssecurity' => 'Ab3dEf6hIj9lMn2pQr5t==', 'userId' => 12345, 'location' => 'https://sts.api.io.mi.com/sts?d=x']), 0);
$maske->invoke($m, 'GetTokenFromXiaomi', 'loginLocationData: ' . json_encode(['userId' => 12345, 'serviceToken' => 'Wq8eR7tY6uI5oP4aS3dF2gH1jK0lZ9xC']), 0);
$maske->invoke($m, 'GetTokenFromXiaomi', 'loginData: ' . json_encode(['qs' => '%3Fsid', 'callback' => 'https://sts.api.io.mi.com/sts', '_sign' => 'Zx9Yw8Vu7Ts6=']), 0);
$maske->invoke($m, 'GetTokenFromXiaomi', 'deviceData[3]: ' . json_encode(['did' => '1234', 'name' => 'Luftreiniger', 'token' => 'fedcba9876543210fedcba9876543210']), 0);
$debug = debugText($m->id());
foreach (['Ab3dEf6hIj9lMn2pQr5t==', 'Wq8eR7tY6uI5oP4aS3dF2gH1jK0lZ9xC', 'Zx9Yw8Vu7Ts6=', 'fedcba9876543210fedcba9876543210'] as $geheim) {
    pruefe(!str_contains($debug, $geheim), 'nicht im Klartext: ' . substr($geheim, 0, 6) . '…');
}
pruefe(str_contains($debug, '"userId":12345') && str_contains($debug, 'Luftreiniger'), 'übrige Angaben bleiben lesbar (userId, Gerätename)');

echo "\nRobot: Karten-URL bleibt unverändert\n";
$url = 'https://awsde0.fds.api.xiaomi.com/robomap/roboroommap/123/0?GalaxyAccessKeyId=5551&Expires=1791200000000&Signature=AbCdEfGh123=';
$maske->invoke($m, 'GetMapURL', $url, 0);
pruefe(str_contains(debugText($m->id()), $url), 'Karten-URL vollständig im Debug');

echo "\nIO: Token aus dem weitergereichten Auftrag\n";
$io   = neueIO();
$ioId = $io->id();
$ioMaske   = new ReflectionMethod(RoborockIO::class, 'SendDebug');
$ioMaske->invoke($io, 'forwarded data', json_encode(['InstanceID' => 1, 'token' => TOKEN, 'ip' => '192.168.178.144', 'method' => 'miIO.info']), 0);
$ioMaske->invoke($io, 'socket [result]', file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json'), 0);
$debug = debugText($ioId);
pruefe(str_contains($debug, 'miIO.info'), 'IO-Debug enthält den Auftrag (Prüfung greift)');
pruefe(!str_contains($debug, TOKEN) && !str_contains($debug, str_repeat('0', 32)), 'IO: Token nirgends im Klartext');

ergebnis();
