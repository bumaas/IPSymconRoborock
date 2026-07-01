<?php

require_once __DIR__ . '/RRMapFileParser.php';

class RRMapDraw
{

    private const MM = 50.0;

    private const CYAN        = [214, 0, 214];
    private const WHITE       = [255, 255, 255];
    private const RED         = [255, 0, 0];
    private const TRANSPARENT = [1, 0, 0];

    private const COLOR_MAP_OUTSIDE = self::TRANSPARENT;
    private const COLOR_MAP_INSIDE   = self::CYAN;
    private const COLOR_BLACK_WALL   = [100, 100, 100]; //dark shade of gray
    private const COLOR_MAP_WALL     = self::COLOR_BLACK_WALL;
    private const COLOR_SCAN         = [223, 223, 223]; //sehr heller Grauton
    //private const COLOR_CARPET       = [self::COLOR_SCAN[0], self::COLOR_SCAN[1], self::COLOR_SCAN[2], 160];
    private const COLOR_GREY_WALL    = [200, 200, 200]; //light grey
    private const COLOR_PATH         = [147, 194, 238];
    private const COLOR_ZONES        = [0xAD, 0xD8, 0xFF, 0x8F];
    private const COLOR_NO_GO_ZONES  = [255, 33, 55, 110];
    private const COLOR_DOOR_SILL_ZONES = [255, 208, 0, 110]; //gelb, wie in der App
    private const COLOR_CHARGER_HALO = [0x66, 0xfe, 0xda, 0x7f];
    private const COLOR_ROBO         = [75, 235, 149];

    private const      ROOM1       = [240, 178, 122];
    private const      ROOM2       = [133, 193, 233];
    private const      ROOM3       = [217, 136, 128];
    private const      ROOM4       = [52, 152, 219];
    private const      ROOM5       = [205, 97, 85];
    private const      ROOM6       = [243, 156, 18];
    private const      ROOM7       = [88, 214, 141];
    private const      ROOM8       = [245, 176, 65];
    private const      ROOM9       = [0xFc, 0xD4, 0x51];
    private const      ROOM10      = [72, 201, 176];
    private const      ROOM11      = [84, 153, 199];
    private const      ROOM12      = [255, 213, 209];
    private const      ROOM13      = [228, 228, 215];
    private const      ROOM14      = [82, 190, 128];
    private const      ROOM15      = [72, 201, 176];
    private const      ROOM16      = [165, 105, 189];
    private const      ROOM_COLORS = [
        self::ROOM1,
        self::ROOM2,
        self::ROOM3,
        self::ROOM4,
        self::ROOM5,
        self::ROOM6,
        self::ROOM7,
        self::ROOM8,
        self::ROOM9,
        self::ROOM10,
        self::ROOM11,
        self::ROOM12,
        self::ROOM13,
        self::ROOM14,
        self::ROOM15,
        self::ROOM16
    ];

    private const MAP_OUTSIDE = 0x00;
    private const MAP_WALL    = 0x01;
    private const MAP_INSIDE  = 0xFF;
    private const MAP_SCAN    = 0x07;

    private const CROPBORDER = 10;


    private RRMapFileParser $rmfp;
    /**
     * @var callable
     */
    private $logger;

    private int             $firstX     = 0;

    private int             $lastX      = 0;

    private int             $firstY     = 0;

    private int             $lastY      = 0;

    private bool            $multicolor = false;

    public function __construct(RRMapFileParser $rmfp, ?callable $logger = null)
    {
        $this->rmfp   = $rmfp;
        $this->logger = $logger ?? static function (string $message, string $data): void {
        };
    }


