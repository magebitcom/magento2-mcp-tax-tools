<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\Rule;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Tool\Tax\Rule\RuleCreate;
use Magebit\McpTaxTools\Tool\Tax\Rule\RuleFieldApplier;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\Data\TaxRuleInterfaceFactory;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RuleCreateTest extends TestCase
{
    private const REQUIRED = [
        'code' => 'Retail',
        'tax_rate_ids' => [1],
        'customer_tax_class_ids' => [3],
        'product_tax_class_ids' => [2],
    ];

    private RuleCreate $tool;

    protected function setUp(): void
    {
        $this->tool = new RuleCreate(
            $this->createMock(TaxRuleRepositoryInterface::class),
            $this->createMock(TaxRuleInterfaceFactory::class),
            new RuleFieldApplier()
        );
    }

    public function testCreateIsWriteGatedAndDoesNotRequireConfirmation(): void
    {
        $this->assertSame(WriteMode::WRITE, $this->tool->getWriteMode());
        $this->assertFalse($this->tool->getConfirmationRequired());
    }

    public function testToolIdentity(): void
    {
        $this->assertSame('tax.rule.create', $this->tool->getName());
        $this->assertSame('Magebit_McpTaxTools::tool_tax_rule_create', $this->tool->getAclResource());
        $this->assertSame('Magento_Tax::manage_tax', $this->tool->getUnderlyingAclResource());
    }

    public function testPriorityAndPositionDefaultToZeroWhenOmitted(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->expects($this->once())->method('setPriority')->with(0);
        $rule->expects($this->once())->method('setPosition')->with(0);

        $this->createTool($rule)->execute(self::REQUIRED);
    }

    public function testExplicitPriorityAndPositionAreKept(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->expects($this->once())->method('setPriority')->with(2);
        $rule->expects($this->once())->method('setPosition')->with(3);

        $this->createTool($rule)->execute(self::REQUIRED + ['priority' => 2, 'position' => 3]);
    }

    public function testSchemaSaysPriorityAndPositionDefaultToZero(): void
    {
        $schema = $this->tool->getInputSchema();
        $this->assertIsArray($schema['properties']);
        foreach (['priority', 'position'] as $field) {
            $property = $schema['properties'][$field];
            $this->assertIsArray($property);
            $this->assertIsString($property['description']);
            $this->assertStringContainsString('Defaults to 0.', $property['description']);
        }
        $required = $schema['required'] ?? [];
        $this->assertIsArray($required);
        $this->assertNotContains('priority', $required);
        $this->assertNotContains('position', $required);
    }

    /**
     * @param TaxRuleInterface $rule
     * @return RuleCreate
     */
    private function createTool(TaxRuleInterface $rule): RuleCreate
    {
        $factory = $this->createMock(TaxRuleInterfaceFactory::class);
        $factory->method('create')->willReturn($rule);
        $repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($rule)->willReturn($rule);

        return new RuleCreate($repository, $factory, new RuleFieldApplier());
    }
}
