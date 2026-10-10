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
    public const SCENES = ['tea', 'tea-path', 'beach', 'hills', 'river', 'food', 'mangrove'];

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
}
