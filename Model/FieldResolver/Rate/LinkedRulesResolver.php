<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model\FieldResolver\Rate;

use Magebit\McpTaxTools\Api\RateFieldResolverInterface;
use Magebit\McpTaxTools\Model\RuleRateIndex;
use Magento\Tax\Api\Data\TaxRateInterface;

/**
 * Flags rates no rule references. Such a rate is configured but never charged —
 * the failure mode a rate list alone cannot show.
 */
class LinkedRulesResolver implements RateFieldResolverInterface
{
    public const KEY = 'linked_rules';

    /**
     * @param RuleRateIndex $ruleRateIndex
     */
    public function __construct(
        private readonly RuleRateIndex $ruleRateIndex
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return 30;
    }

    /**
     * @inheritDoc
     */
    public function resolve(TaxRateInterface $rate, array $args): array
    {
        unset($args);

        $rateId = $rate->getId();
        $rules = $rateId !== null ? $this->ruleRateIndex->rulesForRate((int) $rateId) : [];

        return [
            'rules' => $rules,
            'orphaned' => $rules === [],
        ];
    }
}
