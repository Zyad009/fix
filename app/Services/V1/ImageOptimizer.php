<?php

namespace App\Services\V1;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shrinks uploaded photos before they are stored: upright per EXIF, at most
 * MAX_SIDE pixels on the long edge, re-encoded as JPEG. A phone photo of
 * several MB usually ends up a few hundred KB and still readable. When GD
 * cannot read the file, the original is stored as it is.
 */
class ImageOptimizer
{
    public const MAX_SIDE = 2000;

    private const QUALITY = 80;

    /** Larger photos need more memory than a request may take; they are kept as uploaded. */
    private const MAX_PIXELS = 60_000_000;

    private const MEMORY_LIMIT = '512M';

    /**
     * @return string The stored path.
     */
    public function store(UploadedFile $file, string $directory, string $disk): string
    {
        $optimized = $this->optimize($file);

        if ($optimized === null) {
            return $file->store($directory, $disk);
        }

        $path = $directory.'/'.Str::random(40).'.jpg';
        Storage::disk($disk)->put($path, $optimized);

        return $path;
    }

    /**
     * JPEG bytes, or null to keep the original (unreadable, too large to
     * decode, or already small and needing no resize).
     */
    private function optimize(UploadedFile $file): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $path = $file->getRealPath();
        $info = @getimagesize($path);

        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        $this->raiseMemoryLimit();

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->orient($image, $path);
        }

        $resized = max(imagesx($image), imagesy($image)) > self::MAX_SIDE;
        $canvas = $this->fit($image);

        ob_start();
        imagejpeg($canvas, null, self::QUALITY);
        $jpeg = (string) ob_get_clean();

        if (! $resized && strlen($jpeg) >= $file->getSize()) {
            return null;
        }

        return $jpeg;
    }

    /**
     * Copies the image onto a white canvas no larger than MAX_SIDE, so PNG
     * transparency turns white instead of black.
     */
    private function fit(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    /**
     * Phones store photos sideways with an EXIF orientation flag; re-encoding
     * drops EXIF, so the rotation is applied to the pixels.
     */
    private function orient(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $angle = match ((int) (@exif_read_data($path)['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    private function raiseMemoryLimit(): void
    {
        $current = ini_parse_quantity((string) ini_get('memory_limit'));

        if ($current !== -1 && $current < ini_parse_quantity(self::MEMORY_LIMIT)) {
            ini_set('memory_limit', self::MEMORY_LIMIT);
        }
    }
}
