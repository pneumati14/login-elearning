<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores and serves uploaded lesson media (videos, PDFs) on disk under
 * the persistent var/storage directory.
 */
final class MediaStorage
{
    private readonly string $directory;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir)
    {
        $this->directory = $projectDir.'/var/storage/uploads/lessons';
    }

    /**
     * Moves an uploaded file into storage and returns its stored name.
     */
    public function store(UploadedFile $file): string
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o777, true);
        }

        $name = bin2hex(random_bytes(8)).'.'.($file->guessExtension() ?: 'bin');
        $file->move($this->directory, $name);

        return $name;
    }

    /**
     * Removes a stored file; a null or missing name is ignored.
     */
    public function delete(?string $name): void
    {
        if (null === $name) {
            return;
        }

        $path = $this->path($name);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function path(string $name): string
    {
        return $this->directory.'/'.$name;
    }
}
