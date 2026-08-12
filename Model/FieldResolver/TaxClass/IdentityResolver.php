<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model\FieldResolver\TaxClass;

use Magebit\McpTaxTools\Api\TaxClassFieldResolverInterface;
use Magento\Tax\Api\Data\TaxClassInterface;

class IdentityResolver implements TaxClassFieldResolverInterface
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
    public function resolve(TaxClassInterface $taxClass, array $args): array
    {
        unset($args);
        return [
            'id' => $taxClass->getClassId() !== null ? (int) $taxClass->getClassId() : null,
            'name' => (string) $taxClass->getClassName(),
            'type' => (string) $taxClass->getClassType(),
        ];
    }
}
