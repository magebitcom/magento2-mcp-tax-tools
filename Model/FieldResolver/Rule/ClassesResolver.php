<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model\FieldResolver\Rule;

use Magebit\McpTaxTools\Api\RuleFieldResolverInterface;
use Magebit\McpTaxTools\Model\TaxClassNameIndex;
use Magento\Tax\Api\Data\TaxRuleInterface;

/**
 * Resolves the rule's class ids to names so a reader can judge the wiring
 * without a second lookup.
 */
class ClassesResolver implements RuleFieldResolverInterface
{
    public const KEY = 'classes';

    /**
     * @param TaxClassNameIndex $classNameIndex
     */
    public function __construct(
        private readonly TaxClassNameIndex $classNameIndex
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
    public function resolve(TaxRuleInterface $rule, array $args): array
    {
        unset($args);
        return [
            'customer' => $this->describe($rule->getCustomerTaxClassIds() ?? []),
            'product' => $this->describe($rule->getProductTaxClassIds() ?? []),
        ];
    }

    /**
     * @param array $classIds
     * @phpstan-param array<int|string, mixed> $classIds
     * @return array<int, array{id: int, name: string|null}>
     */
    private function describe(array $classIds): array
    {
        $out = [];
        foreach ($classIds as $classId) {
            if (!is_numeric($classId)) {
                continue;
            }
            $id = (int) $classId;
            $out[] = ['id' => $id, 'name' => $this->classNameIndex->nameFor($id)];
        }
        return $out;
    }
}
