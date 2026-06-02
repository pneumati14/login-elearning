<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Structured postal address embedded in another entity. Country is the
 * ISO 3166-1 alpha-2 code (e.g. "HU", "DE"). All fields nullable — a
 * partial address (e.g. country + city only) is allowed.
 */
#[ORM\Embeddable]
class Address
{
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function isEmpty(): bool
    {
        return null === $this->country && null === $this->city
            && null === $this->postalCode && null === $this->street;
    }

    /**
     * @return array{country: ?string, city: ?string, postalCode: ?string, street: ?string}
     */
    public function toArray(): array
    {
        return [
            'country' => $this->country,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'street' => $this->street,
        ];
    }
}
