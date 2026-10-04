<?php

declare(strict_types=1);

/*
 * Status 206 („No Roborock found") ist vorübergehend — der Update-Timer muss weiterlaufen.
 *
 * Anlass (04.10.2026, nuc, Instanz 40962): Beim Neuladen des Moduls fragt ApplyChanges() über
 * ValidateConfiguration() einmal miIO.info ab. Antwortete der Roboter in diesem Moment nicht
 * (WLAN schlief), stand die Instanz auf 206, und SetUpdateInterval() schaltete den Update-Timer
 * auf 0. Damit lief ValidateConfiguration() nie wieder: Die Instanz blieb über Nacht auf 206,
 * obwohl sich der Roboter weiter steuern ließ — erst ein manuelles „Übernehmen" holte sie zurück.
 * Dasselbe droht nach jedem Kernel-Neustart und jedem Modul-Update.
 *
 * Konfigurationsfehler (IP ungültig, Token ungültig, Zugangsdaten fehlen) behebt dagegen kein
 * neuer Versuch — dort bleibt der Timer aus.
 *
 * Aufruf: php tests/check-validation-retry.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

echo "Roboter antwortet beim ApplyChanges nicht\n";
$m = neueInstanz();
pruefe(in_array('miIO.info', $m->anfragen, true), 'ApplyChanges hat miIO.info angefragt');
pruefe(status($m) === 206, 'Status 206 (No Roborock found), ist ' . status($m));
pruefe($m->timerIntervall('RoborockTimerUpdate') === 60_000, 'Update-Timer läuft weiter (60 s), ist ' . $m->timerIntervall('RoborockTimerUpdate'));

echo "\nnächster Update-Zyklus, Roboter antwortet weiterhin nicht\n";
$m->anfragen = [];
$m->Update();
pruefe($m->anfragen === ['miIO.info'], 'Update fragt erneut miIO.info an: ' . json_encode($m->anfragen));
pruefe(status($m) === 206, 'Status bleibt 206, ist ' . status($m));
pruefe($m->timerIntervall('RoborockTimerUpdate') === 60_000, 'Update-Timer läuft weiter, ist ' . $m->timerIntervall('RoborockTimerUpdate'));

echo "\nKonfigurationsfehler: kein neuer Versuch\n";
$m = neueInstanz(ip: 'keine.ip.invalid');
pruefe(status($m) === 203, 'ungültige IP → Status 203, ist ' . status($m));
pruefe($m->timerIntervall('RoborockTimerUpdate') === 0, 'ungültige IP → Update-Timer aus, ist ' . $m->timerIntervall('RoborockTimerUpdate'));
pruefe($m->anfragen === [], 'ungültige IP → keine Anfrage an den Roboter');

$m = neueInstanz(token: 'zu-kurz');
pruefe(status($m) === 205, 'ungültiges Token → Status 205, ist ' . status($m));
pruefe($m->timerIntervall('RoborockTimerUpdate') === 0, 'ungültiges Token → Update-Timer aus, ist ' . $m->timerIntervall('RoborockTimerUpdate'));

$m = neueInstanz(token: '');
pruefe(status($m) === 201, 'kein Token, keine Zugangsdaten → Status 201, ist ' . status($m));
pruefe($m->timerIntervall('RoborockTimerUpdate') === 0, 'keine Zugangsdaten → Update-Timer aus, ist ' . $m->timerIntervall('RoborockTimerUpdate'));
pruefe($m->timerIntervall('RoborockTimerUpdate_Map') === 0, 'keine Zugangsdaten → Karten-Timer aus, ist ' . $m->timerIntervall('RoborockTimerUpdate_Map'));

ergebnis();
