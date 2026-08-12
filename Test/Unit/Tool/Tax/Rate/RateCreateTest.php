<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\Rate;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Tool\Tax\Rate\RateCreate;
use Magebit\McpTaxTools\Tool\Tax\Rate\RateFieldApplier;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RateCreateTest extends TestCase
{
    private RateCreate $tool;

    protected function setUp(): void
    {
        $this->tool = new RateCreate(
            $this->createMock(TaxRateRepositoryInterface::class),
            $this->createMock(TaxRateInterfaceFactory::class),
            new RateFieldApplier()
        );
    }

    public function testCreateIsWriteGatedAndDoesNotRequireConfirmation(): void
    {
        $this->assertSame(WriteMode::WRITE, $this->tool->getWriteMode());
        $this->assertFalse($this->tool->getConfirmationRequired());
    }

    public function testToolIdentity(): void
    {
        $this->assertSame('tax.rate.create', $this->tool->getName());
        $this->assertSame('Magebit_McpTaxTools::tool_tax_rate_create', $this->tool->getAclResource());
        $this->assertSame('Magento_Tax::manage_tax', $this->tool->getUnderlyingAclResource());
    }
}
