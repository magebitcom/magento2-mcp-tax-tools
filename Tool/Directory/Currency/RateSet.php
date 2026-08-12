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
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Model\CurrencyRateWriter;
use Magento\Framework\Exception\LocalizedException;

class RateSet implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'directory.currency.rate.set';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_directory_currency_rate_set';

    private const CODE_PATTERN = '^[A-Za-z]{3}$';

    /**
     * @param CurrencyRateWriter $rateWriter
     */
    public function __construct(
        private readonly CurrencyRateWriter $rateWriter
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
        return 'Set Currency Rates';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Manually set exchange rates, as the admin Currency Rates page does. Use this for fixed-rate '
            . 'stores or to correct a bad import. Wrong rates mis-price every order in the affected currency.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->string('currency_from', fn (StringBuilder $s) => $s
                ->minLength(3)
                ->maxLength(3)
                ->pattern(self::CODE_PATTERN)
                ->description('Source currency code, normally the base currency.')
                ->required())
            ->rawProperty(
                'rates',
                [
                    'type' => 'object',
                    'propertyNames' => ['pattern' => self::CODE_PATTERN],
                    'additionalProperties' => ['type' => 'number'],
                    'description' => 'Target currency code => rate, e.g. {"EUR": 0.91}.',
                ],
                true
            )
            ->toArray();
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
        return WriteMode::WRITE;
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationRequired(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        $rawFrom = $arguments['currency_from'] ?? null;
        if (!is_string($rawFrom) || $rawFrom === '') {
            throw new LocalizedException(__('Parameter "currency_from" is required and must be a string.'));
        }
        $from = strtoupper($rawFrom);

        $rawRates = $arguments['rates'] ?? null;
        if (!is_array($rawRates) || $rawRates === []) {
            throw new LocalizedException(__('Parameter "rates" is required and must be a non-empty object.'));
        }

        $rates = $this->normalize($rawRates);
        $before = $this->rateWriter->getRates($from, array_keys($rates));

        $this->rateWriter->save([$from => $rates]);

        $payload = [
            'currency_from' => $from,
            'rates' => $rates,
            'saved' => true,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode currency rate result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'currency_from' => $from,
                'rate_count' => count($rates),
                'before' => $before,
            ]
        );
    }

    /**
     * Currency codes are uppercased here because `directory_currency_rate` is stored and
     * matched case-insensitively — an unnormalised key would write one row but read another.
     *
     * @param array $rates
     * @phpstan-param array<int|string, mixed> $rates
     * @return array<string, float>
     * @throws LocalizedException
     */
    private function normalize(array $rates): array
    {
        $out = [];
        foreach ($rates as $to => $rate) {
            if (!is_string($to) || $to === '') {
                throw new LocalizedException(__('Each key of "rates" must be a non-empty currency code.'));
            }
            if (!is_numeric($rate) || (float) $rate <= 0.0) {
                throw new LocalizedException(__('Rate for "%1" must be a positive number.', $to));
            }
            $code = strtoupper($to);
            if (array_key_exists($code, $out)) {
                throw new LocalizedException(
                    __('Currency code "%1" is listed more than once in "rates".', $code)
                );
            }
            $out[$code] = (float) $rate;
        }
        return $out;
    }
}
