<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Display rounding for one currency: how many decimal places the UI
 * rounds amounts to (e.g. HUF 0, EUR 2). Admin-managed; one row per
 * supported currency. Amounts are STORED at full precision — this only
 * governs presentation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'currency_setting')]
class CurrencySetting
{
    #[ORM\Id]
    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: 'smallint')]
    private int $decimals = 0;

    public function __construct(string $currency)
    {
        $this->currency = strtoupper($currency);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    /** Clamped to a sane 0–4 range. */
    public function setDecimals(int $decimals): static
    {
        $this->decimals = max(0, min(4, $decimals));

        return $this;
    }
}
