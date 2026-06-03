<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores and serves uploaded media (videos, PDFs, documents) on disk
 * under the persistent var/storage directory. Files are grouped into
 * sub-directories by category (e.g. "lessons", "opportunities").
 */
final class MediaStorage
{
    private readonly string $baseDirectory;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir)
    {
        $this->baseDirectory = $projectDir.'/var/storage/uploads';
    }

    /**
     * Moves an uploaded file into the given sub-directory and returns its
     * stored name.
     */
    public function store(UploadedFile $file, string $subdir = 'lessons'): string
    {
        $directory = $this->directory($subdir);
        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        $name = bin2hex(random_bytes(8)).'.'.($file->guessExtension() ?: 'bin');
        $file->move($directory, $name);

        return $name;
    }

    /**
     * Removes a stored file; a null or missing name is ignored.
     */
    public function delete(?string $name, string $subdir = 'lessons'): void
    {
        if (null === $name) {
            return;
        }

        $path = $this->path($name, $subdir);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function path(string $name, string $subdir = 'lessons'): string
    {
        return $this->directory($subdir).'/'.$name;
    }

    private function directory(string $subdir): string
    {
        return $this->baseDirectory.'/'.$subdir;
    }
}
