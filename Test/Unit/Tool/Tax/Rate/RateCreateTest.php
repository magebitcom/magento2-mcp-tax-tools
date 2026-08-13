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
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RateCreateTest extends TestCase
{
    private const VALID_ARGUMENTS = [
        'code' => 'MCP-TEST-LV-21',
        'tax_country_id' => 'LV',
        'rate' => 21,
    ];

    private RateCreate $tool;

    protected function setUp(): void
    {
        $this->tool = new RateCreate(
            $this->createMock(TaxRateRepositoryInterface::class),
            $this->createMock(TaxRateInterfaceFactory::class),
            new RateFieldApplier()
        );
    }

    /**
     * @param TaxRateInterface&MockObject $rate
     * @return RateCreate
     */
    private function toolCreating(TaxRateInterface $rate): RateCreate
    {
        $factory = $this->createMock(TaxRateInterfaceFactory::class);
        $factory->method('create')->willReturn($rate);

        $repository = $this->createMock(TaxRateRepositoryInterface::class);
        $repository->method('save')->willReturn($rate);

        return new RateCreate($repository, $factory, new RateFieldApplier());
    }

    /**
     * @return TaxRateInterface&MockObject
     */
    private function savedRate(): TaxRateInterface
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->method('getId')->willReturn(4);
        $rate->method('getCode')->willReturn('MCP-TEST-LV-21');
        $rate->method('getTaxCountryId')->willReturn('LV');
        $rate->method('getRate')->willReturn(21.0);

        return $rate;
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

    /**
     * Magento rejects a rate with no postcode, so the schema's "optional, `*` means
     * all postcodes" only holds if the tool supplies that wildcard itself.
     *
     * @return void
     */
    public function testOmittedPostcodeDefaultsToWildcard(): void
    {
        $rate = $this->savedRate();
        $rate->expects($this->once())->method('setTaxPostcode')->with('*');

        $this->toolCreating($rate)->execute(self::VALID_ARGUMENTS);
    }

    public function testExplicitPostcodeIsNotReplacedByTheDefault(): void
    {
        $rate = $this->savedRate();
        $rate->expects($this->once())->method('setTaxPostcode')->with('LV-1010');

        $this->toolCreating($rate)->execute(self::VALID_ARGUMENTS + ['tax_postcode' => 'LV-1010']);
    }

    /**
     * A range builds its own postcode in beforeSave(), so defaulting here would
     * fight it.
     *
     * @return void
     */
    public function testZipRangeCreateDoesNotGetTheWildcardDefault(): void
    {
        $rate = $this->savedRate();
        $rate->expects($this->never())->method('setTaxPostcode');

        $this->toolCreating($rate)->execute(self::VALID_ARGUMENTS + [
            'zip_is_range' => true,
            'zip_from' => 10000,
            'zip_to' => 19999,
        ]);
    }
}
