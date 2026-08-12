<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model\Search;

use Magebit\McpTaxTools\Model\Search\TaxClassSearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class TaxClassSearchCriteriaBuilderTest extends TestCase
{
    private TaxClassSearchCriteriaBuilder $builder;

    protected function setUp(): void
    {
        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $this->builder = new TaxClassSearchCriteriaBuilder(
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

    public function testClassTypeFilterIsAccepted(): void
    {
        $criteria = $this->builder->build(['filters' => ['class_type' => 'PRODUCT']]);
        $this->assertInstanceOf(SearchCriteria::class, $criteria);
    }
}
