<?php

class RRMapFileParser
{
    public const CHARGER                 = 1;
    public const IMAGE                   = 2;
    public const PATH                    = 3;
    public const GOTO_PATH               = 4;
    public const GOTO_PREDICTED_PATH     = 5;
    public const CURRENTLY_CLEANED_ZONES = 6;
    public const GOTO_TARGET             = 7;
    public const ROBOT_POSITION          = 8;
    public const NO_GO_AREAS             = 9;
    public const VIRTUAL_WALLS           = 10;
    public const BLOCKS                  = 11;
    public const MOB_FORBIDDEN_AREA      = 12;
    public const OBSTACLES               = 13;
    public const IGNORED_OBSTACLES       = 14;
    public const OBSTACLES2              = 15;
    public const IGNORED_OBSTACLES2      = 16;
    public const CARPET_MAP              = 17;
    public const MOP_PATH                = 18;
    public const CARPET_FORBIDDEN_AREA   = 19;
    public const SMART_ZONES_PATH_TYPE   = 20;
    public const SMART_ZONES             = 21;
    public const CUSTOM_CARPET           = 22;
    public const CL_FORBIDDEN_ZONES      = 23;
    public const FLOOR_MAP               = 24;
    public const FURNITURES              = 25;
    public const DOCK_TYPE               = 26;
    public const ENEMIES                 = 26;
    public const DIGEST                  = 1024;
    public const HEADER                  = 0x7272;

    public const PATH_POINT_LENGTH = 'pointLength';
    public const PATH_POINT_SIZE   = 'pointSize';
    public const PATH_ANGLE        = 'angle';

    private string $image        = '';

    private array  $areas        = [];

    private array  $paths        = [];

    private array  $pathsDetails = [];

    private array  $zones        = [];

    private string $blocks       = '';

    private int    $majorVersion;

    private int    $minorVersion;

    private int    $mapIndex;

    private int    $mapSequence;

    private int    $imgHeight;

    private int    $imgWidth;

    private int    $imageSize;

    private int    $top;

    private int    $left;

    private int    $chargerX;

    private int    $chargerY;

    private int    $roboX;

    private int    $roboY;

    private int    $roboA; //angle

    private bool   $isValid      = false;


