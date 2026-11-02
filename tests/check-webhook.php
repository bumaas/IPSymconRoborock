<?php

declare(strict_types=1);

/*
 * Der WebHook /hook/Roborock legt keine Objekte an (Store-Review Punkte 5 und 6).
 *
 * Bis 2.6 build 112 nahm der Hook der IO einen Karten-Upload gerooteter Sauger an (Skript
 * symcon.mapupload.sh): Bild und Koordinaten als Datei, Ziel-ID aus $_GET['id']. Die IO legte ein
 * Medienobjekt an, benannte es und hängte es unter die übergebene ID, wo immer sie im Objektbaum
 * stand; geprüft wurde nur, dass das Objekt existiert. Ein vorhandenes Medium wurde über den
 * Dateinamen gesucht, verschoben und überschrieben. Der Weg ist entfernt (Burkhard 02.11.2026), am nuc
 * hat ihn seit 12/2021 nichts mehr benutzt.
 *
 * Teil 1 prüft den Quelltext der IO, Teil 2 schickt einen Upload an den Hook. Teil 2 braucht GD (der
 * alte Code zeichnete in das Bild); die CI hat GD, lokal: php -d extension=gd tests/check-webhook.php
 *
 * Fixture für Teil 2: tests/fixtures/karte_a10_nuc_2026-10-03.gz, die echte Karte des S6 MaxV, mit
 * RRMapDraw als PNG gezeichnet. Die Koordinatendatei bleibt leer; ihr Format spielt für die Prüfung
 * keine Rolle, denn der Hook darf den Upload gar nicht mehr verarbeiten.
 *
 * Aufruf: php tests/check-webhook.php (Exit-Code 1 bei Fehler)
 */

require_once __DIR__ . '/harness.php';

echo "Quelltext der IO\n";
$quelle = file_get_contents(dirname(__DIR__) . '/Roborock IO/module.php');
foreach (['$_FILES', 'IPS_CreateMedia', 'IPS_SetParent', 'IPS_SetName', 'IPS_SetMediaFile', 'IPS_SetMediaContent', 'IPS_GetMediaIDByFile'] as $aufruf) {
    pruefe(!str_contains($quelle, $aufruf), 'kein ' . $aufruf);
}

echo "\nUpload an den Hook\n";
if (!extension_loaded('gd')) {
    echo "  --   übersprungen, dem CLI-PHP fehlt GD (die CI prüft es)\n";
    ergebnis();
}

$karte = new RRMapFileParser(
    gzdecode(file_get_contents(__DIR__ . '/fixtures/karte_a10_nuc_2026-10-03.gz')),
    static function (string $m, string $d): void {},
    static function (string $m, string $d): void {}
);
$png = (new RRMapDraw($karte, static function (string $m, string $d): void {}))->getImage(1.0);
pruefe($png !== '', 'Kartenbild gezeichnet (' . strlen($png) . ' Bytes)');

$verzeichnis = sys_get_temp_dir() . '/roborock-webhook-' . getmypid();
@mkdir($verzeichnis);
$bild        = $verzeichnis . '/upload_image';
$koordinaten = $verzeichnis . '/upload_coordinates';
file_put_contents($bild, $png);
file_put_contents($koordinaten, '');

$io     = neueIO();
$fremd  = IPS_CreateCategory(); // irgendein Objekt im Baum, nicht die Instanz
$medien = IPS_GetMediaList();

$_GET   = ['id' => (string)$fremd];
$_FILES = [
    'image'       => ['name' => 'latest.png', 'tmp_name' => $bild],
    'coordinates' => ['name' => 'coordinates', 'tmp_name' => $koordinaten],
];
ob_start();
try {
    (new ReflectionMethod($io, 'ProcessHookData'))->invoke($io);
} catch (Throwable $e) {
    echo 'Ausnahme: ' . $e->getMessage();
}
$antwort = (string)ob_get_clean();

$neu = array_values(array_diff(IPS_GetMediaList(), $medien));
pruefe($neu === [], 'kein Medienobjekt angelegt: ' . json_encode($neu));
pruefe(IPS_GetChildrenIDs($fremd) === [], 'unter dem fremden Objekt hängt nichts');
// der alte Weg scheiterte unter PHP 8 schon vor dem Anlegen: imagerotate() mit vier Argumenten
pruefe($antwort !== '' && !str_starts_with($antwort, 'Ausnahme'), 'Hook antwortet mit einer Meldung, ohne Ausnahme: ' . $antwort);

@unlink($bild);
@unlink($koordinaten);
@rmdir($verzeichnis);

ergebnis();
