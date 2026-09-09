<?php

declare(strict_types=1);

/*
 * Kartenzeichnung (RRMapDraw) — Pixel-Regressionstest.
 *
 * Anlass (09.09.2026): Beim Einführen der Style-Prüfung wollte `declare_strict_types` in
 * RRMapDraw.php, RRMapFileParser.php, roborock_vacuum.php und libs/picture.php eingefügt
 * werden. Der Kartenpfad rechnet aber durchweg mit `float` — `getImage(float $scale)` gibt
 * den Maßstab bis in jede Zeichenmethode weiter — und übergibt die Ergebnisse ungecastet an
 * die GD-Funktionen, die `int` erwarten:
 *
 *     imagefilledrectangle($gdImage, round($xPos - $t), …)   // round() liefert float
 *     imagesetthickness($gdImage, $scale * 1.1)              // float
 *     imagecopy($newImage, $im1, $x - ($r / 2), …)           // float bei ungeradem $r
 *
 * Ohne strict_types wandelt PHP das still um (und zwar ABSCHNEIDEND, nicht rundend), mit
 * strict_types wäre es ein TypeError mitten im Kartenzeichnen — auf der Anlage, nicht im
 * Test. Deshalb dieser Test: Er zeichnet eine synthetische Karte, die jeden Zeichenweg
 * berührt, und hält das Ergebnis über den SHA256 der PNG-Bytes fest.
 *
 * Der Hash ist der Beleg dafür, dass die Casts nichts verschieben: Er muss vor und nach
 * dem Einführen von strict_types derselbe sein. Ändert sich später der Zeichencode
 * absichtlich, ändert sich auch der Hash — dann die Karte ansehen und den Wert unten
 * bewusst nachziehen.
 *
 * Gehasht werden die BILDPUNKTE, nicht die PNG-Datei: Die PNG-Kodierung hängt an der
 * zlib- und libgd-Fassung, ein Byte-Hash wäre auf dem Bauserver ein anderer als hier.
 *
 * Aufruf: php tests/check-map-rendering.php (Exit-Code 1 bei Abweichung)
 */

require_once __DIR__ . '/../Roborock Robot/RRMapFileParser.php';
require_once __DIR__ . '/../Roborock Robot/RRMapDraw.php';

/**
 * Synthetische Karte: erbt vom echten Parser, ruft dessen Konstruktor aber nicht auf und
 * beantwortet jede Abfrage aus festen Werten. So hängt der Test weder an der Cloud noch an
 * einer aufgezeichneten Kartendatei, und die Eingabe ist bei jedem Lauf identisch.
 */
final class SyntheticMap extends RRMapFileParser
{
    private const W = 48;
    private const H = 40;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
        // bewusst kein parent::__construct(): der echte Parser will Rohdaten
    }

    public function getImage(): string
    {
        // Ein Byte je Pixel: außen, innen, Wand, Scan und zwei Räume — deckt alle Zweige
        // von drawMap() ab (inklusive der Raumfarben über walltype >> 3).
        $bild = '';
        for ($y = 0; $y < self::H; $y++) {
            for ($x = 0; $x < self::W; $x++) {
                if ($x < 4 || $y < 4 || $x >= self::W - 4 || $y >= self::H - 4) {
                    $byte = 0x00;                       // MAP_OUTSIDE
                } elseif ($x === 4 || $y === 4) {
                    $byte = 0x01;                       // MAP_WALL
                } elseif ($x === 6) {
                    $byte = 0xFF;                       // MAP_INSIDE
                } elseif ($x === 8) {
                    $byte = 0x07;                       // MAP_SCAN
                } elseif ($x === 10) {
                    $byte = 0x04;                       // unbekannter Hindernistyp: $color bleibt null
                } else {
                    $raum = ($x < self::W / 2) ? 1 : 2; // zwei Räume über die Raumkennung
                    $byte = ($raum << 3) | 0x07;
                }
                $bild .= chr($byte);
            }
        }
        return $bild;
    }

    public function getImgWidth(): int  { return self::W; }
    public function getImgHeight(): int { return self::H; }
    public function getLeft(): int      { return 0; }
    public function getTop(): int       { return 0; }
    public function getChargerX(): int  { return 12; }
    public function getChargerY(): int  { return 14; }
    public function getRoboX(): int     { return 20; }
    public function getRoboY(): int     { return 22; }
    public function isValid(): bool     { return true; }
    public function getMapDate(): int   { return 1_757_000_000; }
    public function getBlocks(): string { return ''; }

    /**
     * Die Listen sind numerisch indiziert, so wie der echte Parser sie liefert.
     * Koordinaten in Millimetern (toXCoord/toYCoord rechnet sie in Pixel um).
     */
    public function getPaths(): array
    {
        // Schlüssel = Pfadart, damit drawPath() den Farbzweig je Art durchläuft
        return [
            RRMapFileParser::PATH      => [[2000, 2000], [6000, 2400], [6000, 5600]],
            RRMapFileParser::GOTO_PATH => [[2800, 5200], [5200, 5200]],
        ];
    }

    public function getPathsDetails(): array
    {
        return [];
    }

    public function getWalls(): array
    {
        return [[2400, 6000, 6800, 6000]];
    }

    public function getZones(): array
    {
        return [[2800, 2800, 4800, 4800]];
    }

    public function getAreas(): array
    {
        // je Bereich acht Werte (vier Eckpunkte); drawNoGo() nutzt 0,1 und 4,5
        return [
            RRMapFileParser::NO_GO_AREAS            => [[5600, 2800, 7200, 2800, 7200, 4400, 5600, 4400]],
            RRMapFileParser::DOOR_SILL_FORBIDDEN_AREA => [[2000, 5600, 3200, 5600, 3200, 6000, 2000, 6000]],
        ];
    }

    public function getObstacles(): array
    {
        // äußere Ebene = Hindernisart, innere = die einzelnen Punkte (x, y, Typ)
        return [[[3600, 3600, 1], [4400, 4000, 2]]];
    }

    public function getStuckPoints(): array
    {
        return [[5200, 3200]];
    }

    public function getCarpetMap(): array
    {
        // Teppich in einem kleinen Feld — drawCarpetMap() rechnet dort mit round()
        $karte = array_fill(0, self::W * self::H, 0);
        for ($y = 16; $y < 20; $y++) {
            for ($x = 16; $x < 20; $x++) {
                $karte[$x + self::W * $y] = 1;
            }
        }
        return $karte;
    }

    public function getMopPath(): array { return []; }
}

