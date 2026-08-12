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
use Magebit\Mcp\Model\Tool\Schema\Preset\Filters;
use Magebit\Mcp\Model\Tool\Schema\Preset\Sort;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpTaxTools\Api\RateFieldResolverInterface;
use Magebit\McpTaxTools\Model\Search\RateSearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxRateRepositoryInterface;

class RateList implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.rate.list';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_rate_list';

    /**
     * @param TaxRateRepositoryInterface $rateRepository
     * @param RateSearchCriteriaBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param RateFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly TaxRateRepositoryInterface $rateRepository,
        private readonly RateSearchCriteriaBuilder $searchBuilder,
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
        return 'List Tax Rates';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'List tax rates with their country, region, postcode and '
            . 'percentage. Use this to audit whether each destination country '
            . 'carries the rate it should. A rate that exists here is not '
            . 'necessarily charged — it only applies if a tax rule references '
            . 'it; check the `linked_rules` slice.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->with(Filters::describing(
                'Filter clauses. Scalar or array values (array ⇒ IN).',
                [
                    'tax_calculation_rate_id' => ['type' => ['integer', 'array'], 'description' => 'Rate id(s).'],
                    'code' => ['type' => ['string', 'array'], 'description' => 'Rate code: exact, `*glob*` wildcard, or array ⇒ IN.'],
                    'tax_country_id' => ['type' => ['string', 'array'], 'description' => 'Two-letter country code, e.g. DE.'],
                    'tax_region_id' => ['type' => ['integer', 'array'], 'description' => 'Region id(s); 0 means all regions.'],
                    'tax_postcode' => ['type' => ['string', 'array'], 'description' => 'Postcode pattern; `*` means all.'],
                    'rate' => ['type' => ['number', 'array'], 'description' => 'Rate percentage, e.g. 19.'],
                ]
            ))
            ->with(Sort::fields(RateSearchCriteriaBuilder::SORTABLE_FIELDS, 'tax_calculation_rate_id', 'asc'))
            ->integer('page', fn (IntegerBuilder $i) => $i->minimum(1))
            ->integer('page_size', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(RateSearchCriteriaBuilder::MAX_PAGE_SIZE)
            )
            ->array('fields', fn (ArrayBuilder $a) => $a->ofStrings()
                ->description('Whitelist of resolver keys to include — overrides defaults.')
            )
            ->array('exclude', fn (ArrayBuilder $a) => $a->ofStrings()
                ->description('Resolver keys to omit from the response.')
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
        $criteria = $this->searchBuilder->build($arguments);
        $result = $this->rateRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $rate) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($rate, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? RateSearchCriteriaBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax rate list as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'row_count' => count($rows),
                'total_count' => (int) $result->getTotalCount(),
            ]
        );
    }
}
