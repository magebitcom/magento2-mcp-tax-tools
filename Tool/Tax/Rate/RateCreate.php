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
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;

class RateCreate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.rate.create';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_rate_create';

    /**
     * @param TaxRateRepositoryInterface $rateRepository
     * @param TaxRateInterfaceFactory $rateFactory
     * @param RateFieldApplier $applier
     */
    public function __construct(
        private readonly TaxRateRepositoryInterface $rateRepository,
        private readonly TaxRateInterfaceFactory $rateFactory,
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
        return 'Create Tax Rate';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Create a tax rate for a country, optional region and postcode scope. A new rate is not '
            . 'charged until a tax rule references it — create or update a rule afterwards.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->string('code', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('Unique rate code, e.g. `DE-19`.')
                ->required())
            ->string('tax_country_id', fn (StringBuilder $s) => $s
                ->minLength(2)
                ->maxLength(2)
                ->description('Two-letter ISO country code.')
                ->required())
            ->number('rate', fn (NumberBuilder $n) => $n
                ->minimum(0)
                ->description('Rate percentage, e.g. 19 for 19%.')
                ->required())
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
        $rate = $this->applier->apply($this->rateFactory->create(), $arguments);
        $saved = $this->rateRepository->save($rate);

        $payload = [
            'id' => $saved->getId() !== null ? (int) $saved->getId() : null,
            'code' => (string) $saved->getCode(),
            'country_id' => (string) $saved->getTaxCountryId(),
            'percent' => (float) $saved->getRate(),
            'note' => 'This rate is only charged once a tax rule references it.',
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax rate as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'rate_id' => $saved->getId() !== null ? (int) $saved->getId() : null,
                'code' => (string) $saved->getCode(),
            ]
        );
    }
}