/* --- Prüfung ------------------------------------------------------------------------- */

$pruefungen = 0;
$fehler     = [];

function pruefe(bool $ok, string $text): void
{
    global $pruefungen, $fehler;
    $pruefungen++;
    if (!$ok) {
        $fehler[] = $text;
    }
    echo($ok ? '  ok   ' : '  FEHL ') . $text . "\n";
}

/**
 * Prüfsumme über die Bildpunkte: Maße, dann jeder Punkt als Farbwert mit Deckkraft.
 */
function pixelPruefsumme(string $png): string
{
    $bild = imagecreatefromstring($png);
    $b    = imagesx($bild);
    $h    = imagesy($bild);
    $ctx  = hash_init('sha256');
    hash_update($ctx, "$b x $h");
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $b; $x++) {
            hash_update($ctx, pack('N', imagecolorat($bild, $x, $y)));
        }
    }
    imagedestroy($bild);
    return hash_final($ctx);
}

/*
 * Erwartete Prüfsummen der gezeichneten Karte je Maßstab. Ermittelt am 09.09.2026 am
 * unveränderten Stand (build 88, ohne strict_types, ohne Casts); die Umstellung darf sie
 * nicht verändern.
 */
$erwartet = [
    '1.0' => '0339039b38c055a96df03d14702ed19494a0681be33cf33ee5622155b51e21e0',
    '1.5' => '851baec04699ee8260649935a756d77f4b9ce25d3d2f5c482730f4e6c276341b',
    '2.0' => '1039ecca38c3181583ae534ab20d2be325b899e1bbabae0eb5970c7aaeda7b59',
];

// Neue Sollwerte ermitteln: MAP_HASH_ERMITTELN=1 php tests/check-map-rendering.php
$ermitteln = getenv('MAP_HASH_ERMITTELN') !== false;

echo "\nKarte zeichnen und mit den festgehaltenen Prüfsummen vergleichen\n";
foreach ($erwartet as $massstab => $soll) {
    $draw = new RRMapDraw(new SyntheticMap(), static function (string $m, string $d): void {});
    $png  = $draw->getImage((float)$massstab);
    $ist  = pixelPruefsumme($png);
    pruefe($png !== '' && str_starts_with($png, "\x89PNG"), sprintf('Maßstab %s: PNG erzeugt (%d Bytes)', $massstab, strlen($png)));
    if ($ermitteln) {
        echo "  --   Maßstab $massstab: SHA256 $ist\n";
        continue;
    }
    pruefe($ist === $soll, sprintf('Maßstab %s: Pixel unverändert (%s)', $massstab, substr($ist, 0, 16)));
}

echo "\n$pruefungen Prüfungen, " . count($fehler) . " Fehler\n";
exit($fehler === [] ? 0 : 1);
