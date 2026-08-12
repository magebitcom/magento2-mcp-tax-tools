<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model\FieldResolver\Rate;

use Magebit\McpTaxTools\Api\RateFieldResolverInterface;
use Magento\Tax\Api\Data\TaxRateInterface;

class IdentityResolver implements RateFieldResolverInterface
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
    public function resolve(TaxRateInterface $rate, array $args): array
    {
        unset($args);
        return [
            'id' => $rate->getId() !== null ? (int) $rate->getId() : null,
            'code' => (string) $rate->getCode(),
            'percent' => (float) $rate->getRate(),
        ];
    }
}
