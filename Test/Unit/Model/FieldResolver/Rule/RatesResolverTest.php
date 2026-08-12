<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model\FieldResolver\Rule;

use Magebit\McpTaxTools\Model\FieldResolver\Rule\RatesResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RatesResolverTest extends TestCase
{
    public function testRateIdsAreExpandedToFullDescriptors(): void
    {
        $rate = $this->createMock(TaxRateInterface::class);
        $rate->method('getCode')->willReturn('US-CA-Rate 1');
        $rate->method('getTaxCountryId')->willReturn('US');
        $rate->method('getTaxRegionId')->willReturn(12);
        $rate->method('getTaxPostcode')->willReturn('90001');
        $rate->method('getRate')->willReturn(8.25);

        $repository = $this->createMock(TaxRateRepositoryInterface::class);
        $repository->method('get')->with(3)->willReturn($rate);

        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getTaxRateIds')->willReturn([3]);

        $resolved = (new RatesResolver($repository))->resolve($rule, []);

        $this->assertSame(
            [
                [
                    'id' => 3,
                    'code' => 'US-CA-Rate 1',
                    'country_id' => 'US',
                    'region_id' => 12,
                    'postcode' => '90001',
                    'percent' => 8.25,
                ],
            ],
            $resolved
        );
    }

    public function testMissingRateResolvesToMissingMarker(): void
    {
        $repository = $this->createMock(TaxRateRepositoryInterface::class);
        $repository->method('get')->with(404)->willThrowException(new NoSuchEntityException());

        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getTaxRateIds')->willReturn([404]);

        $resolved = (new RatesResolver($repository))->resolve($rule, []);

        $this->assertSame([['id' => 404, 'missing' => true]], $resolved);
    }

    public function testNullRateIdsResolveToEmptyArrayRatherThanThrowing(): void
    {
        $repository = $this->createMock(TaxRateRepositoryInterface::class);
        $repository->expects($this->never())->method('get');

        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getTaxRateIds')->willReturn(null);

        $this->assertSame([], (new RatesResolver($repository))->resolve($rule, []));
    }
}
