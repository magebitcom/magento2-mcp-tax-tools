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
use Magento\Tax\Api\Data\TaxRuleInterfaceFactory;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RuleCreateTest extends TestCase
{
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
}
