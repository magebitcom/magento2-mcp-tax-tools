<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Tool\Tax\Rate;

use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\Data\TaxRateInterface;

/**
 * Maps tool arguments onto a tax rate. Absent keys are left untouched so
 * update calls stay partial.
 */
class RateFieldApplier
{
    /**
     * @param TaxRateInterface $rate
     * @param array $arguments
     * @phpstan-param array<string, mixed> $arguments
     * @return TaxRateInterface
     * @throws LocalizedException
     */
    public function apply(TaxRateInterface $rate, array $arguments): TaxRateInterface
    {
        if (array_key_exists('code', $arguments)) {
            $rate->setCode($this->requireScalarString($arguments['code'], 'code'));
        }
        if (array_key_exists('tax_country_id', $arguments)) {
            $countryId = $this->requireScalarString($arguments['tax_country_id'], 'tax_country_id');
            $rate->setTaxCountryId(strtoupper($countryId));
        }
        if (array_key_exists('tax_region_id', $arguments)) {
            $rate->setTaxRegionId((int) $this->requireNumeric($arguments['tax_region_id'], 'tax_region_id'));
        }
        if (array_key_exists('rate', $arguments)) {
            $rate->setRate($this->requireNumeric($arguments['rate'], 'rate'));
        }
        if (array_key_exists('tax_postcode', $arguments)) {
            $rate->setTaxPostcode($this->requireScalarString($arguments['tax_postcode'], 'tax_postcode'));
        }

        $this->applyZipRange($rate, $arguments);

        return $rate;
    }

    /**
     * None of these fields accept `null` on the underlying model, and the schema
     * directs callers to omit a field (or pass `0`) rather than null it out.
     *
     * @param mixed $value
     * @param string $field
     * @return string
     * @throws LocalizedException
     */
    private function requireScalarString(mixed $value, string $field): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new LocalizedException(__('Parameter "%1" must be a scalar value.', $field));
    }

    /**
     * @param mixed $value
     * @param string $field
     * @return float
     * @throws LocalizedException
     */
    private function requireNumeric(mixed $value, string $field): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new LocalizedException(__('Parameter "%1" must be numeric.', $field));
    }

    /**
     * The bounds are meaningless without the range flag, so supplying one without it is
     * rejected rather than silently dropped while the caller is told the field changed.
     *
     * @param TaxRateInterface $rate
     * @param array $arguments
     * @phpstan-param array<string, mixed> $arguments
     * @return void
     * @throws LocalizedException
     */
    private function applyZipRange(TaxRateInterface $rate, array $arguments): void
    {
        $hasFlag = array_key_exists('zip_is_range', $arguments);
        $isRange = $hasFlag && (bool) $arguments['zip_is_range'];

        if (!$isRange) {
            if (array_key_exists('zip_from', $arguments) || array_key_exists('zip_to', $arguments)) {
                throw new LocalizedException(
                    __('"zip_from" and "zip_to" only apply when "zip_is_range" is true.')
                );
            }
            if ($hasFlag) {
                $rate->setZipIsRange(0);
            }
            return;
        }

        $from = $arguments['zip_from'] ?? null;
        $to = $arguments['zip_to'] ?? null;
        if (!is_numeric($from) || !is_numeric($to)) {
            throw new LocalizedException(
                __('Both "zip_from" and "zip_to" are required when "zip_is_range" is true.')
            );
        }

        $rate->setZipIsRange(1);
        $rate->setZipFrom((int) $from);
        $rate->setZipTo((int) $to);
    }
}
