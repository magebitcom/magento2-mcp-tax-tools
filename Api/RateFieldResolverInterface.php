<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Api;

use Magebit\Mcp\Api\FieldResolverInterface;
use Magento\Tax\Api\Data\TaxRateInterface;

interface RateFieldResolverInterface extends FieldResolverInterface
{
    /**
     * @param TaxRateInterface $rate
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int|string, mixed>
     */
    public function resolve(TaxRateInterface $rate, array $args): array;
}
