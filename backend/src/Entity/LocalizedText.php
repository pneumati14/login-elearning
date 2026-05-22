<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A piece of admin-entered text stored in multiple languages.
 *
 * English is the required base; other languages are optional and fall
 * back to English when not provided. Embedded into content entities, so
 * a field "title" becomes the columns "title_en" and "title_hu".
 */
#[ORM\Embeddable]
class LocalizedText
{
    #[ORM\Column(type: Types::TEXT)]
    private string $en = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $hu = null;

    public function __construct(string $en = '', ?string $hu = null)
    {
        $this->en = $en;
        $this->setHu($hu);
    }

    public function getEn(): string
    {
        return $this->en;
    }

    public function setEn(string $en): static
    {
        $this->en = $en;

        return $this;
    }

    public function getHu(): ?string
    {
        return $this->hu;
    }

    public function setHu(?string $hu): static
    {
        // Treat an empty Hungarian value as "not provided" so it falls back.
        $this->hu = (null !== $hu && '' !== $hu) ? $hu : null;

        return $this;
    }

    /**
     * The text in the requested locale, falling back to English.
     */
    public function for(string $locale): string
    {
        if ('hu' === $locale && null !== $this->hu) {
            return $this->hu;
        }

        return $this->en;
    }

    /**
     * @return array{en: string, hu: string|null}
     */
    public function toArray(): array
    {
        return ['en' => $this->en, 'hu' => $this->hu];
    }
}
