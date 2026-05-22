<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores uploaded research publication files under the persistent
 * var/storage directory, separate from lesson media.
 */
final class PublicationStorage
{
    private readonly string $directory;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir)
    {
        $this->directory = $projectDir.'/var/storage/uploads/publications';
    }

    public function store(UploadedFile $file): string
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o777, true);
        }

        $name = bin2hex(random_bytes(8)).'.'.($file->guessExtension() ?: 'bin');
        $file->move($this->directory, $name);

        return $name;
    }

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
