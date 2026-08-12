<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\Rate;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpTaxTools\Model\Search\RateSearchCriteriaBuilder;
use Magebit\McpTaxTools\Tool\Tax\Rate\RateList;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RateListTest extends TestCase
{
    private RateList $tool;

    protected function setUp(): void
    {
        $this->tool = new RateList(
            $this->createMock(TaxRateRepositoryInterface::class),
            $this->createMock(RateSearchCriteriaBuilder::class),
            $this->createMock(ResolverPipeline::class),
            []
        );
    }

    public function testToolIdentity(): void
    {
        $this->assertSame('tax.rate.list', $this->tool->getName());
        $this->assertSame('Magebit_McpTaxTools::tool_tax_rate_list', $this->tool->getAclResource());
        $this->assertSame(WriteMode::READ, $this->tool->getWriteMode());
        $this->assertSame('Magento_Tax::manage_tax', $this->tool->getUnderlyingAclResource());
    }

    public function testSchemaDeclaresGeographicFilters(): void
    {
        $schema = $this->tool->getInputSchema();
        $this->assertIsArray($schema['properties']);
        $filters = $schema['properties']['filters'];
        $this->assertIsArray($filters);
        $props = $filters['properties'];
        $this->assertIsArray($props);
        foreach (['tax_country_id', 'tax_region_id', 'tax_postcode', 'code', 'rate'] as $key) {
            $this->assertArrayHasKey($key, $props);
        }
    }
}
