<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Directory\Currency;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Model\CurrencyRateWriter;
use Magebit\McpTaxTools\Tool\Directory\Currency\RateImport;
use Magento\Directory\Model\Currency\Import\Config;
use Magento\Directory\Model\Currency\Import\Factory;
use Magento\Directory\Model\Currency\Import\ImportInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RateImportTest extends TestCase
{
    /**
     * @param array<string, array<string, float|null>> $fetched
     * @param array<int, string> $messages
     * @return RateImport
     */
    private function toolReturning(array $fetched, array $messages): RateImport
    {
        return $this->toolWithWriter($fetched, $messages, $this->createMock(CurrencyRateWriter::class));
    }

    /**
     * @param array<string, array<string, float|null>> $fetched
     * @param array<int, string> $messages
     * @param CurrencyRateWriter&MockObject $rateWriter
     * @return RateImport
     */
    private function toolWithWriter(array $fetched, array $messages, CurrencyRateWriter $rateWriter): RateImport
    {
        $service = $this->createMock(ImportInterface::class);
        $service->method('fetchRates')->willReturn($fetched);
        $service->method('getMessages')->willReturn($messages);

        $factory = $this->createMock(Factory::class);
        $factory->method('create')->willReturn($service);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('currencyconverterapi');

        return new RateImport(
            $factory,
            $this->createMock(Config::class),
            $scopeConfig,
            $rateWriter
        );
    }

    /**
     * @param RateImport $tool
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function decode(RateImport $tool, array $arguments = []): array
    {
        $text = $tool->execute($arguments)->getContent()[0]['text'];
        $this->assertIsString($text);

        $decoded = json_decode($text, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function testImportIsWriteGatedAndRequiresConfirmation(): void
    {
        $tool = $this->toolReturning(['USD' => ['EUR' => 0.91]], []);

        $this->assertSame('directory.currency.rate.import', $tool->getName());
        $this->assertSame(WriteMode::WRITE, $tool->getWriteMode());
        $this->assertTrue($tool->getConfirmationRequired());
        $this->assertSame('Magento_CurrencySymbol::currency_rates', $tool->getUnderlyingAclResource());
        $this->assertSame('Magebit_McpTaxTools::tool_directory_currency_rate_import', $tool->getAclResource());
    }

    public function testWarningsFromAPartialFetchAreSurfaced(): void
    {
        $tool = $this->toolReturning(
            ['USD' => ['EUR' => 0.91, 'GBP' => null]],
            ['We can\'t retrieve a rate from the service for GBP.']
        );

        $decoded = $this->decode($tool);

        $this->assertNotEmpty($decoded['warnings']);
        $this->assertTrue($decoded['partial']);
    }

    public function testACleanFetchReportsNoWarningsAndIsNotPartial(): void
    {
        $tool = $this->toolReturning(['USD' => ['EUR' => 0.91]], []);

        $decoded = $this->decode($tool);

        $this->assertSame([], $decoded['warnings']);
        $this->assertFalse($decoded['partial']);
    }

    public function testAPartialFetchIsNeverSaved(): void
    {
        $rateWriter = $this->createMock(CurrencyRateWriter::class);
        $rateWriter->expects($this->never())->method('save');

        $tool = $this->toolWithWriter(
            ['USD' => ['EUR' => 0.91, 'GBP' => null]],
            ['We can\'t retrieve a rate from the service for GBP.'],
            $rateWriter
        );

        $decoded = $this->decode($tool);

        $this->assertFalse($decoded['saved']);
    }

    public function testACleanFetchIsSavedWithNormalizedFloatsByDefault(): void
    {
        $rateWriter = $this->createMock(CurrencyRateWriter::class);
        $rateWriter->expects($this->once())
            ->method('save')
            ->with(['USD' => ['EUR' => 0.91]]);

        $tool = $this->toolWithWriter(['USD' => ['EUR' => 0.91]], [], $rateWriter);

        $decoded = $this->decode($tool);

        $this->assertTrue($decoded['saved']);
    }

    public function testSaveFalseArgumentPreviewsWithoutWriting(): void
    {
        $rateWriter = $this->createMock(CurrencyRateWriter::class);
        $rateWriter->expects($this->never())->method('save');

        $tool = $this->toolWithWriter(['USD' => ['EUR' => 0.91]], [], $rateWriter);

        $decoded = $this->decode($tool, ['save' => false]);

        $this->assertFalse($decoded['saved']);
    }

    public function testNullRateWithNoWarningsIsStillPartialAndNotSaved(): void
    {
        $rateWriter = $this->createMock(CurrencyRateWriter::class);
        $rateWriter->expects($this->never())->method('save');

        $tool = $this->toolWithWriter(['USD' => ['EUR' => 0.91, 'GBP' => null]], [], $rateWriter);

        $decoded = $this->decode($tool);

        $this->assertTrue($decoded['partial']);
        $this->assertFalse($decoded['saved']);
    }

    public function testAnEmptyFetchIsReportedAsPartialInsteadOfThrowing(): void
    {
        $rateWriter = $this->createMock(CurrencyRateWriter::class);
        $rateWriter->expects($this->never())->method('save');

        $tool = $this->toolWithWriter([], [], $rateWriter);

        $decoded = $this->decode($tool);

        $this->assertTrue($decoded['partial']);
        $this->assertFalse($decoded['saved']);
    }

    public function testAFetchWithNoUsableRatesIsReportedAsPartialInsteadOfThrowing(): void
    {
        $rateWriter = $this->createMock(CurrencyRateWriter::class);
        $rateWriter->expects($this->never())->method('save');

        $tool = $this->toolWithWriter(['USD' => []], [], $rateWriter);

        $decoded = $this->decode($tool);

        $this->assertTrue($decoded['partial']);
        $this->assertFalse($decoded['saved']);
    }

    public function testZeroRateWithNoWarningsIsPartialAndNotSaved(): void
    {
        $rateWriter = $this->createMock(CurrencyRateWriter::class);
        $rateWriter->expects($this->never())->method('save');

        $tool = $this->toolWithWriter(['USD' => ['EUR' => 0.91, 'GBP' => 0.0]], [], $rateWriter);

        $decoded = $this->decode($tool);

        $this->assertTrue($decoded['partial']);
        $this->assertFalse($decoded['saved']);
    }
}
