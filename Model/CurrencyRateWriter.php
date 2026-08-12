<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model;

use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Isolates the one model dependency currency-rate writes need: core ships no
 * service contract for saving rates, so this wraps what the admin controller uses.
 */
class CurrencyRateWriter
{
    /**
     * @param CurrencyFactory $currencyFactory
     */
    public function __construct(
        private readonly CurrencyFactory $currencyFactory
    ) {
    }

    /**
     * @param array $rates From-currency code => to-currency code => rate.
     * @phpstan-param array<string, array<string, float>> $rates
     * @return void
     * @throws LocalizedException
     */
    public function save(array $rates): void
    {
        if ($rates === []) {
            throw new LocalizedException(__('No exchange rates supplied.'));
        }

        $this->currencyFactory->create()->saveRates($rates);
    }

    /**
     * Reads `directory_currency_rate` in one query, matching codes case-insensitively as
     * the table's collation does. A pair with no stored row maps to `null`.
     *
     * @param string $from
     * @param array $toCodes
     * @phpstan-param array<int, string> $toCodes
     * @return array<string, float|null>
     */
    public function getRates(string $from, array $toCodes): array
    {
        $stored = $this->currencyFactory->create()->getCurrencyRates($from, $toCodes);
        $stored = is_array($stored) ? array_change_key_case($stored, CASE_UPPER) : [];

        $rates = [];
        foreach ($toCodes as $toCode) {
            $rate = $stored[strtoupper($toCode)] ?? null;
            $rates[$toCode] = is_numeric($rate) ? (float) $rate : null;
        }

        return $rates;
    }
}
