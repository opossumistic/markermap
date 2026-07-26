<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Stores optional location photos under public/uploads/locations.
 *
 * Normalizes every upload: EXIF orientation, max edge length, strip metadata via re-encode.
 * Prefers WebP; falls back to JPEG if GD lacks WebP support (shared hosting).
 */
final class LocationImageStorage
{
    private const MAX_EDGE = 1600;
    private const WEBP_QUALITY = 82;
    private const JPEG_QUALITY = 85;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/locations')]
        private readonly string $directory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @return string Web path relative to public/, e.g. uploads/locations/abc.webp
     */
    public function store(UploadedFile $file): string
    {
        if (!\extension_loaded('gd')) {
            throw new \RuntimeException('Bildverarbeitung benötigt die PHP-Extension gd.');
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Upload-Verzeichnis konnte nicht angelegt werden.');
        }

        $pathname = $file->getPathname();
        $image = $this->createImage($pathname, $file->getMimeType() ?? '');
        if ($image === false) {
            throw new \RuntimeException('Bild konnte nicht gelesen werden.');
        }

        $image = $this->applyExifOrientation($image, $pathname);
        $image = $this->scaleDown($image);

        $base = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        $safe = $this->slugger->slug($base !== '' ? $base : 'foto')->lower()->toString();
        $useWebp = $this->supportsWebp();
        $extension = $useWebp ? 'webp' : 'jpg';
        $filename = sprintf('%s-%s.%s', $safe !== '' ? $safe : 'foto', bin2hex(random_bytes(8)), $extension);
        $target = $this->directory.'/'.$filename;

        $ok = $useWebp
            ? imagewebp($image, $target, self::WEBP_QUALITY)
            : $this->writeJpeg($image, $target);

        imagedestroy($image);

        if ($ok === false || !is_file($target)) {
            throw new \RuntimeException('Bild konnte nicht gespeichert werden.');
        }

        return 'uploads/locations/'.$filename;
    }

    /**
     * @return \GdImage|false
     */
    private function createImage(string $pathname, string $mime): mixed
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($pathname),
            'image/png' => @imagecreatefrompng($pathname),
            'image/webp' => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($pathname) : false,
            default => false,
        };
    }

    /**
     * Mutates or replaces $image; caller must not keep a stale handle.
     *
     * @param \GdImage $image
     *
     * @return \GdImage
     */
    private function applyExifOrientation(\GdImage $image, string $pathname): \GdImage
    {
        if (!\function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($pathname);
        if ($exif === false || !isset($exif['Orientation'])) {
            return $image;
        }

        return match ((int) $exif['Orientation']) {
            2 => $this->flipInPlace($image, \IMG_FLIP_HORIZONTAL),
            3 => $this->rotateReplace($image, 180),
            4 => $this->flipInPlace($image, \IMG_FLIP_VERTICAL),
            5 => $this->rotateReplace($this->flipInPlace($image, \IMG_FLIP_HORIZONTAL), 270),
            6 => $this->rotateReplace($image, 270),
            7 => $this->rotateReplace($this->flipInPlace($image, \IMG_FLIP_HORIZONTAL), 90),
            8 => $this->rotateReplace($image, 90),
            default => $image,
        };
    }

    /**
     * @param \GdImage $image
     *
     * @return \GdImage
     */
    private function flipInPlace(\GdImage $image, int $mode): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    /**
     * @param \GdImage $image
     *
     * @return \GdImage
     */
    private function rotateReplace(\GdImage $image, float $degrees): \GdImage
    {
        $rotated = imagerotate($image, $degrees, 0);
        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * @param \GdImage $image
     *
     * @return \GdImage
     */
    private function scaleDown(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $edge = max($width, $height);
        if ($edge <= self::MAX_EDGE) {
            return $image;
        }

        $scale = self::MAX_EDGE / $edge;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        if ($dst === false) {
            return $image;
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
        }
        imagealphablending($dst, true);
        imagecopyresampled($dst, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagesavealpha($dst, true);
        imagedestroy($image);

        return $dst;
    }

    /**
     * @param \GdImage $image
     */
    private function writeJpeg(\GdImage $image, string $target): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            return false;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        if ($white !== false) {
            imagefill($canvas, 0, 0, $white);
        }
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        $ok = imagejpeg($canvas, $target, self::JPEG_QUALITY);
        imagedestroy($canvas);

        return $ok;
    }

    private function supportsWebp(): bool
    {
        if (!\function_exists('imagewebp')) {
            return false;
        }

        $info = gd_info();

        return !empty($info['WebP Support']);
    }
}