    public function getImage(float $scale = 1): string
    {
        $width    = floor($scale * $this->rmfp->getImgWidth());
        $height   = floor($scale * $this->rmfp->getImgHeight());
        $newImage = @imagecreatetruecolor($width, $height);
        if (!$newImage) {
            return '';
        }
        $this->drawMap($newImage, $scale);
        $this->drawCarpetMap($newImage, $scale);
        //todo: $this->drawMopPath($newImage, $scale); funktioniert noch nicht
        $this->drawNoGo($newImage, $scale);
        $this->drawWalls($newImage, $scale);
        $this->drawRobo($newImage, $scale);
        $this->drawPath($newImage, $scale);
        $this->drawZones($newImage, $scale);
        $this->drawObstacles($newImage, $scale);
        $this->drawStuckPoints($newImage, $scale);
        // todo: drawGoTo(g2d, scale);

        // crop the image to the used perimeter
        $this->getMapArea();
        $firstX  = ($this->firstX - self::CROPBORDER) > 0 ? $this->firstX - self::CROPBORDER : 0;
        $lastX   = (($this->lastX + self::CROPBORDER) < $this->rmfp->getImgWidth()) ? $this->lastX + self::CROPBORDER : $this->rmfp->getImgWidth();
        $firstY  = ($this->firstY - self::CROPBORDER) > 0 ? $this->firstY - self::CROPBORDER : 0;
        $lastY   = (($this->lastY + self::CROPBORDER + (int)(8 * $scale)) < $this->rmfp->getImgHeight()) ? $this->lastY + self::CROPBORDER + (int)(8 * $scale) : $this->rmfp->getImgHeight();
        $nwidth  = (int)floor(($lastX - $firstX) * $scale);
        $nheight = (int)floor(($lastY - $firstY) * $scale);

        $newImage = imagecrop(
            $newImage, [
                         'x'      => ($this->rmfp->getImgWidth() - $lastX) * $scale,
                         'y'      => ($this->rmfp->getImgHeight() - $lastY) * $scale,
                         'width'  => $nwidth,
                         'height' => $nheight
                     ]
        );

        $newImage = $this->rotateAndMakeImageTransparent($newImage, 180);

        ob_start();
        imagepng($newImage, null, 9, PNG_NO_FILTER);      //imagepng() creates a PNG file from the given image.
        return ob_get_clean();
    }

    private function drawMap(&$gdImage, float $scale): void
    {
        imagesetthickness($gdImage, $scale * 1.1);

        $rmfpImage        = $this->rmfp->getImage();
        $unknownObstacles = [];

        for ($y = 0; $y < ($this->rmfp->getImgHeight() - 1); $y++) {
            for ($x = 0; $x < ($this->rmfp->getImgWidth() + 1); $x++) {
                $color    = null;
                $walltype = ord($rmfpImage[$x + $this->rmfp->getImgWidth() * $y]);

                switch ($walltype) {
                    case self::MAP_OUTSIDE:
                        $color = imagecolorallocate($gdImage, self::COLOR_MAP_OUTSIDE[0], self::COLOR_MAP_OUTSIDE[1], self::COLOR_MAP_OUTSIDE[2]);
                        break;
                    case self::MAP_INSIDE:
                        $color = imagecolorallocate($gdImage, self::COLOR_MAP_INSIDE[0], self::COLOR_MAP_INSIDE[1], self::COLOR_MAP_INSIDE[2]);
                        break;
                    case self::MAP_WALL:
                        $color = imagecolorallocate($gdImage, self::COLOR_MAP_WALL[0], self::COLOR_MAP_WALL[1], self::COLOR_MAP_WALL[2]);
                        break;
                    case self::MAP_SCAN:
                        $color = imagecolorallocate($gdImage, self::COLOR_SCAN[0], self::COLOR_SCAN[1], self::COLOR_SCAN[2]);
                        break;
                    default:
                        $obstacle = $walltype & 0x07;
                        switch ($obstacle) {
                            case 0:
                                $color = imagecolorallocate(
                                    $gdImage,
                                    self::COLOR_GREY_WALL[0],
                                    self::COLOR_GREY_WALL[1],
                                    self::COLOR_GREY_WALL[2]
                                );
                                break;

                            case 1:
                                $color = imagecolorallocate(
                                    $gdImage,
                                    self::COLOR_BLACK_WALL[0],
                                    self::COLOR_BLACK_WALL[1],
                                    self::COLOR_BLACK_WALL[2]
                                );
                                break;

                            case 7:
                                $mapId            = $walltype >> 3;
                                $i                = $mapId % 15;
                                $color            =
                                    imagecolorallocate($gdImage, self::ROOM_COLORS[$i][0], self::ROOM_COLORS[$i][1], self::ROOM_COLORS[$i][2]);
                                $this->multicolor = true;
                                break;

                            default:
                                $unknownObstacles[$obstacle] = true;
                        }
                }

                $xPos = $scale * ($this->rmfp->getImgWidth() - $x);
                $yP   = $scale * $y;
                $t    = $scale / 2 - 0.5;
                imagefilledrectangle($gdImage, round($xPos - $t), round($yP - $t), round($xPos + $t), round($yP + $t), $color);
            }
        }

        if (count($unknownObstacles)) {
            call_user_func(
                $this->logger,
                __CLASS__ . '::' . __FUNCTION__,
                sprintf('Unknown Obstacles found: %s', json_encode($unknownObstacles, JSON_THROW_ON_ERROR))
            );
        }
    }

