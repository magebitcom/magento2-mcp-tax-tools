<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Test\Unit\Tool\Directory\Currency;

use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Tool\Directory\Currency\CurrencyInfo;
use Magento\Directory\Api\CurrencyInformationAcquirerInterface;
use Magento\Directory\Api\Data\CurrencyInformationInterface;
use Magento\Directory\Api\Data\ExchangeRateInterface;
use PHPUnit\Framework\TestCase;

class CurrencyInfoTest extends TestCase
{
    public function testToolIdentity(): void
    {
        $tool = new CurrencyInfo($this->createMock(CurrencyInformationAcquirerInterface::class));

        $this->assertSame('directory.currency.info', $tool->getName());
        $this->assertSame('Magebit_McpTaxTools::tool_directory_currency_info', $tool->getAclResource());
        $this->assertSame(WriteMode::READ, $tool->getWriteMode());
        $this->assertSame('Magento_CurrencySymbol::currency_rates', $tool->getUnderlyingAclResource());
    }

    public function testExchangeRatesAreReturned(): void
    {
        $rate = $this->createMock(ExchangeRateInterface::class);
        $rate->method('getCurrencyTo')->willReturn('EUR');
        $rate->method('getRate')->willReturn(0.7067);

        $info = $this->createMock(CurrencyInformationInterface::class);
        $info->method('getBaseCurrencyCode')->willReturn('USD');
        $info->method('getDefaultDisplayCurrencyCode')->willReturn('USD');
        $info->method('getAvailableCurrencyCodes')->willReturn(['USD']);
        $info->method('getExchangeRates')->willReturn([$rate]);

        $acquirer = $this->createMock(CurrencyInformationAcquirerInterface::class);
        $acquirer->method('getCurrencyInfo')->willReturn($info);

        $result = (new CurrencyInfo($acquirer))->execute([]);
        $text = $result->getContent()[0]['text'];
        $this->assertIsString($text);
        $decoded = json_decode($text, true);

        $this->assertIsArray($decoded);
        $this->assertSame('USD', $decoded['base_currency_code']);
        $this->assertSame([['currency_to' => 'EUR', 'rate' => 0.7067]], $decoded['exchange_rates']);
    }
}
