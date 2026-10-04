<?php

declare(strict_types=1);

/*
 * Hinweise für Skripte und KI-Assistenten im Formular (MCP-Regel 6).
 *
 * Eine KI sieht über den MCP-Server keine Doku, aber das Konfigurationsformular samt unsichtbarer
 * Elemente (IPS_GetConfigurationForm). Mit einer Modulfunktion reisen nur ihr Name und die
 * Parameternamen (IPS_GetFunction). Deshalb:
 *   - jede öffentliche Skriptfunktion steht in einem unsichtbaren Hinweis (visible: false —
 *     Vorgabe Burkhard: in der Konsole erscheint davon nichts), gruppiert nach Aufgaben;
 *   - kein nichtssagender Parametername ($x, $state, $mode …).
 * Die Inhalte sind an der Anlage geprüft (nuc, 04.10.2026): Rückgabeformate der lesenden
 * Funktionen, Werte der Darstellungen, Koordinaten der Ladestation in der echten Karte.
 *
 * Aufruf: php tests/check-form-hints.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

// Symcon-Rückrufe und Kernel-Methoden — keine Skriptfunktionen im Sinne der Hinweise
const KEINE_SKRIPTFUNKTION = ['__construct', 'Create', 'Destroy', 'ApplyChanges', 'RequestAction', 'ReceiveData', 'MessageSink', 'GetConfigurationForm'];
const NICHTSSAGEND         = ['x', 'y', 'state', 'mode', 'power', 'number', 'part', 'Code', 'time', 'direction', 'roomnumber', 'roomname', 'multizone', 'mapIndex', 'segmentid', 'segmentIds', 'starttime', 'endtime', 'token'];

$m    = neueInstanz();
$form = json_decode(IPS_GetConfigurationForm($m->id()), true, 512, JSON_THROW_ON_ERROR);

$hinweise = [];
$sichtbar = [];
$sammle = static function (array $knoten) use (&$sammle, &$hinweise, &$sichtbar): void {
    if (($knoten['type'] ?? '') === 'Label' && isset($knoten['caption'])) {
        if (($knoten['visible'] ?? true) === false) {
            $hinweise[] = $knoten['caption'];
        } elseif (str_contains($knoten['caption'], 'For scripts and AI assistants')) {
            $sichtbar[] = $knoten['caption'];
        }
    }
    foreach ($knoten as $wert) {
        if (is_array($wert)) {
            $sammle($wert);
        }
    }
};
$sammle($form);
$text = implode("\n", $hinweise);

pruefe(count($hinweise) >= 5, 'mindestens fünf unsichtbare Hinweise im Formular, sind ' . count($hinweise));
pruefe($sichtbar === [], 'kein Hinweis für KI-Assistenten ist in der Konsole sichtbar');

echo "\njede Skriptfunktion steht in einem Hinweis\n";
$klasse = new ReflectionClass(Roborock::class);
foreach ($klasse->getMethods(ReflectionMethod::IS_PUBLIC) as $methode) {
    if ($methode->getDeclaringClass()->getName() !== Roborock::class || in_array($methode->getName(), KEINE_SKRIPTFUNKTION, true)) {
        continue;
    }
    pruefe(preg_match('/\bRoborock_' . preg_quote($methode->getName(), '/') . '\b/', $text) === 1, 'Hinweis nennt Roborock_' . $methode->getName());

    foreach ($methode->getParameters() as $parameter) {
        if (in_array($parameter->getName(), NICHTSSAGEND, true)) {
            pruefe(false, sprintf('Roborock_%s: Parametername $%s sagt nicht, was erwartet wird', $methode->getName(), $parameter->getName()));
        }
    }
}

echo "\nInhalte\n";
pruefe(str_contains($text, 'RequestAction') && str_contains($text, '0 = Start'), 'Bedienung über RequestAction mit den Befehlswerten beschrieben');
pruefe(str_contains($text, 'Roborock_RunSelfTest(') && str_contains($text, 'first'), 'Selbsttest als erster Schritt genannt');

ergebnis();
