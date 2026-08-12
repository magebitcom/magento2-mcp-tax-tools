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
use Magebit\Mcp\Model\Tool\Schema\Builder\BooleanBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Model\CurrencyRateWriter;
use Magento\Directory\Model\Currency\Import\Config;
use Magento\Directory\Model\Currency\Import\Factory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;

class RateImport implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'directory.currency.rate.import';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_directory_currency_rate_import';

    private const XML_PATH_IMPORT_SERVICE = 'currency/import/service';

    /**
     * @param Factory $importFactory
     * @param Config $importConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param CurrencyRateWriter $rateWriter
     */
    public function __construct(
        private readonly Factory $importFactory,
        private readonly Config $importConfig,
        private readonly ScopeConfigInterface $scopeConfig,
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
        return 'Import Currency Rates';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Fetch exchange rates from the configured import service and '
            . 'save them — the same action as the admin Currency Rates '
            . 'Import button. Returns the fetched rates plus any warnings; a '
            . 'partial fetch (one currency failed) is reported rather than '
            . 'saved silently. Core Magento has no CLI equivalent.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->string('service', fn (StringBuilder $s) => $s
                ->description('Import service code. Defaults to the configured '
                    . '`currency/import/service`.'))
            ->boolean('save', fn (BooleanBuilder $b) => $b
                ->description('Persist the fetched rates. Defaults to true; set '
                    . 'false to preview without writing.'))
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
        $serviceArg = $arguments['service'] ?? null;
        $configuredService = $this->scopeConfig->getValue(self::XML_PATH_IMPORT_SERVICE);
        $service = is_string($serviceArg) && $serviceArg !== ''
            ? $serviceArg
            : (is_string($configuredService) ? $configuredService : '');

        if ($service === '') {
            throw new LocalizedException(
                __(
                    'No currency import service is configured (currency/import/service). '
                    . 'Available services: %1.',
                    implode(', ', $this->importConfig->getAvailableServices())
                )
            );
        }

        $importer = $this->importFactory->create($service);
        $fetched = $importer->fetchRates();
        $warnings = array_values(array_map('strval', $importer->getMessages()));

        $normalized = $this->normalize($fetched);
        $partial = $warnings !== [] || $normalized === [] || $this->hasInvalidRate($fetched);

        $shouldSave = !array_key_exists('save', $arguments) || (bool) $arguments['save'];
        $saved = false;
        if ($shouldSave && !$partial) {
            $this->rateWriter->save($normalized);
            $saved = true;
        }

        $payload = [
            'service' => $service,
            'rates' => $fetched,
            'warnings' => $warnings,
            'partial' => $partial,
            'saved' => $saved,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode currency import result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'service' => $service,
                'warning_count' => count($warnings),
                'partial' => $partial,
                'saved' => $saved,
            ]
        );
    }

    /**
     * A zero/negative rate is silently dropped by Magento's own saveRates(), so it
     * must be treated as invalid here too — never persisted while reporting success.
     *
     * @param array $fetched
     * @phpstan-param array<string, array<string, float|null>> $fetched
     * @return bool
     */
    private function hasInvalidRate(array $fetched): bool
    {
        foreach ($fetched as $targets) {
            if (!is_array($targets)) {
                return true;
            }
            foreach ($targets as $rate) {
                if (!$this->isValidRate($rate)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array $fetched
     * @phpstan-param array<string, array<string, float|null>> $fetched
     * @return array<string, array<string, float>>
     */
    private function normalize(array $fetched): array
    {
        $out = [];
        foreach ($fetched as $from => $targets) {
            if (!is_array($targets)) {
                continue;
            }
            foreach ($targets as $to => $rate) {
                if ($this->isValidRate($rate)) {
                    $out[$from][$to] = (float) $rate;
                }
            }
        }
        return $out;
    }

    /**
     * @param mixed $rate
     * @return bool
     */
    private function isValidRate(mixed $rate): bool
    {
        return $rate !== null && is_numeric($rate) && (float) $rate > 0.0;
    }
}
