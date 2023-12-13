<?php
// see also https://github.com/marcelrv/XiaomiRobotVacuumProtocol/tree/master/RRMapFile
// Viewer: https://community.openhab.org/t/xiaomi-vacuum-map-viewer-to-find-coordinates-for-zone-cleaning/103500

// parsen: https://github.com/openhab/openhab-addons/blob/4dd6d3a8a2134cec920f07cb0b73c5f224f8bc70/bundles/org.openhab.binding.miio/src/main/java/org/openhab/binding/miio/internal/robot/RRMapFileParser.java
// zeichnen: https://github.com/openhab/openhab-addons/blob/4dd6d3a8a2134cec920f07cb0b73c5f224f8bc70/bundles/org.openhab.binding.miio/src/main/java/org/openhab/binding/miio/internal/robot/RRMapDraw.java


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
    public const FURNITURE               = 25;
    public const DOCK_TYPE               = 26;
    public const ENEMIES                 = 27;
    public const UNKNOWN_30              = 30; //since S8, SimonS
    public const UNKNOWN_32              = 32; //since S8, cbeham
    public const UNKNOWN_33              = 33; //since S8, SimonS
    public const DIGEST                  = 1024;
    public const HEADER                  = 0x7272;

    public const PATH_POINT_LENGTH = 'pointLength';
    public const PATH_POINT_SIZE   = 'pointSize';
    public const PATH_ANGLE        = 'angle';

    private string $image        = '';

    private array  $areas        = [];

    private array  $walls        = [];

    private array  $paths        = [];

    private array  $pathsDetails = [];

    private array  $zones        = [];

    private array  $obstacles    = [];

    private array  $carpetMap    = [];

    private array  $mopPath      = [];

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

    private int    $chargerX     = 0;

    private int    $chargerY     = 0;

    private int    $roboX        = 0;

    private int    $roboY        = 0;

    private int    $roboA        = 0; //angle

    private bool   $isValid      = false;

    /**
     * @var callable
     */
    private $Logger_Dbg;


    public function __construct($raw, callable $Logger_Dbg)
    {
        $this->Logger_Dbg = $Logger_Dbg;

        $mapHeaderLength    = $this->getUInt16($raw, 0x02);
        $mapDataLength      = $this->getUInt32LE($raw, 0x04);
        $this->majorVersion = $this->getUInt16($raw, 0x08);
        $this->minorVersion = $this->getUInt16($raw, 0x0A);
        $this->mapIndex     = $this->getUInt32LE($raw, 0x0C);
        $this->mapSequence  = $this->getUInt32LE($raw, 0x10);

        $blockStartPos = $this->getUInt16($raw, 0x02); // main header length

        call_user_func(
            $this->Logger_Dbg,
            __CLASS__,
            sprintf(
                'HeaderLength: %s, DataLength: %s, majorVersion: %s, minorVersion: %s',
                $mapHeaderLength,
                $mapDataLength,
                $this->majorVersion,
                $this->minorVersion
            )
        );

        while ($blockStartPos < strlen($raw)) {
            $blockHeaderLength = $this->getUInt16($raw, $blockStartPos + 0x02);
            $header            = substr($raw, $blockStartPos, $blockHeaderLength);
            $blocktype         = $this->getUInt16($header, 0x00);
            $blockDataLength   = $this->getUInt32LE($header, 0x04);
            $blockDataStart    = $blockStartPos + $blockHeaderLength;

            if ($blockDataLength) {
                call_user_func(
                    $this->Logger_Dbg,
                    __CLASS__,
                    sprintf('Blocktype: %s, headerLength: %s, dataLength: %s', $blocktype, $blockHeaderLength, $blockDataLength)
                );
            }

            $data = substr($raw, $blockDataStart, $blockDataLength);

            switch ($blocktype) {
                case self::CHARGER:
                    $this->chargerX = $this->getUInt32LE($raw, $blockStartPos + 0x08);
                    $this->chargerY = $this->getUInt32LE($raw, $blockStartPos + 0x0C);
                    break;

                case self::IMAGE:
                    $this->imageSize = $blockDataLength;// (getUInt32LE(raw, blockStartPos + 0x04));
                    if ($blockHeaderLength > 0x1C) {
                        IPS_LogMessage(
                            'Roborock MapFileParser - ' . __FUNCTION__,
                            "block 2 unknown value @pos 8: " . $this->getUInt32LE($header, 0x08)
                        );
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

                case self::VIRTUAL_WALLS:
                    $wallPairs = $this->getUInt16($header, 0x08);
                    for ($wallPair = 0; $wallPair < $wallPairs; $wallPair++) {
                        $x0            = $this->getUInt16($raw, $blockDataStart + $wallPair * 8);
                        $y0            = $this->getUInt16($raw, $blockDataStart + $wallPair * 8 + 2);
                        $x1            = $this->getUInt16($raw, $blockDataStart + $wallPair * 8 + 4);
                        $y1            = $this->getUInt16($raw, $blockDataStart + $wallPair * 8 + 6);
                        $this->walls[] = [$x0, $y0, $x1, $y1];
                    }
                    break;


                case self::BLOCKS:
                    $blocksPairs  = $this->getUInt16($header, 0x08);
                    $this->blocks = substr($data, 0, $blocksPairs);
                    break;

                case self::OBSTACLES2:
                    $obstacle2Pairs = $this->getUInt16($header, 0x08);
                    if ($obstacle2Pairs === 0) {
                        break;
                    }
                    $obstacleDataLength = $blockDataLength / $obstacle2Pairs;
                    $obstacle2          = [];
                    for ($obstaclePair = 0; $obstaclePair < $obstacle2Pairs; $obstaclePair++) {
                        $x0 = $this->getUInt16($data, $obstaclePair * $obstacleDataLength + 0);
                        $y0 = $this->getUInt16($data, $obstaclePair * $obstacleDataLength + 2);
                        $u0 = $this->getUInt16($data, $obstaclePair * $obstacleDataLength + 4);
                        $u1 = $this->getUInt16($data, $obstaclePair * $obstacleDataLength + 6);
                        $u2 = $this->getUInt32LE($data, $obstaclePair * $obstacleDataLength + 8);
                        if ($obstacleDataLength === 28) {
                            if (($data[$obstaclePair * $obstacleDataLength + 12]) === '') {
                                //echo "obstacle with photo: No text" . PHP_EOL;
                            } else {
                                $txt = substr($data, $obstaclePair * $obstacleDataLength + 12, 16);
                                //echo "obstacle with photo: {}" . $txt . PHP_EOL;
                            }
                            $obstacle2[] = [$x0, $y0, $u0, $u1, $u2];
                        } else {
                            $u3          = $this->getUInt32LE($data, $obstaclePair * $obstacleDataLength + 12);
                            $obstacle2[] = [$x0, $y0, $u0, $u1, $u2, $u3];
                            //echo "obstacle without photo." . PHP_EOL;
                        }
                    }
                    $this->obstacles[$blocktype] = $obstacle2;
                    break;

                case self::IGNORED_OBSTACLES2:
                    $ignoredObstaclePairs = $this->getUInt16($header, 0x08);
                    $ignoredObstacle      = [];
                    for ($obstaclePair = 0; $obstaclePair < $ignoredObstaclePairs; $obstaclePair++) {
                        $x0                = $this->getUInt16($data, $obstaclePair * 6 + 0);
                        $y0                = $this->getUInt16($data, $obstaclePair * 6 + 2);
                        $u                 = $this->getUInt16($data, $obstaclePair * 6 + 4);
                        $ignoredObstacle[] = [$x0, $y0, $u];
                    }
                    $this->obstacles[$blocktype] = $ignoredObstacle;
                    break;

                case self::CARPET_MAP:
                    for ($carpetNode = 0; $carpetNode < $blockDataLength; $carpetNode++) {
                        $this->carpetMap[$carpetNode] = ord($data[$carpetNode]);
                    }
                    break;

                case self::MOP_PATH:
                    for ($mopNode = 0; $mopNode < $blockDataLength; $mopNode++) {
                        $this->mopPath[$mopNode] = ord($data[$mopNode]);
                    }
                    break;

                case self::SMART_ZONES_PATH_TYPE:
                case self::SMART_ZONES:
                case self::CUSTOM_CARPET:
                case self::CL_FORBIDDEN_ZONES:
                case self::FLOOR_MAP:
                case self::FURNITURE:
                case self::DOCK_TYPE:
                case self::ENEMIES:
                case self::UNKNOWN_30:
                case self::UNKNOWN_32:
                case self::UNKNOWN_33:
                    // new blocktype not yet decoded
                    break;

                case self::DIGEST:
                    $this->isValid = bin2hex($data) === sha1(substr($raw, 0, $mapHeaderLength + $mapDataLength - 20));
                    call_user_func($this->Logger_Dbg, __CLASS__, sprintf('valid: %s', (int)$this->isValid));
                    break;

                default:
                    if ($blockDataLength > 0) {
                        IPS_LogMessage(
                            'Roborock MapFileParser - ' . __FUNCTION__,
                            sprintf(
                                'The blocktype %s is not yet supported. (header length: %s, data length: %s)',
                                $blocktype,
                                $blockHeaderLength,
                                $blockDataLength
                            )
                        );
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

    public function getWalls(): array
    {
        return $this->walls;
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

    public function getObstacles(): array
    {
        return $this->obstacles;
    }

    public function getCarpetMap(): array
    {
        return $this->carpetMap;
    }

    public function getMopPath(): array
    {
        return $this->mopPath;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }
}