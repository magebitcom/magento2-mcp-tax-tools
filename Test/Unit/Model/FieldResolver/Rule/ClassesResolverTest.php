<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model\FieldResolver\Rule;

use Magebit\McpTaxTools\Model\FieldResolver\Rule\ClassesResolver;
use Magebit\McpTaxTools\Model\TaxClassNameIndex;
use Magento\Tax\Api\Data\TaxRuleInterface;
use PHPUnit\Framework\TestCase;

class ClassesResolverTest extends TestCase
{
    public function testClassIdsAreResolvedToNames(): void
    {
        $index = $this->createMock(TaxClassNameIndex::class);
        $index->method('nameFor')->willReturnMap([
            [2, 'Taxable Goods'],
            [3, 'Retail Customer'],
        ]);

        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getCustomerTaxClassIds')->willReturn([3]);
        $rule->method('getProductTaxClassIds')->willReturn([2]);

        $this->assertSame(
            [
                'customer' => [['id' => 3, 'name' => 'Retail Customer']],
                'product' => [['id' => 2, 'name' => 'Taxable Goods']],
            ],
            (new ClassesResolver($index))->resolve($rule, [])
        );
    }

    public function testMissingClassNameResolvesToNullWithoutDroppingTheId(): void
    {
        $index = $this->createMock(TaxClassNameIndex::class);
        $index->method('nameFor')->willReturn(null);

        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getCustomerTaxClassIds')->willReturn([99]);
        $rule->method('getProductTaxClassIds')->willReturn([]);

        $resolved = (new ClassesResolver($index))->resolve($rule, []);

        $this->assertSame([['id' => 99, 'name' => null]], $resolved['customer']);
        $this->assertSame([], $resolved['product']);
    }

    public function testNullClassIdsResolveToEmptyArraysRatherThanThrowing(): void
    {
        $index = $this->createMock(TaxClassNameIndex::class);

        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getCustomerTaxClassIds')->willReturn(null);
        $rule->method('getProductTaxClassIds')->willReturn(null);

        $this->assertSame(
            ['customer' => [], 'product' => []],
            (new ClassesResolver($index))->resolve($rule, [])
        );
    }
}
