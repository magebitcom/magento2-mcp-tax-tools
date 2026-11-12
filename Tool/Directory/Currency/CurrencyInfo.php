<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Tool\Directory\Currency;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Directory\Api\CurrencyInformationAcquirerInterface;
use Magento\Framework\Exception\LocalizedException;

class CurrencyInfo implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'directory.currency.info';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_directory_currency_info';

    /**
     * @param CurrencyInformationAcquirerInterface $currencyInformationAcquirer
     */
    public function __construct(
        private readonly CurrencyInformationAcquirerInterface $currencyInformationAcquirer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return 'Get Currency Information';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Read the base currency, default display currency, allowed '
            . 'currencies and current exchange rates. Magento stores no '
            . 'timestamp for exchange rates, so this cannot report when they '
            . 'were last refreshed — call system_cron_status with '
            . 'job_code=currency_rates_update to check whether the scheduled '
            . 'import is running.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getAclResource(): string
    {
        return self::ACL_RESOURCE;
    }

    /**
     * @inheritDoc
     */
    public function getUnderlyingAclResource(): ?string
    {
        return 'Magento_CurrencySymbol::currency_rates';
    }

    /**
     * @inheritDoc
     */
    public function getWriteMode(): WriteMode
    {
        return WriteMode::READ;
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationRequired(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        unset($arguments);

        $info = $this->currencyInformationAcquirer->getCurrencyInfo();

        $rates = [];
        foreach ($info->getExchangeRates() ?? [] as $rate) {
            $rates[] = [
                'currency_to' => (string) $rate->getCurrencyTo(),
                'rate' => (float) $rate->getRate(),
            ];
        }

        $payload = [
            'base_currency_code' => (string) $info->getBaseCurrencyCode(),
            'default_display_currency_code' => (string) $info->getDefaultDisplayCurrencyCode(),
            'available_currency_codes' => array_values(
                array_map('strval', $info->getAvailableCurrencyCodes() ?? [])
            ),
            'exchange_rates' => $rates,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode currency information as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'base_currency_code' => (string) $info->getBaseCurrencyCode(),
                'rate_count' => count($rates),
            ]
        );
    }
}