    private function drawZones(&$gdImage, float $scale): void
    {
        foreach ($this->rmfp->getZones() as $point) {
            //echo sprintf('%s: %s, %s, %s, %s', __FUNCTION__, $point[0], $point[1], $point[2], $point[3]) . PHP_EOL;
            $x  = $this->toXCoord($point[0]) * $scale;
            $y  = $this->toYCoord($point[1]) * $scale;
            $x1 = $this->toXCoord($point[2]) * $scale;
            $y1 = $this->toYCoord($point[3]) * $scale;
            imagefilledrectangle(
                $gdImage,
                $x,
                $y,
                $x1,
                $y1,
                imagecolorallocatealpha(
                    $gdImage,
                    self::COLOR_ZONES[0],
                    self::COLOR_ZONES[1],
                    self::COLOR_ZONES[2],
                    (int)(self::COLOR_ZONES[3] / 2)
                )
            );
        }
    }

    private function drawNoGo(&$gdImage, float $scale): void
    {
        imagesetthickness($gdImage, $scale * 0.5);

        foreach ($this->rmfp->getAreas() as $blocktype => $arealist) {
            $areaColor = ($blocktype === RRMapFileParser::DOOR_SILL_FORBIDDEN_AREA)
                ? self::COLOR_DOOR_SILL_ZONES
                : self::COLOR_NO_GO_ZONES;
            foreach ($arealist as $area) {
                //echo sprintf('%s, %s, %s, %s, %s', __FUNCTION__, $area[0], $area[1], $area[4], $area[5]) . PHP_EOL;
                $x1 = $this->toXCoord($area[0]) * $scale;
                $y1 = $this->toYCoord($area[1]) * $scale;
                $x3 = $this->toXCoord($area[4]) * $scale;
                $y3 = $this->toYCoord($area[5]) * $scale;
                imagefilledrectangle(
                    $gdImage,
                    $x1,
                    $y1,
                    $x3,
                    $y3,
                    imagecolorallocatealpha(
                        $gdImage,
                        $areaColor[0],
                        $areaColor[1],
                        $areaColor[2],
                        (int)($areaColor[3] / 2)
                    )
                );
                ImageRectangle(
                    $gdImage,
                    $x1,
                    $y1,
                    $x3,
                    $y3,
                    imagecolorallocate($gdImage, $areaColor[0], $areaColor[1], $areaColor[2])
                );
            }
        }
    }

    private function drawWalls(&$gdImage, float $scale): void
    {
        imagesetthickness($gdImage, $scale * 3);
        $color = imagecolorallocate($gdImage, self::RED[0], self::RED[1], self::RED[2]);
        foreach ($this->rmfp->getWalls() as $point) {
            $x  = $this->toXCoord($point[0]) * $scale;
            $y  = $this->toYCoord($point[1]) * $scale;
            $x1 = $this->toXCoord($point[2]) * $scale;
            $y1 = $this->toYCoord($point[3]) * $scale;
            imageline($gdImage, $x, $y, $x1, $y1, $color);
        }
    }

    /**
     * draws the carpet map
     */
    private function drawCarpetMap(&$gdImage, float $scale): void
    {
        if (count($this->rmfp->getCarpetMap()) === 0) {
            return;
        }
        imagesetthickness($gdImage, $scale * 1.1);

        for ($y = 0; $y < $this->rmfp->getImgHeight() - 1; $y++) {
            for ($x = 0; $x < $this->rmfp->getImgWidth() + 1; $x++) {
                $carpetType = $this->rmfp->getCarpetMap()[$x + $this->rmfp->getImgWidth() * $y];
                if ($carpetType > 0) {
                    $xPos = $scale * ($this->rmfp->getImgWidth() - $x);
                    $yP     = $scale * $y;
                    $t      = $scale / 2 - 0.5;
                    $colors = imagecolorsforindex($gdImage, imagecolorat($gdImage, $xPos, $yP));
                    $color  = imagecolorallocate($gdImage, max($colors['red'] - 15, 0), max($colors['green'] - 15, 0) , max($colors['blue'] - 15, 0));
                    imagefilledrectangle($gdImage, round($xPos - $t), round($yP - $t), round($xPos + $t), round($yP + $t), $color);
                    break;
                }
            }
        }
    }


