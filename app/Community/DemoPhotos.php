<?php

namespace App\Community;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Simple drawn scenes for the demo community (DemoContent): no real people or places, nothing
 * downloaded. Each goes through the real upload path (Photos::store), like a member's photo.
 */
final class DemoPhotos
{
    public const SCENES = ['tea', 'tea-path', 'beach', 'hills', 'river', 'food', 'mangrove', 'rickshaw', 'paddy', 'boat', 'rain', 'books', 'city', 'doi'];

    /** A JPEG of the scene, ready for Photos::store(). */
    public static function file(string $scene): UploadedFile
    {
        $image = self::draw($scene);
        $path = tempnam(sys_get_temp_dir(), 'demo-photo').'.jpg';
        imagejpeg($image, $path, 90);

        return new UploadedFile($path, "{$scene}.jpg", 'image/jpeg', null, true);
    }

    private static function draw(string $scene): GdImage
    {
        [$w, $h] = $scene === 'tea-path' ? [1000, 1300] : [1200, 900];
        $im = imagecreatetruecolor($w, $h);
        $c = fn (string $hex) => imagecolorallocate($im, ...sscanf($hex, '#%02x%02x%02x'));

        match ($scene) {
            'tea' => self::tea($im, $w, $h, $c),
            'tea-path' => self::teaPath($im, $w, $h, $c),
            'beach' => self::beach($im, $w, $h, $c),
            'hills' => self::hills($im, $w, $h, $c),
            'river' => self::river($im, $w, $h, $c),
            'food' => self::food($im, $w, $h, $c),
            'mangrove' => self::mangrove($im, $w, $h, $c),
            'rickshaw' => self::rickshaw($im, $w, $h, $c),
            'paddy' => self::paddy($im, $w, $h, $c),
            'boat' => self::boat($im, $w, $h, $c),
            'rain' => self::rain($im, $w, $h, $c),
            'books' => self::books($im, $w, $h, $c),
            'city' => self::city($im, $w, $h, $c),
            'doi' => self::doi($im, $w, $h, $c),
        };

        // Two thirds the size: every seed sends these through the full upload path, which is the slow part.
        return imagescale($im, (int) ($w * 2 / 3), (int) ($h * 2 / 3), IMG_BICUBIC);
    }

