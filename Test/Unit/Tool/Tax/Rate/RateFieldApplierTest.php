<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\Rate;

use Magebit\McpTaxTools\Tool\Tax\Rate\RateFieldApplier;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\Data\TaxRateInterface;
use PHPUnit\Framework\TestCase;

class RateFieldApplierTest extends TestCase
{
    private RateFieldApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new RateFieldApplier();
    }

    public function testSuppliedFieldsAreApplied(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->expects($this->once())->method('setCode')->with('DE-19');
        $rate->expects($this->once())->method('setTaxCountryId')->with('DE');
        $rate->expects($this->once())->method('setRate')->with(19.0);
        $rate->expects($this->never())->method('setTaxPostcode');

        $this->applier->apply($rate, [
            'code' => 'DE-19',
            'tax_country_id' => 'DE',
            'rate' => 19,
        ]);
    }

    public function testOmittedFieldsAreLeftUntouched(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->expects($this->never())->method('setCode');
        $rate->expects($this->never())->method('setRate');

        $this->applier->apply($rate, []);
    }

    public function testZipRangeRequiresBothBounds(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rate, ['zip_is_range' => true, 'zip_from' => 90001]);
    }

    public function testZipBoundsWithoutTheRangeFlagAreRejectedRatherThanDiscarded(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->expects($this->never())->method('setZipFrom');
        $rate->expects($this->never())->method('setZipTo');

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rate, ['zip_from' => 90001, 'zip_to' => 96162]);
    }

    public function testZipBoundsAlongsideADisabledRangeFlagAreRejected(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->expects($this->never())->method('setZipFrom');

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rate, ['zip_is_range' => false, 'zip_from' => 90001]);
    }

    public function testDisablingTheRangeFlagAloneIsStillAccepted(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->expects($this->once())->method('setZipIsRange')->with(0);
        $rate->expects($this->never())->method('setZipFrom');

        $this->applier->apply($rate, ['zip_is_range' => false]);
    }

    public function testFalsyButSuppliedValuesAreStillApplied(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->expects($this->once())->method('setRate')->with(0.0);
        $rate->expects($this->once())->method('setTaxPostcode')->with('0');

        $this->applier->apply($rate, [
            'rate' => 0,
            'tax_postcode' => '0',
        ]);
    }

    public function testExplicitNullStringFieldIsRejected(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->expects($this->never())->method('setCode');

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rate, ['code' => null]);
    }

    public function testExplicitNullNumericFieldIsRejected(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->expects($this->never())->method('setRate');

        $this->expectException(LocalizedException::class);
        $this->applier->apply($rate, ['rate' => null]);
    }
}