    /**
     * draws the mop path
     */
    private function drawMopPath(&$gdImage, float $scale): void
    {
        if (count($this->rmfp->getMopPath()) === 0) {
            return;
        }
        imagesetthickness($gdImage, $scale * 1.1);

        /*
        for ($y = 0; $y < $this->rmfp->getImgHeight() - 1; $y++) {
            for ($x = 0; $x < $this->rmfp->getImgWidth() + 1; $x++) {
                // no idea yet
            }
        }
        */
    }


    /**
     * draws the vacuum path
     */
    private function drawPath(&$gdImage, float $scale): void
    {
        imagesetthickness($gdImage, $scale * 0.5);

        foreach ($this->rmfp->getPaths() as $pathType => $paths) {
            //Integer pathType = path . getKey();
            switch ($pathType) {
                case RRMapFileParser::PATH:
                    if (!$this->multicolor) {
                        $color = imagecolorallocate($gdImage, self::COLOR_PATH[0], self::COLOR_PATH[1], self::COLOR_PATH[2]);
                    } else {
                        $color = imagecolorallocate($gdImage, self::WHITE[0], self::WHITE[1], self::WHITE[2]);
                    }
                    break;
                case RRMapFileParser::GOTO_PATH:
                    $color = imagecolorallocate($gdImage, 0, 128, 0); //green
                    break;
                case RRMapFileParser::GOTO_PREDICTED_PATH:
                    $color = imagecolorallocate($gdImage, 255, 255, 0); //yellow
                    break;
                default:
                    $color = imagecolorallocate($gdImage, 0, 255, 255); //cyan
            }

            $prvX = 0;
            $prvY = 0;
            foreach ($paths as $point) {
                $x = $this->toXCoord($point[0]) * $scale;
                $y = $this->toYCoord($point[1]) * $scale;
                if ($prvX > 1) {
                    imageline($gdImage, $prvX, $prvY, $x, $y, $color);
                }
                $prvX = $x;
                $prvY = $y;
            }
        }
    }

    private function drawRobo(&$gdImage, float $scale): void
    {
        imagesetthickness($gdImage, 3 * $scale);

        $radius   = 8 * $scale;
        $color    = imagecolorallocatealpha(
            $gdImage,
            self::COLOR_CHARGER_HALO[0],
            self::COLOR_CHARGER_HALO[1],
            self::COLOR_CHARGER_HALO[2],
            (int)(self::COLOR_CHARGER_HALO[3] / 2)
        );
        $chargerX = $this->toXCoord($this->rmfp->getChargerX()) * $scale;
        $chargerY = $this->toYCoord($this->rmfp->getChargerY()) * $scale;
        imagefilledellipse($gdImage, $chargerX, $chargerY, $radius, $radius, $color);
        $this->drawCenteredImg($gdImage, $scale / 8, dirname(__DIR__) . '/imgs/charger.png', $chargerX, $chargerY);

        $radius = 10 * $scale;
        $color  = imagecolorallocate($gdImage, self::COLOR_ROBO[0], self::COLOR_ROBO[1], self::COLOR_ROBO[2]);
        $roboX  = $this->toXCoord($this->rmfp->getRoboX()) * $scale;
        $roboY  = $this->toYCoord($this->rmfp->getRoboY()) * $scale;
        imagefilledellipse($gdImage, $roboX, $roboY, $radius, $radius, $color);
        if ($scale >= 1.5) {
            $this->drawCenteredImg($gdImage, $scale / 15, dirname(__DIR__) . '/imgs/robo.png', $roboX, $roboY);
        }
    }

