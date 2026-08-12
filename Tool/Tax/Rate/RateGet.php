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
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpTaxTools\Api\RateFieldResolverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxRateRepositoryInterface;

class RateGet implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.rate.get';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_rate_get';

    /**
     * @param TaxRateRepositoryInterface $rateRepository
     * @param ResolverPipeline $pipeline
     * @param RateFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly TaxRateRepositoryInterface $rateRepository,
        private readonly ResolverPipeline $pipeline,
        private readonly array $fieldResolvers = []
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
        return 'Get Tax Rate';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Fetch a single tax rate by id, including its country, region, postcode scope and percentage.';
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
            ->array('fields', fn (ArrayBuilder $a) => $a->ofStrings()
                ->description('Whitelist of resolver keys to include — overrides defaults.'))
            ->array('exclude', fn (ArrayBuilder $a) => $a->ofStrings()
                ->description('Resolver keys to omit from the response.'))
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
        $rawId = $arguments['rate_id'] ?? null;
        if (!is_numeric($rawId)) {
            throw new LocalizedException(__('Parameter "rate_id" is required and must be an integer.'));
        }
        $rateId = (int) $rawId;

        $rate = $this->rateRepository->get($rateId);

        $row = [];
        foreach ($this->pipeline->plan($this->fieldResolvers, $arguments) as $resolver) {
            $row[$resolver->getKey()] = $resolver->resolve($rate, $arguments);
        }

        $json = json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax rate as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: ['rate_id' => $rateId]
        );
    }
}