    public function __construct($raw)
    {
        // see also https://github.com/marcelrv/XiaomiRobotVacuumProtocol/tree/master/RRMapFile
        // Viewer: https://community.openhab.org/t/xiaomi-vacuum-map-viewer-to-find-coordinates-for-zone-cleaning/103500
        // https://github.com/marcelrv/openhab2/commits/276a4cfc0512d9a44d87d43505c01d66561ea65e/bundles/org.openhab.binding.miio/src/main/java/org/openhab/binding/miio/internal/robot/RRMapFileParser.java

        // zeichnen: https://github.com/marcelrv/openhab2/blob/276a4cfc0512d9a44d87d43505c01d66561ea65e/bundles/org.openhab.binding.miio/src/main/java/org/openhab/binding/miio/internal/robot/RRMapDraw.java

        $printBlockDetails  = false;
        $mapHeaderLength    = $this->getUInt16($raw, 0x02);
        $mapDataLength      = $this->getUInt32LE($raw, 0x04);
        $this->majorVersion = $this->getUInt16($raw, 0x08);
        $this->minorVersion = $this->getUInt16($raw, 0x0A);
        $this->mapIndex     = $this->getUInt32LE($raw, 0x0C);
        $this->mapSequence  = $this->getUInt32LE($raw, 0x10);

        $blockStartPos = $this->getUInt16($raw, 0x02); // main header length

        while ($blockStartPos < strlen($raw)) {
            $blockHeaderLength = $this->getUInt16($raw, $blockStartPos + 0x02);
            $header            = substr($raw, $blockStartPos, $blockHeaderLength);
            $blocktype         = $this->getUInt16($header, 0x00);
            $blockDataLength   = $this->getUInt32LE($header, 0x04);
            $blockDataStart    = $blockStartPos + $blockHeaderLength;
            //echo 'Header: ' . bin2hex($header) . "\r\n";

            $data = substr($raw, $blockDataStart, $blockDataLength);

            switch ($blocktype) {
                case self::CHARGER:
                    $this->chargerX = $this->getUInt32LE($raw, $blockStartPos + 0x08);
                    $this->chargerY = $this->getUInt32LE($raw, $blockStartPos + 0x0C);
                    break;

                case self::IMAGE:
                    $this->imageSize = $blockDataLength;// (getUInt32LE(raw, blockStartPos + 0x04));
                    if ($blockHeaderLength > 0x1C) {
                        IPS_LogMessage(__FUNCTION__, "block 2 unknown value @pos 8: " . $this->getUInt32LE($header, 0x08));
                    }
                    $this->top       = $this->getUInt32LE($header, $blockHeaderLength - 16);
                    $this->left      = $this->getUInt32LE($header, $blockHeaderLength - 12);
                    $this->imgHeight = $this->getUInt32LE($header, $blockHeaderLength - 8);
                    $this->imgWidth  = $this->getUInt32LE($header, $blockHeaderLength - 4);
                    $this->image     = $data;
                    break;

                case self::PATH:
                case self::GOTO_PATH:
                case self::GOTO_PREDICTED_PATH:
                    $paths                           = [];
                    $pairs                           = $this->getUInt32LE($header, 0x04) / 4;
                    $detail[self::PATH_POINT_LENGTH] = $this->getUInt32LE($header, 0x08);
                    $detail[self::PATH_POINT_SIZE]   = $this->getUInt32LE($header, 0x0C);
                    $detail[self::PATH_ANGLE]        = $this->getUInt32LE($header, 0x10);
                    for ($pathpair = 0; $pathpair < $pairs; $pathpair++) {
                        $x       = $this->getUInt16(substr($raw, $blockDataStart + $pathpair * 4, 2));
                        $y       = $this->getUInt16(substr($raw, $blockDataStart + $pathpair * 4 + 2, 2));
                        $paths[] = [$x, $y];
                    }
                    if (count($paths)) {
                        $this->paths[$blocktype]        = $paths;
                        $this->pathsDetails[$blocktype] = $detail;
                    }
                    break;

                case self::CURRENTLY_CLEANED_ZONES:
                    $zonePairs = $this->getUInt16($header, 0x08);
                    for ($zonePair = 0; $zonePair < $zonePairs; $zonePair++) {
                        $x0            = $this->getUInt16($raw, $blockDataStart + $zonePair * 8);
                        $y0            = $this->getUInt16($raw, $blockDataStart + $zonePair * 8 + 2);
                        $x1            = $this->getUInt16($raw, $blockDataStart + $zonePair * 8 + 4);
                        $y1            = $this->getUInt16($raw, $blockDataStart + $zonePair * 8 + 6);
                        $this->zones[] = [$x0, $y0, $x1, $y1];
                    }
                    break;

                case self::ROBOT_POSITION:
                    $this->roboX = $this->getUInt32LE($data, 0x00);
                    $this->roboY = $this->getUInt32LE($data, 0x04);
                    if ($blockDataLength > 8) { // model S6
                        $this->roboA = $this->getUInt32LE($data, 0x08);
                    }
                    break;

                case self::NO_GO_AREAS:
                case self::MOB_FORBIDDEN_AREA:
                case self::CARPET_FORBIDDEN_AREA:
                    $area      = [];
                    $areaPairs = $this->getUInt16($header, 0x08);
                    for ($areaPair = 0; $areaPair < $areaPairs; $areaPair++) {
                        $x0     = $this->getUInt16($raw, $blockDataStart + $areaPair * 16);
                        $y0     = $this->getUInt16($raw, $blockDataStart + $areaPair * 16 + 2);
                        $x1     = $this->getUInt16($raw, $blockDataStart + $areaPair * 16 + 4);
                        $y1     = $this->getUInt16($raw, $blockDataStart + $areaPair * 16 + 6);
                        $x2     = $this->getUInt16($raw, $blockDataStart + $areaPair * 16 + 8);
                        $y2     = $this->getUInt16($raw, $blockDataStart + $areaPair * 16 + 10);
                        $x3     = $this->getUInt16($raw, $blockDataStart + $areaPair * 16 + 12);
                        $y3     = $this->getUInt16($raw, $blockDataStart + $areaPair * 16 + 14);
                        $area[] = [$x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3];
                    }
                    if (count($area)) {
                        $this->areas[$blocktype] = $area;
                    }
                    break;

                case self::BLOCKS:
                    $blocksPairs  = $this->getUInt16($header, 0x08);
                    $this->blocks = substr($data, 0, $blocksPairs);
                    break;

                case self::DIGEST:
                    $this->isValid = bin2hex($data) === sha1(substr($raw, 0, $mapHeaderLength + $mapDataLength - 20));
                    break;

                default:
                    if ($blockDataLength > 0){
                        IPS_LogMessage(__FUNCTION__, sprintf ('The blocktype %s is not yet supported. (header length: %s, data length: %s)', $blocktype, $blockHeaderLength, $blockDataLength));
                    }
            }
            $blockStartPos += $blockDataLength + $blockHeaderLength;
        }
    }

    private function getUInt16(string $bytes, int $i = 0): int
    {
        return ord($bytes[$i++]) | (ord($bytes[$i++]) << 8);
    }

    private function getUInt32LE(string $bytes, int $i): int
    {
        return ord($bytes[$i++]) | (ord($bytes[$i++]) << 8) | (ord($bytes[$i++]) << 16) | (ord($bytes[$i++]) << 24);
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getAreas(): array
    {
        return $this->areas;
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getPathsDetails(): array
    {
        return $this->pathsDetails;
    }

    public function getImgWidth(): int
    {
        return $this->imgWidth;
    }

    public function getImgHeight(): int
    {
        return $this->imgHeight;
    }

    public function getLeft(): int
    {
        return $this->left;
    }

    public function getTop(): int
    {
        return $this->top;
    }

    public function getChargerX(): int
    {
        return $this->chargerX;
    }

    public function getChargerY(): int
    {
        return $this->chargerY;
    }

    public function getRoboX(): int
    {
        return $this->roboX;
    }

    public function getRoboY(): int
    {
        return $this->roboY;
    }

    public function getBlocks(): string
    {
        return $this->blocks;
    }
   public function getZones(): array
    {
        return $this->zones;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }
}