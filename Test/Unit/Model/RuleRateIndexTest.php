<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model;

use Magebit\McpTaxTools\Model\RuleRateIndex;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\Data\TaxRuleSearchResultsInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RuleRateIndexTest extends TestCase
{
    private function indexWithRules(TaxRuleInterface ...$rules): RuleRateIndex
    {
        $results = $this->createMock(TaxRuleSearchResultsInterface::class);
        $results->method('getItems')->willReturn($rules);

        $repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $repository->method('getList')->willReturn($results);

        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        return new RuleRateIndex($repository, $criteriaBuilder);
    }

    public function testARateReferencedByARuleReportsThatRule(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getId')->willReturn(1);
        $rule->method('getCode')->willReturn('Rule1');
        $rule->method('getTaxRateIds')->willReturn([3]);

        $this->assertSame(
            [['id' => 1, 'code' => 'Rule1']],
            $this->indexWithRules($rule)->rulesForRate(3)
        );
    }

    public function testAnOrphanedRateReportsAnEmptyList(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getId')->willReturn(1);
        $rule->method('getCode')->willReturn('Rule1');
        $rule->method('getTaxRateIds')->willReturn([3]);

        $this->assertSame([], $this->indexWithRules($rule)->rulesForRate(1));
        $this->assertSame([], $this->indexWithRules($rule)->rulesForRate(2));
    }

    public function testARateSharedByTwoRulesReportsBoth(): void
    {
        $first = $this->createMock(TaxRuleInterface::class);
        $first->method('getId')->willReturn(1);
        $first->method('getCode')->willReturn('Rule1');
        $first->method('getTaxRateIds')->willReturn([3]);

        $second = $this->createMock(TaxRuleInterface::class);
        $second->method('getId')->willReturn(2);
        $second->method('getCode')->willReturn('Rule2');
        $second->method('getTaxRateIds')->willReturn([3, 4]);

        $this->assertSame(
            [['id' => 1, 'code' => 'Rule1'], ['id' => 2, 'code' => 'Rule2']],
            $this->indexWithRules($first, $second)->rulesForRate(3)
        );
    }

    public function testTheRepositoryIsQueriedOnlyOnce(): void
    {
        $rule = $this->createMock(TaxRuleInterface::class);
        $rule->method('getId')->willReturn(1);
        $rule->method('getCode')->willReturn('Rule1');
        $rule->method('getTaxRateIds')->willReturn([3]);

        $results = $this->createMock(TaxRuleSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$rule]);

        $repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $repository->expects($this->once())->method('getList')->willReturn($results);

        $criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('create')->willReturn(new SearchCriteria());

        $index = new RuleRateIndex($repository, $criteriaBuilder);

        $this->assertSame([['id' => 1, 'code' => 'Rule1']], $index->rulesForRate(3));
        $this->assertSame([], $index->rulesForRate(999));
        $this->assertSame([['id' => 1, 'code' => 'Rule1']], $index->rulesForRate(3));
    }
}
