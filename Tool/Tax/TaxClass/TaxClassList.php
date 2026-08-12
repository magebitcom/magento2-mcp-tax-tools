<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Tool\Tax\TaxClass;

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
use Magebit\McpTaxTools\Api\TaxClassFieldResolverInterface;
use Magebit\McpTaxTools\Model\Search\TaxClassSearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxClassRepositoryInterface;

class TaxClassList implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.class.list';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_class_list';

    /**
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param TaxClassSearchCriteriaBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param TaxClassFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly TaxClassRepositoryInterface $taxClassRepository,
        private readonly TaxClassSearchCriteriaBuilder $searchBuilder,
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
        return 'List Tax Classes';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'List product and customer tax classes. Use this to resolve a '
            . '`tax_class_id` seen on a product, customer group or tax rule '
            . 'into a human-readable class name. Filter by `class_type` '
            . '(PRODUCT or CUSTOMER) to narrow the list.';
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
                    'class_id' => ['type' => ['integer', 'array'], 'description' => 'Tax class id(s).'],
                    'class_name' => ['type' => ['string', 'array'], 'description' => 'Class name: exact, `*glob*` wildcard, or array ⇒ IN.'],
                    'class_type' => ['type' => ['string', 'array'], 'description' => 'PRODUCT or CUSTOMER.'],
                ]
            ))
            ->with(Sort::fields(TaxClassSearchCriteriaBuilder::SORTABLE_FIELDS, 'class_id', 'asc'))
            ->integer('page', fn (IntegerBuilder $i) => $i->minimum(1))
            ->integer('page_size', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(TaxClassSearchCriteriaBuilder::MAX_PAGE_SIZE)
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
        $result = $this->taxClassRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $taxClass) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($taxClass, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? TaxClassSearchCriteriaBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax class list as JSON.'));
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
