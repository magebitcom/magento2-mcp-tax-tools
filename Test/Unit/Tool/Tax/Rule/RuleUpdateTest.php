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
use Magebit\McpTaxTools\Tool\Tax\Rule\RuleFieldApplier;
use Magebit\McpTaxTools\Tool\Tax\Rule\RuleUpdate;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RuleUpdateTest extends TestCase
{
    /** @var TaxRuleRepositoryInterface&MockObject */
    private TaxRuleRepositoryInterface $ruleRepository;

    private RuleUpdate $tool;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->tool = new RuleUpdate($this->ruleRepository, new RuleFieldApplier(), new RuleLinkIds());
    }

    public function testUpdateIsWriteGatedAndDoesNotRequireConfirmation(): void
    {
        $this->assertSame(WriteMode::WRITE, $this->tool->getWriteMode());
        $this->assertFalse($this->tool->getConfirmationRequired());
    }

    public function testToolIdentity(): void
    {
        $this->assertSame('tax.rule.update', $this->tool->getName());
        $this->assertSame('Magebit_McpTaxTools::tool_tax_rule_update', $this->tool->getAclResource());
        $this->assertSame('Magento_Tax::manage_tax', $this->tool->getUnderlyingAclResource());
    }

    public function testDescriptionWarnsThatLinkedIdsAreReplacedNotMerged(): void
    {
        $this->assertStringContainsString('replaced', $this->tool->getDescription());
        $this->assertStringContainsString('not merged', $this->tool->getDescription());
    }

    public function testAuditSummaryRecordsTheLinkSetBeforeReplacingIt(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getId')->willReturn(7);
        $rule->method('getCode')->willReturn('Rule1');
        $rule->method('getTaxRateIds')->willReturn([1, 2]);
        $rule->method('getCustomerTaxClassIds')->willReturn([3]);
        $rule->method('getProductTaxClassIds')->willReturn([2]);

        $this->ruleRepository->method('get')->with(7)->willReturn($rule);
        $this->ruleRepository->method('save')->willReturnArgument(0);

        $result = $this->tool->execute(['rule_id' => 7, 'tax_rate_ids' => [3]]);

        $this->assertSame([
            'code' => 'Rule1',
            'tax_rate_ids' => [1, 2],
            'customer_tax_class_ids' => [3],
            'product_tax_class_ids' => [2],
        ], $result->getAuditSummary()['before']);
    }

    /**
     * Magento hands these back as strings after a save, where create returns ints —
     * the tool normalizes so both report the same shape.
     *
     * @return void
     */
    public function testLinkedIdsAreReturnedAsIntegers(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getId')->willReturn(7);
        $rule->method('getCode')->willReturn('Rule1');
        $rule->method('getTaxRateIds')->willReturn(['4', '5']);
        $rule->method('getCustomerTaxClassIds')->willReturn(['3']);
        $rule->method('getProductTaxClassIds')->willReturn(['2']);

        $this->ruleRepository->method('get')->with(7)->willReturn($rule);
        $this->ruleRepository->method('save')->willReturnArgument(0);

        $text = $this->tool->execute(['rule_id' => 7, 'priority' => 1])->getContent()[0]['text'];
        $this->assertIsString($text);
        $decoded = json_decode($text, true);
        $this->assertIsArray($decoded);

        $this->assertSame([4, 5], $decoded['tax_rate_ids']);
        $this->assertSame([3], $decoded['customer_tax_class_ids']);
        $this->assertSame([2], $decoded['product_tax_class_ids']);
    }
}
