<?php

declare(strict_types=1);

/*
 * Kein WebHook, keine Joystick-Fernbedienung (Store-Review Punkte 5, 6 und 8).
 *
 * Bis 2.6 build 113 registrierte die IO den Hook /hook/Roborock. Er nahm bis build 112 einen
 * Karten-Upload gerooteter Sauger an, der Medienobjekte unter eine beliebige Objekt-ID aus $_GET['id']
 * hängte, und bis build 113 Fernsteuerbefehle aus libs/joystick.html (Variable „Fernbedienung“ des
 * Robot). Der Fernsteuerweg nahm Befehle für jede ID ohne Anmeldung an und scheiterte selbst, weil er
 * das Token per IPS_GetProperty las, das ein Attribut ist. Hook und Joystick sind entfernt (Burkhard
 * 02.11.2026); ferngesteuert wird per Skript mit Roborock_Move_Direction.
 *
 * Eine vorhandene Variable „Fernbedienung“ (Ident remote) bleibt mit ihrem Namen stehen, das Formular
 * nennt sie und löscht sie per Knopf nach Rückfrage (wie check-plain-text.php für „Aktuelle Koordinaten“).
 *
 * Aufruf: php tests/check-webhook.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

echo "IO ohne Hook\n";
$io = neueIO();
pruefe(!method_exists($io, 'ProcessHookData') || (new ReflectionMethod($io, 'ProcessHookData'))->getDeclaringClass()->getName() !== 'RoborockIO', 'IO verarbeitet keine Hook-Aufrufe');
$quelle = file_get_contents(dirname(__DIR__) . '/Roborock IO/module.php');
pruefe(preg_match('/(?<!Un)RegisterHook\(/', $quelle) === 0, 'kein RegisterHook (UnregisterHook für Altbestände ist erlaubt)');
foreach (['$_GET', '$_FILES', 'IPS_CreateMedia', 'IPS_SetParent', 'IPS_GetProperty('] as $aufruf) {
    pruefe(!str_contains($quelle, $aufruf), 'kein ' . $aufruf);
}

echo "\nRobot ohne Joystick\n";
pruefe(!file_exists(dirname(__DIR__) . '/libs/joystick.html'), 'libs/joystick.html gibt es nicht mehr');
$m = neueInstanz();
pruefe(!method_exists($m, 'SetJoystickHtml'), 'keine Skriptfunktion Roborock_SetJoystickHtml');
$form = IPS_GetConfigurationForm($m->id());
pruefe(!str_contains($form, '"name":"remote"'), 'kein Formularfeld remote');
pruefe(!str_contains($form, 'SetJoystickHtml'), 'Formular-Hinweise nennen SetJoystickHtml nicht');

echo "\nvorhandene Variable „Fernbedienung“\n";
$alt = IPS_CreateVariable(VARIABLETYPE_STRING);
IPS_SetParent($alt, $m->id());
IPS_SetIdent($alt, 'remote');
IPS_SetName($alt, 'Mein Joystick');
IPS_ApplyChanges($m->id());
pruefe(IPS_ObjectExists($alt) && IPS_GetName($alt) === 'Mein Joystick', 'bleibt mit ihrem Namen stehen: ' . (IPS_ObjectExists($alt) ? IPS_GetName($alt) : 'gelöscht'));
$form = IPS_GetConfigurationForm($m->id());
pruefe(str_contains($form, 'Mein Joystick'), 'Formular nennt sie');
try {
    IPS_RequestAction($m->id(), 'DeleteObsoleteVariables', true);
} catch (Throwable $e) {
    pruefe(false, 'Löschknopf scheitert: ' . $e->getMessage());
}
pruefe(!IPS_ObjectExists($alt), 'Löschknopf entfernt sie');

ergebnis();
