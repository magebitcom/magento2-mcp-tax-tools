<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model\FieldResolver\Rate;

use Magebit\McpTaxTools\Model\FieldResolver\Rate\LocationResolver;
use Magento\Tax\Api\Data\TaxRateInterface;
use PHPUnit\Framework\TestCase;

class LocationResolverTest extends TestCase
{
    private LocationResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LocationResolver();
    }

    public function testResolvesASinglePostcode(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->method('getTaxCountryId')->willReturn('DE');
        $rate->method('getTaxRegionId')->willReturn(0);
        $rate->method('getRegionName')->willReturn(null);
        $rate->method('getTaxPostcode')->willReturn('*');
        $rate->method('getZipIsRange')->willReturn(null);
        $rate->method('getZipFrom')->willReturn(null);
        $rate->method('getZipTo')->willReturn(null);

        $this->assertSame(
            [
                'country_id' => 'DE',
                'region_id' => 0,
                'region_name' => null,
                'postcode' => '*',
                'zip_is_range' => false,
                'zip_from' => null,
                'zip_to' => null,
            ],
            $this->resolver->resolve($rate, [])
        );
    }

    public function testResolvesAZipRange(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->method('getTaxCountryId')->willReturn('US');
        $rate->method('getTaxRegionId')->willReturn(12);
        $rate->method('getRegionName')->willReturn('California');
        $rate->method('getTaxPostcode')->willReturn(null);
        $rate->method('getZipIsRange')->willReturn(1);
        $rate->method('getZipFrom')->willReturn(90001);
        $rate->method('getZipTo')->willReturn(96162);

        $resolved = $this->resolver->resolve($rate, []);

        $this->assertTrue($resolved['zip_is_range']);
        $this->assertSame(90001, $resolved['zip_from']);
        $this->assertSame(96162, $resolved['zip_to']);
        $this->assertSame('California', $resolved['region_name']);
    }

    public function testKeyAndSortOrder(): void
    {
        $this->assertSame('location', $this->resolver->getKey());
        $this->assertSame(20, $this->resolver->getSortOrder());
    }
}
