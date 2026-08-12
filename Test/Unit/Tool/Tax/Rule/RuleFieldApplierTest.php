<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\Rule;

use Magebit\McpTaxTools\Tool\Tax\Rule\RuleFieldApplier;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\Data\TaxRuleInterface;
use PHPUnit\Framework\TestCase;

class RuleFieldApplierTest extends TestCase
{
    private RuleFieldApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new RuleFieldApplier();
    }

    public function testIdArraysAreCoercedToIntegers(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->expects($this->once())->method('setTaxRateIds')->with([1, 2]);
        $rule->expects($this->once())->method('setCustomerTaxClassIds')->with([3]);
        $rule->expects($this->once())->method('setProductTaxClassIds')->with([2]);

        $this->applier->apply($rule, [
            'tax_rate_ids' => ['1', 2],
            'customer_tax_class_ids' => [3],
            'product_tax_class_ids' => [2],
        ]);
    }

    public function testOmittedFieldsAreLeftUntouched(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->expects($this->never())->method('setTaxRateIds');
        $rule->expects($this->never())->method('setCode');
        $rule->expects($this->never())->method('setPriority');
        $rule->expects($this->never())->method('setPosition');
        $rule->expects($this->never())->method('setCalculateSubtotal');
        $rule->expects($this->never())->method('setCustomerTaxClassIds');
        $rule->expects($this->never())->method('setProductTaxClassIds');

        $this->applier->apply($rule, []);
    }

    public function testEmptyRateIdArrayIsRejected(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rule, ['tax_rate_ids' => []]);
    }

    public function testFalsyButSuppliedValuesAreStillApplied(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->expects($this->once())->method('setPriority')->with(0);
        $rule->expects($this->once())->method('setPosition')->with(0);
        $rule->expects($this->once())->method('setCalculateSubtotal')->with(false);

        $this->applier->apply($rule, [
            'priority' => 0,
            'position' => 0,
            'calculate_subtotal' => false,
        ]);
    }

    public function testExplicitNullCodeIsRejected(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->expects($this->never())->method('setCode');

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rule, ['code' => null]);
    }

    public function testExplicitNullPriorityIsRejected(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->expects($this->never())->method('setPriority');

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rule, ['priority' => null]);
    }

    public function testExplicitNullIdArrayIsRejected(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->expects($this->never())->method('setTaxRateIds');

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rule, ['tax_rate_ids' => null]);
    }
}
