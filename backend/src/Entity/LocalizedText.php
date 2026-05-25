<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A piece of admin-entered text stored in multiple languages.
 *
 * English is the required base; other languages are optional and fall
 * back to English when not provided. Embedded into content entities, so
 * a field "title" becomes "title_en", "title_hu", "title_az", "title_de",
 * "title_pt", "title_tr" and "title_pl".
 */
#[ORM\Embeddable]
class LocalizedText
{
    #[ORM\Column(type: Types::TEXT)]
    private string $en = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $hu = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $az = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $de = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $tr = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pl = null;

    public function __construct(
        string $en = '',
        ?string $hu = null,
        ?string $az = null,
        ?string $de = null,
        ?string $pt = null,
        ?string $tr = null,
        ?string $pl = null,
    ) {
        $this->en = $en;
        $this->setHu($hu);
        $this->setAz($az);
        $this->setDe($de);
        $this->setPt($pt);
        $this->setTr($tr);
        $this->setPl($pl);
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

    public function getAz(): ?string
    {
        return $this->az;
    }

    public function setAz(?string $az): static
    {
        $this->az = (null !== $az && '' !== $az) ? $az : null;

        return $this;
    }

    public function getDe(): ?string
    {
        return $this->de;
    }

    public function setDe(?string $de): static
    {
        $this->de = (null !== $de && '' !== $de) ? $de : null;

        return $this;
    }

    public function getPt(): ?string
    {
        return $this->pt;
    }

    public function setPt(?string $pt): static
    {
        $this->pt = (null !== $pt && '' !== $pt) ? $pt : null;

        return $this;
    }

    public function getTr(): ?string
    {
        return $this->tr;
    }

    public function setTr(?string $tr): static
    {
        $this->tr = (null !== $tr && '' !== $tr) ? $tr : null;

        return $this;
    }

    public function getPl(): ?string
    {
        return $this->pl;
    }

    public function setPl(?string $pl): static
    {
        $this->pl = (null !== $pl && '' !== $pl) ? $pl : null;

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
        if ('az' === $locale && null !== $this->az) {
            return $this->az;
        }
        if ('de' === $locale && null !== $this->de) {
            return $this->de;
        }
        if ('pt' === $locale && null !== $this->pt) {
            return $this->pt;
        }
        if ('tr' === $locale && null !== $this->tr) {
            return $this->tr;
        }
        if ('pl' === $locale && null !== $this->pl) {
            return $this->pl;
        }

        return $this->en;
    }

    /**
     * @return array{en: string, hu: string|null, az: string|null, de: string|null, pt: string|null, tr: string|null, pl: string|null}
     */
    public function toArray(): array
    {
        return [
            'en' => $this->en,
            'hu' => $this->hu,
            'az' => $this->az,
            'de' => $this->de,
            'pt' => $this->pt,
            'tr' => $this->tr,
            'pl' => $this->pl,
        ];
    }
}
