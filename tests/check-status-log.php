<?php

declare(strict_types=1);

/*
 * Statuswechsel stehen als Klartext im Log (MCP-Regeln 3, 4, 16).
 *
 * Eine KI sieht über den MCP-Server vom Instanzstatus nur die Zahl; was 206 bedeutet und was zu tun
 * ist, erfährt sie aus dem Log. Deshalb:
 *   - beim Wechsel in einen Fehlerstatus genau eine Warnung mit Art und nächstem Schritt
 *     (Sauger antwortet nicht → wird automatisch erneut versucht; Konfiguration falsch → beheben),
 *   - keine Wiederholung, solange der Status gleich bleibt (206 wird bei jeder Aktualisierung neu
 *     geprüft — eine Warnung je Versuch wäre Log-Rauschen),
 *   - bei der Rückkehr auf „aktiv" eine Meldung.
 *
 * Fixture: tests/fixtures/miio_info_a10.json — echte Antwort der Roborock IO auf miIO.info
 * (S6 MaxV am nuc, 04.10.2026, per IPS_EnableDebugFile mitgeschnitten); Token, MAC, SSID und
 * BSSID neutralisiert.
 *
 * Aufruf: php tests/check-status-log.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

$warnungen = static fn(array $log): array => array_values(array_column(array_filter($log, static fn(array $l): bool => $l['Type'] === KL_WARNING), 'Message'));
$meldungen = static fn(array $log): array => array_values(array_column(array_filter($log, static fn(array $l): bool => $l['Type'] === KL_MESSAGE), 'Message'));

echo "Sauger antwortet beim Übernehmen nicht (206)\n";
IPS\LogServer::reset();
$m = neueInstanz();
$w = $warnungen(logSeit($m, 0));
pruefe(status($m) === 206, 'Status 206, ist ' . status($m));
pruefe(count($w) === 1, 'genau eine Warnung, sind ' . count($w) . ': ' . json_encode($w, JSON_UNESCAPED_UNICODE));
pruefe(isset($w[0]) && str_contains($w[0], '192.168.178.144'), 'Warnung nennt die IP-Adresse');
pruefe(isset($w[0]) && str_contains($w[0], '60'), 'Warnung nennt den Takt des erneuten Versuchs (60 s)');

echo "\nnächste Aktualisierung, Sauger antwortet weiterhin nicht\n";
$ab = logAnzahl($m);
$m->Update();
pruefe($warnungen(logSeit($m, $ab)) === [], 'keine weitere Warnung bei gleichem Status');

echo "\nSauger antwortet wieder\n";
$m->ioAntworten['miIO.info'] = file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json');
$ab = logAnzahl($m);
$m->Update();
$neu = logSeit($m, $ab);
pruefe(status($m) === 102, 'Status 102, ist ' . status($m));
pruefe($warnungen($neu) === [], 'keine Warnung bei der Erholung: ' . json_encode($warnungen($neu), JSON_UNESCAPED_UNICODE));
pruefe(count($meldungen($neu)) === 1, 'genau eine Meldung zur Erholung, sind ' . count($meldungen($neu)) . ': ' . json_encode($meldungen($neu), JSON_UNESCAPED_UNICODE));

echo "\nweitere Aktualisierung, alles in Ordnung\n";
$ab = logAnzahl($m);
$m->Update();
pruefe(logSeit($m, $ab) === [], 'kein Logeintrag im Normalbetrieb');

echo "\nKonfigurationsfehler\n";
$m = neueInstanz(ip: 'keine.ip.invalid');
$w = $warnungen(logSeit($m, 0));
pruefe(count($w) === 1 && str_contains($w[0], 'keine.ip.invalid'), 'ungültige IP: eine Warnung mit dem Wert: ' . json_encode($w, JSON_UNESCAPED_UNICODE));
$ab = logAnzahl($m);
IPS_ApplyChanges($m->id());
pruefe($warnungen(logSeit($m, $ab)) === [], 'erneutes Übernehmen mit demselben Fehler: keine zweite Warnung');

$m = neueInstanz(token: 'zu-kurz');
$w = $warnungen(logSeit($m, 0));
pruefe(count($w) === 1 && stripos($w[0], 'token') !== false, 'ungültiges Token: eine Warnung, die das Token nennt: ' . json_encode($w, JSON_UNESCAPED_UNICODE));

$m = neueInstanz(token: '');
$w = $warnungen(logSeit($m, 0));
pruefe(count($w) === 1 && str_contains($w[0], 'Xiaomi'), 'fehlende Zugangsdaten: eine Warnung, die das Xiaomi-Konto nennt: ' . json_encode($w, JSON_UNESCAPED_UNICODE));

echo "\nKonfigurationsfehler behoben: keine Meldung „antwortet wieder“\n";
// Code-Review build 103: Die Rückkehr aus 201/203/205 meldete „Der Sauger antwortet wieder“, obwohl er
// nie unerreichbar war — das passt nur nach 206.
$miioInfo = file_get_contents(__DIR__ . '/fixtures/miio_info_a10.json');
$m = neueInstanz(ip: 'keine.ip.invalid', ioAntworten: ['miIO.info' => $miioInfo]);
$ab = logAnzahl($m);
IPS_SetProperty($m->id(), 'ip', '192.168.178.144');
IPS_ApplyChanges($m->id());
$neu = $meldungen(logSeit($m, $ab));
pruefe(status($m) === 102, 'IP korrigiert: Status 102, ist ' . status($m));
pruefe(count($neu) === 1, 'IP korrigiert: genau eine Meldung, sind ' . count($neu) . ': ' . json_encode($neu, JSON_UNESCAPED_UNICODE));
pruefe(!str_contains(implode(' ', $neu), 'responds again'), 'IP korrigiert: nicht „antwortet wieder“: ' . json_encode($neu, JSON_UNESCAPED_UNICODE));

$m = neueInstanz(token: 'zu-kurz', ioAntworten: ['miIO.info' => $miioInfo]);
$ab = logAnzahl($m);
$m->SetDeviceToken('0123456789abcdef0123456789abcdef');
$neu = $meldungen(logSeit($m, $ab));
pruefe(status($m) === 102, 'Token gesetzt: Status 102, ist ' . status($m));
pruefe(!str_contains(implode(' ', $neu), 'responds again'), 'Token gesetzt: nicht „antwortet wieder“: ' . json_encode($neu, JSON_UNESCAPED_UNICODE));

ergebnis();
