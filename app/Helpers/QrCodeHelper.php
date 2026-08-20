<?php

namespace App\Helpers;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeHelper
{
    /**
     * Build QR options tuned for embedding in PDF stamps.
     * Minimal quiet zone reduces the white border so placement matches the preview box.
     */
    private static function options(int $scale = 8, bool $base64 = true): QROptions
    {
        $options = new QROptions;
        $options->outputInterface = QRGdImagePNG::class;
        $options->scale = max(4, $scale);
        $options->outputBase64 = $base64;
        // Keep a tiny quiet zone for scannability, but not the default thick white frame
        $options->addQuietzone = true;
        $options->quietzoneSize = 1;
        $options->drawLightModules = true;
        $options->bgColor = [255, 255, 255];
        $options->imageTransparent = false;

        return $options;
    }

    /**
     * Generate a PNG QR code and return a data-URI (data:image/png;base64,...).
     */
    public static function toDataUri(string $payload, int $scale = 8): string
    {
        return (new QRCode(self::options($scale, true)))->render($payload);
    }

    /**
     * Generate a PNG QR code and write it to a temporary file. Returns absolute path.
     * Optionally crops residual uniform white border so the stamp fills the target box.
     */
    public static function toTempFile(string $payload, ?string $directory = null, int $scale = 8): string
    {
        $directory = $directory ?: storage_path('app/temp');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $binary = self::toPngBinary($payload, $scale);
        $binary = self::trimWhiteBorder($binary);

        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'qr_'.uniqid('', true).'.png';
        file_put_contents($path, $binary);

        return $path;
    }

    /**
     * Return raw PNG binary bytes.
     */
    public static function toPngBinary(string $payload, int $scale = 8): string
    {
        return (new QRCode(self::options($scale, false)))->render($payload);
    }

    /**
     * Trim uniform near-white borders from a PNG so the QR fills the stamp box.
     * Keeps a 1px safety pad so modules are not clipped.
     */
    private static function trimWhiteBorder(string $pngBinary, int $pad = 1): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $pngBinary;
        }

        $src = @imagecreatefromstring($pngBinary);
        if ($src === false) {
            return $pngBinary;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 4 || $h < 4) {
            imagedestroy($src);

            return $pngBinary;
        }

        $isLight = static function (int $rgb): bool {
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            // near-white / light gray
            return $r > 245 && $g > 245 && $b > 245;
        };

        $minX = $w;
        $minY = $h;
        $maxX = 0;
        $maxY = 0;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (! $isLight(imagecolorat($src, $x, $y))) {
                    if ($x < $minX) {
                        $minX = $x;
                    }
                    if ($y < $minY) {
                        $minY = $y;
                    }
                    if ($x > $maxX) {
                        $maxX = $x;
                    }
                    if ($y > $maxY) {
                        $maxY = $y;
                    }
                }
            }
        }

        // No dark pixels found — return original
        if ($maxX < $minX || $maxY < $minY) {
            imagedestroy($src);

            return $pngBinary;
        }

        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($w - 1, $maxX + $pad);
        $maxY = min($h - 1, $maxY + $pad);

        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;

        // Already tight enough
        if ($cropW >= $w - 2 && $cropH >= $h - 2) {
            imagedestroy($src);

            return $pngBinary;
        }

        // Make square crop so PDF stamp stays square
        $side = max($cropW, $cropH);
        $dst = imagecreatetruecolor($side, $side);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);

        $dstX = (int) floor(($side - $cropW) / 2);
        $dstY = (int) floor(($side - $cropH) / 2);
        imagecopy($dst, $src, $dstX, $dstY, $minX, $minY, $cropW, $cropH);

        ob_start();
        imagepng($dst);
        $out = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $out !== false ? $out : $pngBinary;
    }
}
