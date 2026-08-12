<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\TaxClass;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpTaxTools\Model\Search\TaxClassSearchCriteriaBuilder;
use Magebit\McpTaxTools\Tool\Tax\TaxClass\TaxClassList;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use PHPUnit\Framework\TestCase;

class TaxClassListTest extends TestCase
{
    private TaxClassList $tool;

    protected function setUp(): void
    {
        $this->tool = new TaxClassList(
            $this->createMock(TaxClassRepositoryInterface::class),
            $this->createMock(TaxClassSearchCriteriaBuilder::class),
            $this->createMock(ResolverPipeline::class),
            []
        );
    }

    public function testToolIdentity(): void
    {
        $this->assertSame('tax.class.list', $this->tool->getName());
        $this->assertSame('Magebit_McpTaxTools::tool_tax_class_list', $this->tool->getAclResource());
        $this->assertSame(WriteMode::READ, $this->tool->getWriteMode());
        $this->assertFalse($this->tool->getConfirmationRequired());
    }

    public function testUnderlyingAclIsTheAdminTaxResource(): void
    {
        $this->assertSame('Magento_Tax::manage_tax', $this->tool->getUnderlyingAclResource());
    }

    public function testSchemaDeclaresClassTypeFilter(): void
    {
        $schema = $this->tool->getInputSchema();
        $this->assertIsArray($schema['properties']);
        $filters = $schema['properties']['filters'];
        $this->assertIsArray($filters);
        $props = $filters['properties'];
        $this->assertIsArray($props);
        $this->assertArrayHasKey('class_type', $props);
        $this->assertArrayHasKey('class_name', $props);
    }
}
