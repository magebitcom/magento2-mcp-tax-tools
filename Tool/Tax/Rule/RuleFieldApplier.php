<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Tool\Tax\Rule;

use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\Data\TaxRuleInterface;

/**
 * Maps tool arguments onto a tax rule. Absent keys are left untouched so
 * update calls stay partial.
 */
class RuleFieldApplier
{
    /**
     * @param TaxRuleInterface $rule
     * @param array $arguments
     * @phpstan-param array<string, mixed> $arguments
     * @return TaxRuleInterface
     * @throws LocalizedException
     */
    public function apply(TaxRuleInterface $rule, array $arguments): TaxRuleInterface
    {
        if (array_key_exists('code', $arguments)) {
            $rule->setCode($this->requireScalarString($arguments['code'], 'code'));
        }
        if (array_key_exists('priority', $arguments)) {
            $rule->setPriority((int) $this->requireNumeric($arguments['priority'], 'priority'));
        }
        if (array_key_exists('position', $arguments)) {
            $rule->setPosition((int) $this->requireNumeric($arguments['position'], 'position'));
        }
        if (array_key_exists('calculate_subtotal', $arguments)) {
            $rule->setCalculateSubtotal($this->requireBool($arguments['calculate_subtotal'], 'calculate_subtotal'));
        }
        if (array_key_exists('tax_rate_ids', $arguments)) {
            $rule->setTaxRateIds($this->idList($arguments['tax_rate_ids'], 'tax_rate_ids'));
        }
        if (array_key_exists('customer_tax_class_ids', $arguments)) {
            $rule->setCustomerTaxClassIds(
                $this->idList($arguments['customer_tax_class_ids'], 'customer_tax_class_ids')
            );
        }
        if (array_key_exists('product_tax_class_ids', $arguments)) {
            $rule->setProductTaxClassIds(
                $this->idList($arguments['product_tax_class_ids'], 'product_tax_class_ids')
            );
        }

        return $rule;
    }

    /**
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
     * @param mixed $value
     * @param string $field
     * @return bool
     * @throws LocalizedException
     */
    private function requireBool(mixed $value, string $field): bool
    {
        if (is_scalar($value)) {
            return (bool) $value;
        }

        throw new LocalizedException(__('Parameter "%1" must be a scalar value.', $field));
    }

    /**
     * A rule with an empty link set silently stops applying, so refuse it here
     * rather than let it save.
     *
     * @param mixed $value
     * @param string $field
     * @return array<int, int>
     * @throws LocalizedException
     */
    private function idList(mixed $value, string $field): array
    {
        if (!is_array($value) || $value === []) {
            throw new LocalizedException(
                __('Parameter "%1" must be a non-empty array of ids.', $field)
            );
        }

        $ids = [];
        foreach ($value as $candidate) {
            if (!is_numeric($candidate)) {
                throw new LocalizedException(
                    __('Parameter "%1" must contain only numeric ids.', $field)
                );
            }
            $ids[] = (int) $candidate;
        }
        return $ids;
    }
}
