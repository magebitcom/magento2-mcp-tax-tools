<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model\Search;

use Magebit\McpTaxTools\Model\Search\RuleSearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class RuleSearchCriteriaBuilderTest extends TestCase
{
    private RuleSearchCriteriaBuilder $builder;

    protected function setUp(): void
    {
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $this->builder = new RuleSearchCriteriaBuilder(
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
        $criteria = $this->builder->build(['filters' => [RuleSearchCriteriaBuilder::FIELD_PRIORITY => 5]]);
        $this->assertInstanceOf(SearchCriteria::class, $criteria);
    }

    public function testStringFilterIsAccepted(): void
    {
        $criteria = $this->builder->build(['filters' => [RuleSearchCriteriaBuilder::FIELD_CODE => 'Rule1']]);
        $this->assertInstanceOf(SearchCriteria::class, $criteria);
    }

    public function testCodeGlobWildcardIsRoutedToLikeFilterWithPercentSubstitution(): void
    {
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with(RuleSearchCriteriaBuilder::FIELD_CODE, 'Rule%', 'like')
            ->willReturnSelf();
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $builder = new RuleSearchCriteriaBuilder(
            $criteriaBuilder,
            $this->createMock(SortOrderBuilder::class)
        );

        $builder->build(['filters' => [RuleSearchCriteriaBuilder::FIELD_CODE => 'Rule*']]);
    }

    public function testPriorityFilterIsRoutedToEqFilterNotLike(): void
    {
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with(RuleSearchCriteriaBuilder::FIELD_PRIORITY, 5, 'eq')
            ->willReturnSelf();
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $builder = new RuleSearchCriteriaBuilder(
            $criteriaBuilder,
            $this->createMock(SortOrderBuilder::class)
        );

        $builder->build(['filters' => [RuleSearchCriteriaBuilder::FIELD_PRIORITY => 5]]);
    }

    public function testIdFilterAsArrayIsRoutedToInFilter(): void
    {
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('addFilter')
            ->with(RuleSearchCriteriaBuilder::FIELD_ID, [1, 2], 'in')
            ->willReturnSelf();
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $builder = new RuleSearchCriteriaBuilder(
            $criteriaBuilder,
            $this->createMock(SortOrderBuilder::class)
        );

        $builder->build(['filters' => [RuleSearchCriteriaBuilder::FIELD_ID => [1, 2]]]);
    }

    public function testPositionFilterIsAccepted(): void
    {
        $criteria = $this->builder->build(['filters' => [RuleSearchCriteriaBuilder::FIELD_POSITION => 1]]);
        $this->assertInstanceOf(SearchCriteria::class, $criteria);
    }
}
