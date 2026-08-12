<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Tool\Tax\Rate;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\BooleanBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\NumberBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxRateRepositoryInterface;

class RateUpdate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.rate.update';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_rate_update';

    /**
     * @param TaxRateRepositoryInterface $rateRepository
     * @param RateFieldApplier $applier
     */
    public function __construct(
        private readonly TaxRateRepositoryInterface $rateRepository,
        private readonly RateFieldApplier $applier
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
        return 'Update Tax Rate';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Update an existing tax rate. Only the fields you supply change. Changing a percentage '
            . 'takes effect immediately for new orders.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('rate_id', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->description('Tax rate id.')
                ->required())
            ->string('code', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('Unique rate code, e.g. `DE-19`.'))
            ->string('tax_country_id', fn (StringBuilder $s) => $s
                ->minLength(2)
                ->maxLength(2)
                ->description('Two-letter ISO country code.'))
            ->number('rate', fn (NumberBuilder $n) => $n
                ->minimum(0)
                ->description('Rate percentage, e.g. 19 for 19%.'))
            ->integer('tax_region_id', fn (IntegerBuilder $i) => $i
                ->minimum(0)
                ->description('Region id; 0 or omitted means all regions.'))
            ->string('tax_postcode', fn (StringBuilder $s) => $s
                ->description('Postcode pattern; `*` means all postcodes.'))
            ->boolean('zip_is_range', fn (BooleanBuilder $b) => $b
                ->description('Treat the postcode scope as a numeric range.'))
            ->integer('zip_from', fn (IntegerBuilder $i) => $i
                ->description('Range start; required when `zip_is_range` is true, rejected otherwise.'))
            ->integer('zip_to', fn (IntegerBuilder $i) => $i
                ->description('Range end; required when `zip_is_range` is true, rejected otherwise.'))
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
        return 'Magento_Tax::manage_tax';
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
        return false;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        $rawId = $arguments['rate_id'] ?? null;
        if (!is_numeric($rawId)) {
            throw new LocalizedException(__('Parameter "rate_id" is required and must be an integer.'));
        }
        $rateId = (int) $rawId;

        $rate = $this->rateRepository->get($rateId);
        $before = [
            'code' => (string) $rate->getCode(),
            'tax_country_id' => (string) $rate->getTaxCountryId(),
            'tax_region_id' => $rate->getTaxRegionId(),
            'tax_postcode' => $rate->getTaxPostcode(),
            'rate' => (float) $rate->getRate(),
            'zip_is_range' => $rate->getZipIsRange(),
            'zip_from' => $rate->getZipFrom(),
            'zip_to' => $rate->getZipTo(),
        ];

        $saved = $this->rateRepository->save($this->applier->apply($rate, $arguments));

        $payload = [
            'id' => $saved->getId() !== null ? (int) $saved->getId() : null,
            'code' => (string) $saved->getCode(),
            'country_id' => (string) $saved->getTaxCountryId(),
            'percent' => (float) $saved->getRate(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax rate as JSON.'));
        }

        $changed = array_values(array_diff(array_keys($arguments), ['rate_id']));

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'rate_id' => $rateId,
                'changed_fields' => $changed,
                'before' => $before,
            ]
        );
    }
}
