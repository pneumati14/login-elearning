<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Per-currency settings: display rounding (decimal places, e.g. HUF 0,
 * EUR 2) and the exchange rate of one unit expressed in HUF (HUF = 1,
 * EUR ≈ 410…), used by the pipeline report to convert mixed-currency
 * totals into the selected currency. One row per supported currency.
 * Amounts are STORED at full precision — decimals only govern
 * presentation; the rate only governs report conversion.
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

    /** 1 unit of this currency in HUF; null until set (HUF itself is 1). */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 6, nullable: true)]
    private ?string $rateHuf = null;

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

    public function getRateHuf(): ?string
    {
        return $this->rateHuf;
    }

    public function setRateHuf(?string $rateHuf): static
    {
        $this->rateHuf = $rateHuf;

        return $this;
    }
}