    private function drawObstacles(&$gdImage, float $scale): void
    {
        $radius = 2 * $scale;
        imagesetthickness($gdImage, 3 * $scale);

        $color = imagecolorallocate($gdImage, 255, 0, 255); //magenta

        foreach ($this->rmfp->getObstacles() as $obstacle) {
            foreach ($obstacle as $entry) {
                $obstacleX = $this->toXCoord($entry[0]) * $scale;
                $obstacleY = $this->toYCoord($entry[1]) * $scale;
                imagefilledellipse($gdImage, $obstacleX, $obstacleY, $radius, $radius, $color);
                if ($scale > 1.0) {
                    $imgFile = dirname(__DIR__) . '/imgs/obstacle-' . $entry[2] . '.png';
                    if (!file_exists($imgFile)) {
                        $imgFile = dirname(__DIR__) . '/imgs/obstacle-18.png';
                    }
                    $this->drawCenteredImg($gdImage, $scale / 3, $imgFile, $obstacleX, $obstacleY);
                }
            }
        }
    }

    private function drawStuckPoints(&$gdImage, float $scale): void
    {
        $radius = 3 * $scale;
        imagesetthickness($gdImage, (int)($scale));

        $color = imagecolorallocate($gdImage, 255, 140, 0); //orange

        foreach ($this->rmfp->getStuckPoints() as $point) {
            $x = $this->toXCoord($point[0]) * $scale;
            $y = $this->toYCoord($point[1]) * $scale;
            imagefilledellipse($gdImage, $x, $y, $radius, $radius, $color);
        }
    }

    private function drawCenteredImg(&$gdImage, float $scale, string $imgFile, float $x, float $y): void
    {
        if ($addImage = @imagecreatefrompng($imgFile)) {
            $addImage = imagescale($addImage, imagesx($addImage) * $scale, -1);
            $addImage = $this->rotateAndMakeImageTransparent($addImage, 180);
            $xpos     = round($x - (imagesx($addImage) / 2));
            $ypos     = round($y - (imagesy($addImage) / 2));
            //echo sprintf('%s: x: %s, y: %s, xpos: %s, ypos: %s', __FUNCTION__, $x, $y, $xpos, $ypos) . PHP_EOL;
            imagecopy($gdImage, $addImage, $xpos, $ypos, 0, 0, imagesx($addImage), imagesy($addImage));
        } else {
            call_user_func($this->logger, __CLASS__ . '::' . __FUNCTION__, sprintf('Error loading image: %s', $imgFile));
        }
    }

    /**
     * This function is used to make an image transparent and rotate it.
     *
     * @param resource $gdImage Represents the image to be rotated.
     * @param int $angle Represents the rotation angle.
     * @return false|resource Returns the rotated image.
     */
    private function rotateAndMakeImageTransparent($gdImage, int $angle)
    {
        $pngTransparency = imagecolorallocatealpha($gdImage, self::TRANSPARENT[0], self::TRANSPARENT[1], self::TRANSPARENT[2], 127);
        imagefill($gdImage, 0, 0, $pngTransparency);

        $result = imagerotate($gdImage, $angle, $pngTransparency);
        imagealphablending($result, true);
        imagesavealpha($result, true);

        return $result;
    }

    /**
     * Finds the perimeter of the used area in the map
     *
     */
    private function getMapArea(): void
    {
        $firstX = $this->rmfp->getImgWidth();
        $lastX  = 0;
        $firstY = $this->rmfp->getImgHeight();
        $lastY  = 0;
        for ($y = 0; $y < $this->rmfp->getImgHeight() - 1; $y++) {
            for ($x = 0; $x < $this->rmfp->getImgWidth() + 1; $x++) {
                $walltype = ord($this->rmfp->getImage()[$x + $this->rmfp->getImgWidth() * $y]);
                if ($walltype > self::MAP_OUTSIDE) {
                    if ($y < $firstY) {
                        $firstY = $y;
                    }
                    if ($y > $lastY) {
                        $lastY = $y;
                    }
                    if ($x < $firstX) {
                        $firstX = $x;
                    }
                    if ($x > $lastX) {
                        $lastX = $x;
                    }
                }
            }
        }
        $this->firstX = $firstX;
        $this->lastX  = $lastX;
        $this->firstY = $this->rmfp->getImgHeight() - $lastY;
        $this->lastY  = $this->rmfp->getImgHeight() - $firstY;
    }


    private function toXCoord(float $x): float
    {
        return $this->rmfp->getImgWidth() + $this->rmfp->getLeft() - ($x / self::MM);
    }

    private function toYCoord(float $y): float
    {
        return $y / self::MM - $this->rmfp->getTop();
    }

}

