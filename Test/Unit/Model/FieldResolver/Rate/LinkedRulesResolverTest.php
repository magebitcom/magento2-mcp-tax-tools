<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model\FieldResolver\Rate;

use Magebit\McpTaxTools\Model\FieldResolver\Rate\LinkedRulesResolver;
use Magebit\McpTaxTools\Model\RuleRateIndex;
use Magento\Tax\Api\Data\TaxRateInterface;
use PHPUnit\Framework\TestCase;

class LinkedRulesResolverTest extends TestCase
{
    public function testALinkedRateIsNotFlaggedAsOrphaned(): void
    {
        $index = $this->createMock(RuleRateIndex::class);
        $index->method('rulesForRate')->with(3)->willReturn([['id' => 1, 'code' => 'Rule1']]);

        $rate = $this->createMock(TaxRateInterface::class);
        $rate->method('getId')->willReturn(3);

        $this->assertSame(
            [
                'rules' => [['id' => 1, 'code' => 'Rule1']],
                'orphaned' => false,
            ],
            (new LinkedRulesResolver($index))->resolve($rate, [])
        );
    }

    public function testAnUnlinkedRateIsFlaggedAsOrphaned(): void
    {
        $index = $this->createMock(RuleRateIndex::class);
        $index->method('rulesForRate')->with(1)->willReturn([]);

        $rate = $this->createMock(TaxRateInterface::class);
        $rate->method('getId')->willReturn(1);

        $this->assertSame(
            [
                'rules' => [],
                'orphaned' => true,
            ],
            (new LinkedRulesResolver($index))->resolve($rate, [])
        );
    }

    public function testKeyAndSortOrder(): void
    {
        $resolver = new LinkedRulesResolver($this->createMock(RuleRateIndex::class));
        $this->assertSame('linked_rules', $resolver->getKey());
        $this->assertSame(30, $resolver->getSortOrder());
    }
}
