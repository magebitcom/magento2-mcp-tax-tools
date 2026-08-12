<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\Rate;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Tool\Tax\Rate\RateDelete;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RateDeleteTest extends TestCase
{
    /** @var TaxRateRepositoryInterface&MockObject */
    private TaxRateRepositoryInterface $rateRepository;

    private RateDelete $tool;

    protected function setUp(): void
    {
        $this->rateRepository = $this->createMock(TaxRateRepositoryInterface::class);
        $this->tool = new RateDelete($this->rateRepository);
    }

    public function testDeleteIsWriteGatedAndRequiresConfirmation(): void
    {
        $this->assertSame(WriteMode::WRITE, $this->tool->getWriteMode());
        $this->assertTrue($this->tool->getConfirmationRequired());
    }

    public function testToolIdentity(): void
    {
        $this->assertSame('tax.rate.delete', $this->tool->getName());
        $this->assertSame('Magebit_McpTaxTools::tool_tax_rate_delete', $this->tool->getAclResource());
        $this->assertSame('Magento_Tax::manage_tax', $this->tool->getUnderlyingAclResource());
    }

    public function testDescriptionWarnsThatDeletionChangesWhatCustomersAreCharged(): void
    {
        $this->assertStringContainsString('charged', strtolower($this->tool->getDescription()));
    }

    public function testExecuteCapturesRateSnapshotBeforeDeleting(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->method('getCode')->willReturn('US-CA');
        $rate->method('getTaxCountryId')->willReturn('US');
        $rate->method('getRate')->willReturn(7.25);

        $callOrder = [];

        $this->rateRepository->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturnCallback(function () use (&$callOrder, $rate) {
                $callOrder[] = 'get';
                return $rate;
            });
        $this->rateRepository->expects($this->once())
            ->method('deleteById')
            ->with(1)
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'deleteById';
                return true;
            });

        $result = $this->tool->execute(['rate_id' => 1]);

        $this->assertSame(['get', 'deleteById'], $callOrder);
        $this->assertSame([
            'id' => 1,
            'code' => 'US-CA',
            'country_id' => 'US',
            'percent' => 7.25,
        ], $result->getAuditSummary());
    }
}