    /** Vertical gradient between two colours over rows y0..y1. */
    private static function gradient(GdImage $im, int $w, int $y0, int $y1, string $from, string $to): void
    {
        [$r1, $g1, $b1] = sscanf($from, '#%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf($to, '#%02x%02x%02x');
        for ($y = $y0; $y < $y1; $y += 2) {
            $t = ($y - $y0) / max(1, $y1 - $y0);
            imagefilledrectangle($im, 0, $y, $w, $y + 1, imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }
    }

    /** A filled ridge along the given heights (fractions of the height), closed at the bottom. */
    private static function ridge(GdImage $im, int $w, int $h, array $heights, $colour): void
    {
        $points = [];
        foreach ($heights as $i => $y) {
            array_push($points, (int) ($w * $i / (count($heights) - 1)), (int) ($h * $y));
        }
        array_push($points, $w, $h, 0, $h);
        imagefilledpolygon($im, $points, $colour);
    }

    private static function tea(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, $h, '#bfe3f2', '#eaf6e3');
        self::ridge($im, $w, $h, [0.42, 0.36, 0.40, 0.33, 0.38], $c('#7fae6d'));
        self::ridge($im, $w, $h, [0.55, 0.50, 0.58, 0.52, 0.56], $c('#4f8f4a'));
        self::ridge($im, $w, $h, [0.72, 0.66, 0.70, 0.64, 0.69], $c('#2f6f3a'));
        // Tea rows: light curved stripes on the near hills.
        for ($y = (int) ($h * 0.62); $y < $h; $y += 26) {
            imagearc($im, (int) ($w / 2), $y + 600, $w * 2, 1200, 250, 290, $c('#86c06a'));
        }
        imagefilledellipse($im, (int) ($w * 0.8), (int) ($h * 0.16), 110, 110, $c('#fff3c4'));
    }

    private static function teaPath(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.35), '#d9eef7', '#f4f1dc');
        self::gradient($im, $w, (int) ($h * 0.35), $h, '#5f9e55', '#2d6b33');
        for ($y = (int) ($h * 0.38); $y < $h; $y += 30) {
            imagefilledrectangle($im, 0, $y, $w, $y + 8, $c('#7cb867'));
        }
        imagefilledpolygon($im, [(int) ($w * 0.47), (int) ($h * 0.36), (int) ($w * 0.53), (int) ($h * 0.36), (int) ($w * 0.75), $h, (int) ($w * 0.25), $h], $c('#c8a77a'));
    }

    private static function beach(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.5), '#7cc4ef', '#d8f0fb');
        imagefilledellipse($im, (int) ($w * 0.25), (int) ($h * 0.22), 130, 130, $c('#fff1b8'));
        self::gradient($im, $w, (int) ($h * 0.5), (int) ($h * 0.72), '#2b8fc4', '#5fbfdc');
        for ($x = 0; $x < $w; $x += 90) {
            imagearc($im, $x, (int) ($h * 0.72), 120, 30, 180, 360, $c('#ffffff'));
        }
        self::gradient($im, $w, (int) ($h * 0.72), $h, '#f0d9a6', '#e3c48a');
    }

    private static function hills(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, $h, '#f6c58f', '#fbe9d0');
        self::ridge($im, $w, $h, [0.40, 0.28, 0.36, 0.22, 0.34, 0.30], $c('#8aa7b8'));
        self::ridge($im, $w, $h, [0.55, 0.42, 0.50, 0.40, 0.48], $c('#5b7f8e'));
        self::ridge($im, $w, $h, [0.70, 0.58, 0.66, 0.60, 0.68], $c('#355d55'));
        self::ridge($im, $w, $h, [0.86, 0.80, 0.84, 0.78, 0.83], $c('#1f4136'));
    }

    private static function river(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.3), '#a8d8f0', '#e6f4f8');
        self::gradient($im, $w, (int) ($h * 0.3), $h, '#6aa65a', '#3d7a3d');
        imagefilledpolygon($im, [(int) ($w * 0.45), (int) ($h * 0.3), (int) ($w * 0.55), (int) ($h * 0.3), (int) ($w * 0.7), (int) ($h * 0.6), (int) ($w * 0.9), $h, (int) ($w * 0.2), $h, (int) ($w * 0.38), (int) ($h * 0.6)], $c('#4a9bd1'));
        foreach ([[0.12, 0.42], [0.2, 0.5], [0.82, 0.45], [0.9, 0.55]] as [$x, $y]) {
            imagefilledellipse($im, (int) ($w * $x), (int) ($h * $y), 120, 140, $c('#2e6630'));
        }
    }

    private static function food(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, $h, '#8a5a3c', '#6e4430');
        imagefilledellipse($im, (int) ($w / 2), (int) ($h / 2), 760, 660, $c('#f7f4ee'));
        imagefilledellipse($im, (int) ($w / 2), (int) ($h / 2), 640, 550, $c('#ffffff'));
        imagefilledellipse($im, (int) ($w * 0.42), (int) ($h * 0.5), 300, 240, $c('#fbfaf3')); // rice
        imagefilledellipse($im, (int) ($w * 0.6), (int) ($h * 0.42), 180, 150, $c('#e9b83c')); // dal
        imagefilledellipse($im, (int) ($w * 0.6), (int) ($h * 0.62), 220, 90, $c('#c46a2d')); // fish
        imagefilledpolygon($im, [(int) ($w * 0.69), (int) ($h * 0.62), (int) ($w * 0.75), (int) ($h * 0.57), (int) ($w * 0.75), (int) ($h * 0.67)], $c('#c46a2d'));
    }

    private static function mangrove(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.45), '#cfe0d2', '#eef3e8');
        self::ridge($im, $w, $h, [0.42, 0.30, 0.36, 0.28, 0.38, 0.32], $c('#2f5d3a'));
        self::gradient($im, $w, (int) ($h * 0.62), $h, '#6b8f8a', '#3f625e');
        for ($x = 40; $x < $w; $x += 85) {
            imagefilledrectangle($im, $x, (int) ($h * 0.45), $x + 10, (int) ($h * 0.66), $c('#4a3a2a'));
            imageline($im, $x + 5, (int) ($h * 0.6), $x - 20, (int) ($h * 0.68), $c('#4a3a2a'));
            imageline($im, $x + 5, (int) ($h * 0.6), $x + 30, (int) ($h * 0.68), $c('#4a3a2a'));
        }
    }

    /** Buildings along the bottom: [x, width, height] as fractions, in one colour. */
    private static function skyline(GdImage $im, int $w, int $h, float $base, array $blocks, $colour): void
    {
        foreach ($blocks as [$x, $bw, $bh]) {
            imagefilledrectangle($im, (int) ($w * $x), (int) ($h * ($base - $bh)), (int) ($w * ($x + $bw)), (int) ($h * $base), $colour);
        }
    }

    private static function rickshaw(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.6), '#f7d9a8', '#fbeedb');
        self::skyline($im, $w, $h, 0.62, [[0, 0.14, 0.38], [0.15, 0.1, 0.28], [0.27, 0.16, 0.44], [0.45, 0.12, 0.32], [0.6, 0.15, 0.4], [0.78, 0.1, 0.3], [0.9, 0.1, 0.36]], $c('#c99b7a'));
        self::gradient($im, $w, (int) ($h * 0.62), $h, '#8d8a85', '#5f5c58');
        imagefilledrectangle($im, 0, (int) ($h * 0.62), $w, (int) ($h * 0.66), $c('#b9b2a6')); // footpath
        // The rickshaw: two wheels, a seat, a bright hood.
        $x = (int) ($w * 0.45);
        $y = (int) ($h * 0.86);
        imagesetthickness($im, 8);
        imageellipse($im, $x - 120, $y, 170, 170, $c('#2b2b2b'));
        imageellipse($im, $x + 150, $y, 120, 120, $c('#2b2b2b'));
        imageline($im, $x - 120, $y, $x + 150, $y - 30, $c('#2b2b2b'));
        imagesetthickness($im, 1);
        imagefilledrectangle($im, $x - 190, $y - 150, $x - 30, $y - 90, $c('#2f7d6d')); // seat
        imagefilledarc($im, $x - 110, $y - 150, 230, 230, 180, 360, $c('#e03a3e'), IMG_ARC_PIE); // hood
        imagefilledarc($im, $x - 110, $y - 150, 150, 150, 180, 360, $c('#f2c14e'), IMG_ARC_PIE);
        imagefilledellipse($im, $x + 150, $y - 150, 60, 20, $c('#2b2b2b')); // handlebar
    }

    private static function paddy(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.45), '#9fd3f0', '#eaf6fb');
        imagefilledellipse($im, (int) ($w * 0.7), (int) ($h * 0.2), 120, 120, $c('#fff3c4'));
        self::ridge($im, $w, $h, [0.46, 0.44, 0.47, 0.45, 0.46], $c('#4c8a46')); // tree line
        self::gradient($im, $w, (int) ($h * 0.48), $h, '#b9d65a', '#6fa83a');
        // Rows of rice, closer together towards the horizon.
        for ($i = 1; $i < 18; $i++) {
            $y = (int) ($h * (0.48 + 0.52 * ($i / 18) ** 1.6));
            imageline($im, 0, $y, $w, $y, $c('#5d9430'));
        }
        imagefilledrectangle($im, (int) ($w * 0.16), (int) ($h * 0.36), (int) ($w * 0.24), (int) ($h * 0.47), $c('#a0522d')); // hut
        imagefilledpolygon($im, [(int) ($w * 0.14), (int) ($h * 0.37), (int) ($w * 0.2), (int) ($h * 0.3), (int) ($w * 0.26), (int) ($h * 0.37)], $c('#c9a86a'));
    }

    private static function boat(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.5), '#f6b98a', '#fde3c8');
        imagefilledellipse($im, (int) ($w * 0.5), (int) ($h * 0.5), 220, 220, $c('#f08a4b'));
        self::gradient($im, $w, (int) ($h * 0.5), $h, '#5d8fb0', '#2f5c7a');
        for ($y = (int) ($h * 0.55); $y < $h; $y += 40) {
            imageline($im, (int) ($w * 0.35), $y, (int) ($w * 0.65), $y, $c('#f3a46b')); // the sun in the water
        }
        $x = (int) ($w * 0.3);
        $y = (int) ($h * 0.68);
        imagefilledpolygon($im, [$x - 220, $y - 30, $x + 220, $y - 30, $x + 160, $y + 40, $x - 160, $y + 40], $c('#3b2a1e')); // hull
        imagefilledpolygon($im, [$x - 90, $y - 30, $x + 90, $y - 30, $x + 60, $y - 110, $x - 60, $y - 110], $c('#6b4a2e')); // cabin
        imagefilledrectangle($im, $x + 120, $y - 260, $x + 128, $y - 30, $c('#3b2a1e')); // mast
        imagefilledpolygon($im, [$x + 128, $y - 250, $x + 128, $y - 60, $x + 260, $y - 70], $c('#e8d9b5')); // sail
    }

    private static function rain(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.6), '#7c8a96', '#b8c2c9');
        self::skyline($im, $w, $h, 0.6, [[0, 0.2, 0.3], [0.22, 0.12, 0.42], [0.36, 0.18, 0.26], [0.56, 0.14, 0.36], [0.72, 0.28, 0.3]], $c('#5d6a73'));
        self::gradient($im, $w, (int) ($h * 0.6), $h, '#7e93a0', '#56707f'); // flooded street
        for ($y = (int) ($h * 0.64); $y < $h; $y += 34) {
            imageline($im, mt_rand(0, $w / 2), $y, mt_rand($w / 2, $w), $y, $c('#9fb4c0'));
        }
        mt_srand(7);
        for ($i = 0; $i < 260; $i++) {
            $x = mt_rand(0, $w);
            $y = mt_rand(0, $h);
            imageline($im, $x, $y, $x - 10, $y + 40, $c('#dfe7ec'));
        }
        imagefilledarc($im, (int) ($w * 0.7), (int) ($h * 0.62), 220, 160, 180, 360, $c('#e03a3e'), IMG_ARC_PIE); // umbrella
        imagefilledrectangle($im, (int) ($w * 0.7) - 3, (int) ($h * 0.62), (int) ($w * 0.7) + 3, (int) ($h * 0.75), $c('#2b2b2b'));
    }

    private static function books(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, $h, '#f3ead8', '#e2d3b5');
        imagefilledrectangle($im, 0, (int) ($h * 0.72), $w, $h, $c('#a87b4f')); // table
        $y = (int) ($h * 0.72);
        foreach ([['#2f7d6d', 380, 70], ['#e03a3e', 340, 60], ['#f2c14e', 400, 80], ['#3d5a99', 320, 55], ['#6b4a2e', 360, 65]] as [$colour, $bw, $bh]) {
            $x = (int) ($w * 0.36) + mt_rand(-30, 30);
            imagefilledrectangle($im, $x, $y - $bh, $x + $bw, $y, $c($colour));
            imagefilledrectangle($im, $x + 10, $y - $bh + 10, $x + $bw - 10, $y - $bh + 16, $c('#ffffff'));
            $y -= $bh + 4;
        }
        imagefilledrectangle($im, (int) ($w * 0.12), (int) ($h * 0.52), (int) ($w * 0.24), (int) ($h * 0.72), $c('#ffffff')); // mug
        imageellipse($im, (int) ($w * 0.25), (int) ($h * 0.62), 70, 90, $c('#ffffff'));
        imagefilledellipse($im, (int) ($w * 0.18), (int) ($h * 0.53), 140, 30, $c('#8a5a3c'));
    }

    private static function city(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, (int) ($h * 0.75), '#2b3a67', '#e58f65');
        self::skyline($im, $w, $h, 0.8, [[0, 0.1, 0.35], [0.08, 0.08, 0.5], [0.17, 0.12, 0.4], [0.3, 0.07, 0.62], [0.38, 0.13, 0.45], [0.52, 0.09, 0.55], [0.62, 0.14, 0.38], [0.77, 0.08, 0.6], [0.86, 0.14, 0.42]], $c('#1f2540'));
        mt_srand(11);
        for ($i = 0; $i < 140; $i++) { // lit windows
            $x = mt_rand(0, $w - 20);
            $y = mt_rand((int) ($h * 0.25), (int) ($h * 0.78));
            if (imagecolorat($im, $x, $y) === $c('#1f2540')) {
                imagefilledrectangle($im, $x, $y, $x + 12, $y + 16, $c('#f7d36b'));
            }
        }
        self::gradient($im, $w, (int) ($h * 0.8), $h, '#3a3f55', '#24283a');
    }

    private static function doi(GdImage $im, int $w, int $h, callable $c): void
    {
        self::gradient($im, $w, 0, $h, '#e9dcc5', '#cdb48f');
        $x = (int) ($w / 2);
        $y = (int) ($h * 0.55);
        imagefilledellipse($im, $x, $y + 120, 560, 120, $c('#9c6b45')); // shadow
        imagefilledpolygon($im, [$x - 260, $y - 120, $x + 260, $y - 120, $x + 200, $y + 140, $x - 200, $y + 140], $c('#b5663a')); // clay pot
        imagefilledellipse($im, $x, $y + 140, 400, 60, $c('#b5663a'));
        imagefilledellipse($im, $x, $y - 120, 520, 110, $c('#8e4a26'));
        imagefilledellipse($im, $x, $y - 118, 470, 88, $c('#f4e3c3')); // the doi
        imagefilledellipse($im, $x - 60, $y - 125, 160, 30, $c('#fbf1dc'));
    }
}
