<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Model;

use Magebit\McpTaxTools\Model\CurrencyRateWriter;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CurrencyRateWriterTest extends TestCase
{
    public function testRatesArePassedToTheCurrencyModel(): void
    {
        $currency = $this->createMock(Currency::class);
        $currency->expects($this->once())
            ->method('saveRates')
            ->with(['USD' => ['EUR' => 0.91]]);

        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($currency);

        (new CurrencyRateWriter($factory))->save(['USD' => ['EUR' => 0.91]]);
    }

    public function testEmptyRateSetIsRejected(): void
    {
        $factory = $this->createMock(CurrencyFactory::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(LocalizedException::class);
        (new CurrencyRateWriter($factory))->save([]);
    }

    public function testGetRatesResolvesTheWholeSetInOneCallAndNullsMissingPairs(): void
    {
        $currency = $this->createMock(Currency::class);
        $currency->expects($this->once())
            ->method('getCurrencyRates')
            ->with('USD', ['EUR', 'GBP'])
            ->willReturn(['EUR' => '0.706700000000']);

        $factory = $this->createMock(CurrencyFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with([])
            ->willReturn($currency);

        $rates = (new CurrencyRateWriter($factory))->getRates('USD', ['EUR', 'GBP']);

        $this->assertSame(['EUR', 'GBP'], array_keys($rates));
        $this->assertSame(0.7067, $rates['EUR']);
        $this->assertNull($rates['GBP']);
    }

    public function testGetRatesMatchesStoredPairsRegardlessOfRequestedCodeCasing(): void
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('getCurrencyRates')->willReturn(['EUR' => '0.706700000000']);

        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($currency);

        $rates = (new CurrencyRateWriter($factory))->getRates('USD', ['eur']);

        $this->assertSame(['eur'], array_keys($rates));
        $this->assertSame(0.7067, $rates['eur']);
    }

    public function testGetRatesMatchesStoredPairsWhenTheStoredKeyIsLowercase(): void
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('getCurrencyRates')->willReturn(['eur' => '0.706700000000']);

        $factory = $this->createMock(CurrencyFactory::class);
        $factory->method('create')->willReturn($currency);

        $rates = (new CurrencyRateWriter($factory))->getRates('USD', ['EUR']);

        $this->assertSame(0.7067, $rates['EUR']);
    }
}
