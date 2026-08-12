<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Tax\TaxClass;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Tool\Tax\TaxClass\TaxClassUpdate;
use Magento\Tax\Api\Data\TaxClassInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TaxClassUpdateTest extends TestCase
{
    /** @var TaxClassRepositoryInterface&MockObject */
    private TaxClassRepositoryInterface $taxClassRepository;

    private TaxClassUpdate $tool;

    protected function setUp(): void
    {
        $this->taxClassRepository = $this->createMock(TaxClassRepositoryInterface::class);
        $this->tool = new TaxClassUpdate($this->taxClassRepository);
    }

    public function testToolIdentityAndGating(): void
    {
        $this->assertSame('tax.class.update', $this->tool->getName());
        $this->assertSame('Magebit_McpTaxTools::tool_tax_class_update', $this->tool->getAclResource());
        $this->assertSame(WriteMode::WRITE, $this->tool->getWriteMode());
        $this->assertFalse($this->tool->getConfirmationRequired());
        $this->assertSame('Magento_Tax::manage_tax', $this->tool->getUnderlyingAclResource());
    }

    public function testSchemaDoesNotAcceptClassType(): void
    {
        $schema = $this->tool->getInputSchema();
        $this->assertIsArray($schema['properties']);
        $this->assertArrayNotHasKey('class_type', $schema['properties']);
        $this->assertIsArray($schema['required']);
        $this->assertContains('class_id', $schema['required']);
        $this->assertContains('class_name', $schema['required']);
    }

    public function testExecuteRenamesTheLoadedClassAndSaves(): void
    {
        $taxClass = $this->createMock(TaxClassInterface::class);
        $taxClass->method('getClassName')->willReturn('Taxable Goods');
        $taxClass->method('getClassType')->willReturn('PRODUCT');

        $this->taxClassRepository->expects($this->once())
            ->method('get')
            ->with(2)
            ->willReturn($taxClass);
        $taxClass->expects($this->once())
            ->method('setClassName')
            ->with('Reduced Rate');
        $this->taxClassRepository->expects($this->once())
            ->method('save')
            ->with($taxClass)
            ->willReturn('2');

        $result = $this->tool->execute(['class_id' => 2, 'class_name' => 'Reduced Rate']);

        $this->assertSame([
            'class_id' => 2,
            'changed_fields' => ['class_name'],
            'before' => ['name' => 'Taxable Goods'],
        ], $result->getAuditSummary());
    }

    public function testResponsePayloadStillReportsTheNewName(): void
    {
        $taxClass = $this->createMock(TaxClassInterface::class);
        $taxClass->method('getClassName')->willReturn('Taxable Goods');
        $taxClass->method('getClassType')->willReturn('PRODUCT');

        $this->taxClassRepository->method('get')->with(2)->willReturn($taxClass);
        $this->taxClassRepository->method('save')->willReturn('2');

        $text = $this->tool->execute(['class_id' => 2, 'class_name' => 'Reduced Rate'])->getContent()[0]['text'];
        $this->assertIsString($text);

        $this->assertSame([
            'id' => 2,
            'name' => 'Reduced Rate',
            'type' => 'PRODUCT',
        ], json_decode($text, true));
    }

    public function testThePriorNameIsCapturedBeforeTheRenameIsApplied(): void
    {
        $taxClass = $this->createMock(TaxClassInterface::class);
        $taxClass->method('getClassType')->willReturn('PRODUCT');

        $currentName = 'Taxable Goods';
        $taxClass->method('getClassName')->willReturnCallback(
            function () use (&$currentName): string {
                return $currentName;
            }
        );
        $taxClass->expects($this->once())
            ->method('setClassName')
            ->with('Reduced Rate')
            ->willReturnCallback(function (string $name) use (&$currentName, $taxClass) {
                $currentName = $name;
                return $taxClass;
            });

        $this->taxClassRepository->method('get')->with(2)->willReturn($taxClass);
        $this->taxClassRepository->method('save')->willReturn('2');

        $result = $this->tool->execute(['class_id' => 2, 'class_name' => 'Reduced Rate']);

        $this->assertSame(['name' => 'Taxable Goods'], $result->getAuditSummary()['before']);
    }
}
