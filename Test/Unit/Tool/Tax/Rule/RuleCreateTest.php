<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\Rule;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Model\RuleLinkIds;
use Magebit\McpTaxTools\Tool\Tax\Rule\RuleCreate;
use Magebit\McpTaxTools\Tool\Tax\Rule\RuleFieldApplier;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\Data\TaxRuleInterfaceFactory;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RuleCreateTest extends TestCase
{
    private const VALID_ARGUMENTS = [
        'code' => 'MCP-TEST-RULE',
        'tax_rate_ids' => [4],
        'customer_tax_class_ids' => [5],
        'product_tax_class_ids' => [4],
    ];

    private RuleCreate $tool;

    protected function setUp(): void
    {
        $this->tool = new RuleCreate(
            $this->createMock(TaxRuleRepositoryInterface::class),
            $this->createMock(TaxRuleInterfaceFactory::class),
            new RuleFieldApplier(),
            new RuleLinkIds()
        );
    }

    /**
     * @param TaxRuleInterface&MockObject $rule
     * @return RuleCreate
     */
    private function toolCreating(TaxRuleInterface $rule): RuleCreate
    {
        $factory = $this->createMock(TaxRuleInterfaceFactory::class);
        $factory->method('create')->willReturn($rule);

        $repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $repository->method('save')->willReturn($rule);

        return new RuleCreate($repository, $factory, new RuleFieldApplier(), new RuleLinkIds());
    }

    /**
     * @param array<int, int|string> $rateIds
     * @return TaxRuleInterface&MockObject
     */
    private function savedRule(array $rateIds = [4]): TaxRuleInterface
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getId')->willReturn(2);
        $rule->method('getCode')->willReturn('MCP-TEST-RULE');
        $rule->method('getTaxRateIds')->willReturn($rateIds);
        $rule->method('getCustomerTaxClassIds')->willReturn(['5']);
        $rule->method('getProductTaxClassIds')->willReturn(['4']);

        return $rule;
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

    /**
     * Magento's validator reads an unset priority back as `""` and rejects it, so a
     * schema-optional priority is unusable unless the tool defaults it.
     *
     * @return void
     */
    public function testOmittedPriorityDefaultsToZero(): void
    {
        $rule = $this->savedRule();
        $rule->expects($this->once())->method('setPriority')->with(0);

        $this->toolCreating($rule)->execute(self::VALID_ARGUMENTS);
    }

    public function testOmittedPositionDefaultsToZero(): void
    {
        $rule = $this->savedRule();
        $rule->expects($this->once())->method('setPosition')->with(0);

        $this->toolCreating($rule)->execute(self::VALID_ARGUMENTS);
    }

    public function testExplicitPriorityIsNotReplacedByTheDefault(): void
    {
        $rule = $this->savedRule();
        $rule->expects($this->once())->method('setPriority')->with(7);

        $this->toolCreating($rule)->execute(self::VALID_ARGUMENTS + ['priority' => 7]);
    }

    public function testLinkedIdsAreReturnedAsIntegers(): void
    {
        $rule = $this->savedRule(['4', '5']);

        $text = $this->toolCreating($rule)->execute(self::VALID_ARGUMENTS)->getContent()[0]['text'];
        $this->assertIsString($text);
        $decoded = json_decode($text, true);
        $this->assertIsArray($decoded);

        $this->assertSame([4, 5], $decoded['tax_rate_ids']);
        $this->assertSame([5], $decoded['customer_tax_class_ids']);
        $this->assertSame([4], $decoded['product_tax_class_ids']);
    }
}
