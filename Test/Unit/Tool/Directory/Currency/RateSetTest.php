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
use Magebit\McpTaxTools\Tool\Directory\Currency\RateSet;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RateSetTest extends TestCase
{
    /** @var CurrencyRateWriter&MockObject */
    private CurrencyRateWriter $rateWriter;

    private RateSet $tool;

    protected function setUp(): void
    {
        $this->rateWriter = $this->createMock(CurrencyRateWriter::class);
        $this->tool = new RateSet($this->rateWriter);
    }

    public function testSetIsWriteGatedAndRequiresConfirmation(): void
    {
        $this->assertSame('directory.currency.rate.set', $this->tool->getName());
        $this->assertSame(WriteMode::WRITE, $this->tool->getWriteMode());
        $this->assertTrue($this->tool->getConfirmationRequired());
        $this->assertSame('Magento_CurrencySymbol::currency_rates', $this->tool->getUnderlyingAclResource());
        $this->assertSame('Magebit_McpTaxTools::tool_directory_currency_rate_set', $this->tool->getAclResource());
    }

    public function testExecuteNormalizesRatesAndDelegatesToWriter(): void
    {
        $this->rateWriter->method('getRates')
            ->with('USD', ['EUR', 'GBP'])
            ->willReturn(['EUR' => 0.7067, 'GBP' => null]);
        $this->rateWriter->expects($this->once())
            ->method('save')
            ->with(['USD' => ['EUR' => 0.91, 'GBP' => 1.2]]);

        $result = $this->tool->execute([
            'currency_from' => 'USD',
            'rates' => ['EUR' => 0.91, 'GBP' => 1.2],
        ]);

        $this->assertSame(
            [
                'currency_from' => 'USD',
                'rate_count' => 2,
                'before' => ['EUR' => 0.7067, 'GBP' => null],
            ],
            $result->getAuditSummary()
        );
    }

    public function testLowercaseCurrencyCodesReachTheWriterUppercased(): void
    {
        $this->rateWriter->expects($this->once())
            ->method('getRates')
            ->with('USD', ['EUR'])
            ->willReturn(['EUR' => 0.7067]);
        $this->rateWriter->expects($this->once())
            ->method('save')
            ->with(['USD' => ['EUR' => 0.85]]);

        $result = $this->tool->execute([
            'currency_from' => 'usd',
            'rates' => ['eur' => 0.85],
        ]);

        $this->assertSame(
            [
                'currency_from' => 'USD',
                'rate_count' => 1,
                'before' => ['EUR' => 0.7067],
            ],
            $result->getAuditSummary()
        );
    }

    public function testTargetCodesThatCollideOnceUppercasedAreRejected(): void
    {
        $this->rateWriter->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->tool->execute(['currency_from' => 'USD', 'rates' => ['eur' => 0.85, 'EUR' => 0.9]]);
    }

    public function testSchemaConstrainsCurrencyCodesToThreeLetters(): void
    {
        $schema = $this->tool->getInputSchema();

        $properties = $schema['properties'];
        $this->assertIsArray($properties);

        $from = $properties['currency_from'];
        $this->assertIsArray($from);
        $this->assertSame('^[A-Za-z]{3}$', $from['pattern']);

        $rates = $properties['rates'];
        $this->assertIsArray($rates);
        $propertyNames = $rates['propertyNames'];
        $this->assertIsArray($propertyNames);
        $this->assertSame('^[A-Za-z]{3}$', $propertyNames['pattern']);
    }

    public function testNonNumericRateIsRejectedRatherThanSkipped(): void
    {
        $this->rateWriter->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->tool->execute(['currency_from' => 'USD', 'rates' => ['EUR' => 'not-a-number']]);
    }

    public function testZeroRateIsRejected(): void
    {
        $this->rateWriter->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->tool->execute(['currency_from' => 'USD', 'rates' => ['EUR' => 0]]);
    }

    public function testNegativeRateIsRejected(): void
    {
        $this->rateWriter->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->tool->execute(['currency_from' => 'USD', 'rates' => ['EUR' => -0.5]]);
    }

    public function testMissingCurrencyFromIsRejected(): void
    {
        $this->rateWriter->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->tool->execute(['rates' => ['EUR' => 0.91]]);
    }

    public function testEmptyRatesMapIsRejected(): void
    {
        $this->rateWriter->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->tool->execute(['currency_from' => 'USD', 'rates' => []]);
    }
}
