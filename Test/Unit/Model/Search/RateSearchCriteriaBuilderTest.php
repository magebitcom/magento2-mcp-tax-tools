<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model\Search;

use Magebit\McpTaxTools\Model\Search\RateSearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class RateSearchCriteriaBuilderTest extends TestCase
{
    private RateSearchCriteriaBuilder $builder;

    protected function setUp(): void
    {
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $this->builder = new RateSearchCriteriaBuilder(
            $criteriaBuilder,
            $this->createMock(SortOrderBuilder::class)
        );
    }

    public function testUnknownFilterKeyIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->builder->build(['filters' => ['nope' => 'x']]);
    }

    public function testNonArrayFilterPayloadIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->builder->build(['filters' => 'not-an-object']);
    }

    public function testEqualsOrInFilterIsAccepted(): void
    {
        $criteria = $this->builder->build(['filters' => [RateSearchCriteriaBuilder::FIELD_REGION => 12]]);
        $this->assertInstanceOf(SearchCriteria::class, $criteria);
    }

    public function testStringFilterIsAccepted(): void
    {
        $criteria = $this->builder->build(['filters' => [RateSearchCriteriaBuilder::FIELD_COUNTRY => 'DE']]);
        $this->assertInstanceOf(SearchCriteria::class, $criteria);
    }

    public function testCountryGlobWildcardIsRoutedToLikeFilterWithPercentSubstitution(): void
    {
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with(RateSearchCriteriaBuilder::FIELD_COUNTRY, 'DE%', 'like')
            ->willReturnSelf();
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $builder = new RateSearchCriteriaBuilder(
            $criteriaBuilder,
            $this->createMock(SortOrderBuilder::class)
        );

        $builder->build(['filters' => [RateSearchCriteriaBuilder::FIELD_COUNTRY => 'DE*']]);
    }

    public function testRegionFilterIsRoutedToEqFilterNotLike(): void
    {
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with(RateSearchCriteriaBuilder::FIELD_REGION, 12, 'eq')
            ->willReturnSelf();
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $builder = new RateSearchCriteriaBuilder(
            $criteriaBuilder,
            $this->createMock(SortOrderBuilder::class)
        );

        $builder->build(['filters' => [RateSearchCriteriaBuilder::FIELD_REGION => 12]]);
    }
}
