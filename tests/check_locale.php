<?php

declare(strict_types=1);

/**
 * Prueft die Uebersetzungs-Vollstaendigkeit der Module dieses Repos:
 *
 *  - Jeder caption/label/suffix-Text des Konfigurationsformulars braucht einen
 *    de-Schluessel in locale.json. Die Formulare werden hier im Code aufgebaut
 *    (GetConfigurationForm statt form.json), daher werden die Literale
 *    'caption' => '...' direkt aus module.php eingesammelt; eine evtl.
 *    vorhandene form.json wird zusaetzlich beruecksichtigt.
 *  - Jeder Translate('...')-Text braucht ebenfalls einen de-Schluessel.
 *  - Verwaiste de-Schluessel werden nur gemeldet, nicht als Fehler gewertet
 *    (dynamische Nutzung wie zusammengesetzte Captions ist moeglich).
 *
 * Exit-Code 1 bei fehlenden Uebersetzungen (fuer die CI), sonst 0.
 * Aufruf: php tests/check_locale.php
 */

$moduleDirs = ['Roborock IO', 'Roborock Robot'];
$root       = dirname(__DIR__);
$fail       = false;

foreach ($moduleDirs as $moduleDir) {
    $dir = $root . '/' . $moduleDir;
    echo "==== $moduleDir ====\n";

    $localeFile = $dir . '/locale.json';
    if (!is_file($localeFile)) {
        echo "FEHLER: keine locale.json vorhanden\n\n";
        $fail = true;
        continue;
    }
    $locale = json_decode(file_get_contents($localeFile), true, 512, JSON_THROW_ON_ERROR);
    $deKeys = array_keys($locale['translations']['de'] ?? []);

    $modulePhp = file_get_contents($dir . '/module.php');

    // 1) caption/label/suffix-Texte einsammeln: Literale aus dem Form-Code in
    //    module.php sowie (falls vorhanden) rekursiv aus form.json
    $formTexts = [];
    collectFormLiterals($modulePhp, $formTexts);
    $formRaw  = '';
    $formFile = $dir . '/form.json';
    if (is_file($formFile)) {
        $formRaw = file_get_contents($formFile);
        $form    = json_decode($formRaw, true, 512, JSON_THROW_ON_ERROR);
        collectFormTexts($form, '', $formTexts);
    }

    // 2) Translate-Aufrufe aus module.php und aus den Skripten in form.json (z. B. onClick)
    $translateTexts = collectTranslateTexts($modulePhp . "\n" . $formRaw);

    // Fehlend: Formular-Text ohne de-Schluessel
    $missingForm = [];
    foreach ($formTexts as $text => $paths) {
        if (!in_array($text, $deKeys, true)) {
            $missingForm[$text] = $paths[0];
        }
    }

    // Fehlend: Translate-Text ohne de-Schluessel
    $missingPhp = [];
    foreach (array_keys($translateTexts) as $text) {
        if (!in_array($text, $deKeys, true)) {
            $missingPhp[] = $text;
        }
    }

    // Verwaist: de-Schluessel weder Formular-Text noch Translate-Text
    $orphans = [];
    foreach ($deKeys as $key) {
        if (!isset($formTexts[$key]) && !isset($translateTexts[$key])) {
            $inLiteral = str_contains($modulePhp, $key) || ($formRaw !== '' && str_contains($formRaw, $key));
            $orphans[] = [$key, $inLiteral ? 'kommt woertlich in module.php/form.json vor' : 'nirgends gefunden'];
        }
    }

    echo 'Formular-Texte (unique):  ' . count($formTexts) . "\n";
    echo 'locale de-Schluessel:     ' . count($deKeys) . "\n";
    echo 'Translate-Texte:          ' . count($translateTexts) . "\n";
    echo 'Sprachen in locale.json:  ' . implode(', ', array_keys($locale['translations'] ?? [])) . "\n\n";

    echo 'FEHLENDE UEBERSETZUNGEN (Formular -> kein de-Schluessel): ' . count($missingForm) . "\n";
    foreach ($missingForm as $text => $path) {
        echo "  - \"$text\"  ($path)\n";
    }
    echo 'FEHLENDE UEBERSETZUNGEN (Translate -> kein de-Schluessel): ' . count($missingPhp) . "\n";
    foreach ($missingPhp as $text) {
        echo "  - \"$text\"\n";
    }
    echo 'VERWAISTE de-SCHLUESSEL (nur Hinweis, kein Fehler): ' . count($orphans) . "\n";
    foreach ($orphans as [$key, $note]) {
        echo "  - \"$key\"  [$note]\n";
    }
    echo "\n";

    if ($missingForm !== [] || $missingPhp !== []) {
        $fail = true;
    }
}

if ($fail) {
    echo "FEHLER: Es fehlen Uebersetzungen (siehe oben).\n";
    exit(1);
}

echo "OK: Alle Texte sind uebersetzt.\n";

/**
 * Sammelt 'caption' => '...' (sowie label/suffix) String-Literale aus dem
 * PHP-Code des im Code aufgebauten Konfigurationsformulars.
 */
function collectFormLiterals(string $code, array &$formTexts): void
{
    foreach (
        [
            '/\'(caption|label|suffix)\'\s*=>\s*\'((?:[^\'\\\\]|\\\\.)+)\'/s',
            '/\'(caption|label|suffix)\'\s*=>\s*"((?:[^"\\\\]|\\\\.)+)"/s'
        ] as $pattern
    ) {
        if (preg_match_all($pattern, $code, $matches)) {
            foreach ($matches[2] as $i => $text) {
                $formTexts[stripcslashes($text)][] = 'module.php:' . $matches[1][$i];
            }
        }
    }
}

function collectFormTexts(array $node, string $path, array &$formTexts): void
{
    foreach ($node as $k => $v) {
        $p = $path . (is_int($k) ? '[' . $k . ']' : '.' . $k);
        if (in_array($k, ['caption', 'label', 'suffix'], true) && is_string($v) && $v !== '') {
            $formTexts[$v][] = $p;
        }
        if (is_array($v)) {
            collectFormTexts($v, $p, $formTexts);
        }
    }
}

/**
 * Sammelt die Argumente aller Translate('...')-/Translate("...")-Aufrufe im uebergebenen Quelltext.
 *
 * @return array<string, true> Texte als Schluessel (dedupliziert)
 */
function collectTranslateTexts(string $code): array
{
    $texts = [];
    foreach (
        [
            '/->Translate\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/s',
            '/->Translate\(\s*"((?:[^"\\\\]|\\\\.)*)"/s'
        ] as $pattern
    ) {
        if (preg_match_all($pattern, $code, $matches)) {
            foreach ($matches[1] as $text) {
                $texts[stripcslashes($text)] = true;
            }
        }
    }

    return $texts;
}
