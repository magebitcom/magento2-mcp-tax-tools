<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model\FieldResolver\Rule;

use Magebit\McpTaxTools\Api\RuleFieldResolverInterface;
use Magento\Tax\Api\Data\TaxRuleInterface;

class IdentityResolver implements RuleFieldResolverInterface
{
    public const KEY = 'identity';

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
        return 10;
    }

    /**
     * @inheritDoc
     */
    public function resolve(TaxRuleInterface $rule, array $args): array
    {
        unset($args);
        return [
            'id' => $rule->getId() !== null ? (int) $rule->getId() : null,
            'code' => (string) $rule->getCode(),
            'priority' => (int) $rule->getPriority(),
            'position' => (int) $rule->getPosition(),
            'calculate_subtotal' => (bool) $rule->getCalculateSubtotal(),
        ];
    }
}
