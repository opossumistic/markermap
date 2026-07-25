<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Stores optional location photos under public/uploads/locations.
 */
final class LocationImageStorage
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/locations')]
        private readonly string $directory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @return string Web path relative to public/, e.g. uploads/locations/abc.jpg
     */
    public function store(UploadedFile $file): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Upload-Verzeichnis konnte nicht angelegt werden.');
        }

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = 'jpg';
        }

        $base = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        $safe = $this->slugger->slug($base !== '' ? $base : 'foto')->lower()->toString();
        $filename = sprintf('%s-%s.%s', $safe !== '' ? $safe : 'foto', bin2hex(random_bytes(8)), $extension);

        $file->move($this->directory, $filename);

        return 'uploads/locations/'.$filename;
    }
}
