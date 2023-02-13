<?php

function createPicture($data)
{
    $newImage        = null;
    $multi           = 4;
    $picsize         = 30;
    $divsize         = 50 / $multi;
    $pic_zonen       = 0;
    $pic_toppos      = 0;
    $pic_leftpos     = 0;
    $pic_imageheight = 0;
    $pic_imagewidth  = 0;

    $filedatapos = 0;
    $i           = $filedatapos;
    if ($data[$i++] !== 'r') {
        return;
    }
    if ($data[$i++] !== 'r') {
        return;
    }

    $headerlength   = ord($data[$i++]) | (ord($data[$i++]) << 8);
    $locationfooter = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
    $Major          = ord($data[$i++]) | (ord($data[$i++]) << 8);
    $Minor          = ord($data[$i++]) | (ord($data[$i++]) << 8);
    $MapIndex       = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
    $MapSequence    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
    $filedatapos    = $headerlength;

    echo "Filelen        :". strlen($data). "\r\n";
    echo "Headerlength   :".$headerlength."\r\n";
    echo "Locationfooter :".$locationfooter."\r\n";
    echo "Major          :".$Major."\r\n";
    echo "Minor          :".$Minor."\r\n";
    echo "MapIndex       :".$MapIndex."\r\n";
    echo "MapSequence    :".$MapSequence."\r\n";
    echo "\r\n";

    while ($filedatapos < strlen($data)) {
        $i                 = $filedatapos;
        $blocktype         = ord($data[$i++]) | (ord($data[$i++]) << 8);
        $blockheaderlength = ord($data[$i++]) | (ord($data[$i++]) << 8);
        $blockdatlength    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
        echo "Filedatapos       :" . $filedatapos . "\r\n";

        $filedatapos = $filedatapos + $blockheaderlength + $blockdatlength;
        echo "Blocktype         :" . $blocktype . "\r\n";
        echo "Blockheaderlength :" . $blockheaderlength . "\r\n";
        echo "Blockdatlength    :" . $blockdatlength . "\r\n";

        //Charger POS
        if ($blocktype == 1) {
            $chargerposx    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $chargerposy    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $picchargerposx = (int)(($chargerposx - ($pic_leftpos * $divsize)) / $divsize);
            $picchargerposy = (int)($pic_imageheight - (($chargerposy - ($pic_toppos * $divsize)) / $divsize));
            $im1            = imagecreatefromstring(
                base64_decode(
                    '
            iVBORw0KGgoAAAANSUhEUgAAACoAAAAqCAMAAADyHTlpAAADAFBMVEVHcExF5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5
            o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o5F5o7////w/fa69tVm6qL1/vlt66br/PNf6p7+//7o/PGE7rR37ay39dOL77nM+OCh8sbB99n2/vlH5o9e6Z2c8sPk++/j++5s66Vj6qBg6p6i88dn66Nq66VY6Zq99td67a6S8L30/vjb+ul57a2C7rNW6Jiv9M5Q55
            UAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
            AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
            AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
            AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABmUTLUAAAALnRSTlMAJPkvN+wI+4DP1eoxP47t7v4GrpmcB6+Ptj7w+vMJ9Lm11DIlMI0m0DUuM/G06wfbVgAAAfxJREFUeNqNletzmzAMwGVhXg0NTbLkbtl12fvW/f
            //ya7Xy7h82Nruwa5p8yhhgPGAFrCN6VWfbOmHZEtCNkCSMUX/ZZ6S4SvkrneQbETceE66ELY/aLzXo8bru1M5Blw6IWvt9cIa8LmvkOAPbqcJU7wS5yPoJYi55PUs+tBDwmSNuYBSew69MqVRxWIV3R7BEzKySe3VsjTRrUWSNtneInu41vCNhuTZ0X27jb9VBzD+aUm
            YCPvvXyqvb4+1pBuJGv618OrddcjTgoSppLryCtRVqwn0Oqtz037tAp2ZHTKvypMqZ5ohnejJ0UEpGsW1ngR2oxjW6OtJuZGrLsOdtJ/XJGQqukPp9M5NTYJpVNKmLKVMJHnSrB+z3RaXYQ9Zpyhv12i062GXJHH7a1GhAuZjCCvWkIVV6JVrVsmotpOxQMIxbjrNUh+D
            4C9RvcFxB61rgHJqTczUAsKRlsxdDNVrfwp1JPwJEYa6+B0SZkUDr7ayrswYnank9rw0mEtJWUCU/FbI5WXlI7BF5eefBdkpm80ewp0EgjKONWRyUp6qHLlk9b7tbdIlVxFvxhu3nOa/4EwlN7e58F9QctY73S541qSmvPYi6CODd5k84It5e/VCAy7zKFTfArYHMuiQf
            3c79rzHyF/1vVvF0E3TwcHyYJ+496Ypj5P/uAmtfUpJqE0AAAAASUVORK5CYII=
            '
                )
            );
            $im1            = rotate_transparent_img($im1, 0);
            $r              = $picsize;
            $im1            = imagescale($im1, $r, $r, IMG_BILINEAR_FIXED);
            imagecopy($newImage, $im1, $picchargerposx - ($r / 2), $picchargerposy - ($r / 2), 0, 0, $r, $r);
            /*echo "Charger POS\r\n";
            echo "-----------\r\n";
            echo "Charger POS X     :".($chargerposx). "\r\n";
            echo "Charger POS Y     :".($chargerposy). "\r\n";
            echo "Charger POS X PIC :".$picchargerposx. "\r\n";
            echo "Charger POS Y PIC :".$picchargerposy. "\r\n";
            */
        }

        //PICTURE
        if ($blocktype == 2) {
            $pic_zonen       = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $pic_toppos      = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $pic_leftpos     = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $pic_imageheight = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $pic_imagewidth  = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $newImage        = imagecreatetruecolor($pic_imagewidth, $pic_imageheight);
            for ($y = $pic_imageheight; $y > 0; $y--) {
                for ($x = 0; $x < $pic_imagewidth; $x++) {
                    $color = null;
                    $pixel = ord($data[$i++]);

                    // Hintergrund
                    if ($pixel == 0) {
                        $color = imagecolorallocate($newImage, 0, 0, 255);
                    } // ist es eine Flache
                    elseif (($pixel >> 1 & 0x03) == 3) {
                        $zone  = $pixel >> 3;
                        $color = imagecolorallocate($newImage, $zone * 8, 255 - ($zone * 4), 255 - ($zone * 8));
                    } // Wand
                    elseif (($pixel >> 0 & 0x01) == 1) {
                        $color = imagecolorallocate($newImage, 0, 0, 0);
                    } // Hinderniss
                    else {
                        $color = imagecolorallocate($newImage, 128, 128, 128);
                    }


                    imagesetpixel($newImage, $x, $y, $color);
                }
            }
            $newImage        = imagescale($newImage, $pic_imagewidth * $multi, $pic_imageheight * $multi, IMG_BILINEAR_FIXED);
            $pic_toppos      = $pic_toppos * $multi;
            $pic_leftpos     = $pic_leftpos * $multi;
            $pic_imageheight = $pic_imageheight * $multi;
            $pic_imagewidth  = $pic_imagewidth * $multi;
            /*      echo "Bild\r\n";
            echo "-----------\r\n";

            echo "Zonen             :".$pic_zonen. "\r\n";
            echo "Toppos            :".$pic_toppos. "\r\n";
            echo "Leftpos           :".$pic_leftpos. "\r\n";
            echo "Imageheight       :".$pic_imageheight. "\r\n";
            echo "Imagewidth        :".$pic_imagewidth. "\r\n";
            */
        }

        //Vakuumpfad
        if ($blocktype == 3) {
            $pointlengths = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $pointsize    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $angle        = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);

            $xold = ord($data[$i++]) | (ord($data[$i++]) << 8);
            $yold = ord($data[$i++]) | (ord($data[$i++]) << 8);

            for ($pointlength = 1; $pointlength < $pointlengths; $pointlength++) {
                $x               = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $y               = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $pointlengthxold = (int)(($xold - ($pic_leftpos * $divsize)) / $divsize);
                $pointlengthyold = (int)($pic_imageheight - (($yold - ($pic_toppos * $divsize)) / $divsize));
                $pointlengthx    = (int)(($x - ($pic_leftpos * $divsize)) / $divsize);
                $pointlengthy    = (int)($pic_imageheight - (($y - ($pic_toppos * $divsize)) / $divsize));
                $xold            = $x;
                $yold            = $y;

                $color = imagecolorallocate($newImage, 255, 255, 255);
                imageline($newImage, $pointlengthxold, $pointlengthyold, $pointlengthx, $pointlengthy, $color);
            }

            echo "Vakuumpfad\r\n";
            echo "----------\r\n";
            echo "PointLengths      :" . $pointlengths . "\r\n";
            echo "PointSize         :" . $pointsize . "\r\n";
            echo "Winkel            :" . $angle . "\r\n";
        }

        //predicted goto path
        if ($blocktype == 5) {
            $pointlengths = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $pointsize    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $angle        = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);

            $xold = ord($data[$i++]) | (ord($data[$i++]) << 8);
            $yold = ord($data[$i++]) | (ord($data[$i++]) << 8);

            for ($pointlength = 1; $pointlength < $pointlengths; $pointlength++) {
                $x               = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $y               = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $pointlengthxold = (int)(($xold - ($pic_leftpos * $divsize)) / $divsize);
                $pointlengthyold = (int)($pic_imageheight - (($yold - ($pic_toppos * $divsize)) / $divsize));
                $pointlengthx    = (int)(($x - ($pic_leftpos * $divsize)) / $divsize);
                $pointlengthy    = (int)($pic_imageheight - (($y - ($pic_toppos * $divsize)) / $divsize));
                $xold            = $x;
                $yold            = $y;

                $color = imagecolorallocate($newImage, 255, 255, 0);
                imageline($newImage, $pointlengthxold, $pointlengthyold, $pointlengthx, $pointlengthy, $color);
            }
            /*echo "vorhergesagter Goto-Pfad\r\n";
            echo "----------\r\n";
            echo "PointLengths      :".$pointlengths. "\r\n";
            echo "PointSize         :".$pointsize. "\r\n";
            echo "Winkel            :".$angle. "\r\n";
            */
        }

        //Clean zonw
        if ($blocktype == 6) {
            $counters = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            for ($counter = 0; $counter < $counters; $counter++) {
                $x1 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $y1 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $x2 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $y2 = ord($data[$i++]) | (ord($data[$i++]) << 8);


                $x1pic = (int)(($x1 - ($pic_leftpos * $divsize)) / $divsize);
                $y1pic = (int)($pic_imageheight - (($y1 - ($pic_toppos * $divsize)) / $divsize));
                $x2pic = (int)(($x2 - ($pic_leftpos * $divsize)) / $divsize);
                $y2pic = (int)($pic_imageheight - (($y2 - ($pic_toppos * $divsize)) / $divsize));

                imagefilledrectangle($newImage, $x1pic, $y1pic, $x2pic, $y2pic, imagecolorallocatealpha($newImage, 0, 255, 0, 64));
                ImageRectangle($newImage, $x1pic, $y1pic, $x2pic, $y2pic, imagecolorallocate($newImage, 0, 255, 0));
            }
            echo "Zonen\r\n";
            echo "----------\r\n";
            echo "Conter      :" . $counter . "\r\n";
        }
        //Target Position
        if ($blocktype == 7) {
            $targetxpos    = ord($data[$i++]) | (ord($data[$i++]) << 8);
            $targetypos    = ord($data[$i++]) | (ord($data[$i++]) << 8);
            $pictargetxpos = (int)(($targetxpos - ($pic_leftpos * $divsize)) / $divsize);
            $pictargetypos = (int)($pic_imageheight - (($targetypos - ($pic_toppos * $divsize)) / $divsize));

            $coordinates    = [];
            $coordinates[0] = $pictargetxpos + ($picsize / 2);            // Point 1 x
            $coordinates[1] = $pictargetypos + ($picsize / 2);          // Point 1 y
            $coordinates[2] = $pictargetxpos - ($picsize / 2);          // Point 2 x
            $coordinates[3] = $pictargetypos + ($picsize / 2);          // Point 2 y
            $coordinates[4] = $pictargetxpos;                      // Point 3 x
            $coordinates[5] = $pictargetypos - ($picsize / 2);           // Point 3 y


            ImageFilledPolygon($newImage, $coordinates, 3, ImageColorAllocate($newImage, 255, 255, 0));
            /*echo "Target Position\r\n";
            echo "----- ---------\r\n";

            echo "Target X POS       :".$targetxpos. "\r\n";
            echo "Target Y POS       :".$targetypos. "\r\n";
            echo "Target X POS PIC   :".$pictargetxpos. "\r\n";
            echo "Target Y POS PIC   :".$pictargetypos. "\r\n";
            */
        }

        //Robot Position
        if ($blocktype == 8) {
            $robotxpos    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $robotypos    = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $robotangle   = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            $picrobotxpos = (int)(($robotxpos - ($pic_leftpos * $divsize)) / $divsize);
            $picrobotypos = (int)($pic_imageheight - (($robotypos - ($pic_toppos * $divsize)) / $divsize));

            $im = imagecreatefromstring(
                base64_decode(
                    '
            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
            '
                )
            );
            $im = rotate_transparent_img($im, $robotangle - 90);
            $r  = $picsize * 2;
            $im = imagescale($im, $r, $r, IMG_BILINEAR_FIXED);
            imagecopy($newImage, $im, $picrobotxpos - ($r / 2), $picrobotypos - ($r / 2), 0, 0, $r, $r);
            /*echo "Robot Position\r\n";
            echo "--------------\r\n";

            echo "Robot X POS       :".$robotxpos. "\r\n";
            echo "Robot Y POS       :".$robotypos. "\r\n";
            echo "Robot X POS PIC   :".$picrobotxpos. "\r\n";
            echo "Robot Y POS PIC   :".$picrobotypos. "\r\n";
            echo "Robot ANGLE       :".$robotangle. "\r\n";
            */
        }
        //Sperrzone Position
        if ($blocktype == 9) {
            $counters = ord($data[$i++]) | (ord($data[$i++]) << 8) | (ord($data[$i++]) << 16) | (ord($data[$i++]) << 24);
            for ($counter = 0; $counter < $counters; $counter++) {
                $x1 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $y1 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $x2 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $y2 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $x3 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $y3 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $x4 = ord($data[$i++]) | (ord($data[$i++]) << 8);
                $y4 = ord($data[$i++]) | (ord($data[$i++]) << 8);

                // X1 = X4
                // X2 = X3
                // Y1 =Y2
                // Y3 = Y4

                $x1pic = (int)(($x1 - ($pic_leftpos * $divsize)) / $divsize);
                $y1pic = (int)($pic_imageheight - (($y1 - ($pic_toppos * $divsize)) / $divsize));
                $x2pic = (int)(($x2 - ($pic_leftpos * $divsize)) / $divsize);
                $y2pic = (int)($pic_imageheight - (($y2 - ($pic_toppos * $divsize)) / $divsize));
                $x3pic = (int)(($x3 - ($pic_leftpos * $divsize)) / $divsize);
                $y3pic = (int)($pic_imageheight - (($y3 - ($pic_toppos * $divsize)) / $divsize));
                $x4pic = (int)(($x4 - ($pic_leftpos * $divsize)) / $divsize);
                $y4pic = (int)($pic_imageheight - (($y4 - ($pic_toppos * $divsize)) / $divsize));

                imagefilledrectangle($newImage, $x1pic, $y1pic, $x3pic, $y3pic, imagecolorallocatealpha($newImage, 255, 0, 0, 64));
                ImageRectangle($newImage, $x1pic, $y1pic, $x3pic, $y3pic, imagecolorallocate($newImage, 255, 0, 0));
            }
            /*echo "Nogo-Zonen\r\n";
            echo "----------\r\n";
            echo "Conter      :".$counter. "\r\n";
            */
        }
        // check the file of sha1 sum
        if ($blocktype == 1024) {
            $file     = sha1(substr($data, 0, $i));
            $filesha1 = bin2hex(substr($data, $i, 20));
            if ($file == $filesha1) {
                imagepng($newImage, 'temp.png', 9);      //imagepng() creates a PNG file from the given image.
                echo "write file\r\n";
            }
        }
        //echo "\r\n";
    }
}
