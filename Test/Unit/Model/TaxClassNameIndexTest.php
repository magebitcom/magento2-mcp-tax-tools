<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model;

use Magebit\McpTaxTools\Model\TaxClassNameIndex;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Tax\Api\Data\TaxClassInterface;
use Magento\Tax\Api\Data\TaxClassSearchResultsInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use PHPUnit\Framework\TestCase;

class TaxClassNameIndexTest extends TestCase
{
    public function testNamesAreResolvedAndTheRepositoryIsQueriedOnlyOnce(): void
    {
        $productClass = $this->createMock(TaxClassInterface::class);
        $productClass->method('getClassId')->willReturn(2);
        $productClass->method('getClassName')->willReturn('Taxable Goods');

        $customerClass = $this->createMock(TaxClassInterface::class);
        $customerClass->method('getClassId')->willReturn(3);
        $customerClass->method('getClassName')->willReturn('Retail Customer');

        $results = $this->createMock(TaxClassSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$productClass, $customerClass]);

        $repository = $this->createMock(TaxClassRepositoryInterface::class);
        $repository->expects($this->once())->method('getList')->willReturn($results);

        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $index = new TaxClassNameIndex($repository, $criteriaBuilder);

        $this->assertSame('Taxable Goods', $index->nameFor(2));
        $this->assertSame('Retail Customer', $index->nameFor(3));
        $this->assertSame('Taxable Goods', $index->nameFor(2));
    }

    public function testUnknownClassIdResolvesToNull(): void
    {
        $results = $this->createMock(TaxClassSearchResultsInterface::class);
        $results->method('getItems')->willReturn([]);

        $repository = $this->createMock(TaxClassRepositoryInterface::class);
        $repository->method('getList')->willReturn($results);

        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $index = new TaxClassNameIndex($repository, $criteriaBuilder);

        $this->assertNull($index->nameFor(999));
    }
}
