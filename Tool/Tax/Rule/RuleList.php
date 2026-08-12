<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Tool\Tax\Rule;

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
use Magebit\McpTaxTools\Api\RuleFieldResolverInterface;
use Magebit\McpTaxTools\Model\Search\RuleSearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxRuleRepositoryInterface;

class RuleList implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.rule.list';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_rule_list';

    /**
     * @param TaxRuleRepositoryInterface $ruleRepository
     * @param RuleSearchCriteriaBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param RuleFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly TaxRuleRepositoryInterface $ruleRepository,
        private readonly RuleSearchCriteriaBuilder $searchBuilder,
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
        return 'List Tax Rules';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'List tax rules with their linked tax rates and customer / '
            . 'product tax classes resolved inline. This is the single call '
            . 'that answers whether a rate is wired to the right classes — a '
            . 'correct rate attached to the wrong rule still charges wrong.';
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
                    'tax_calculation_rule_id' => ['type' => ['integer', 'array'], 'description' => 'Rule id(s).'],
                    'code' => ['type' => ['string', 'array'], 'description' => 'Rule code: exact, `*glob*` wildcard, or array ⇒ IN.'],
                    'priority' => ['type' => ['integer', 'array'], 'description' => 'Rule priority.'],
                    'position' => ['type' => ['integer', 'array'], 'description' => 'Sort position.'],
                ]
            ))
            ->with(Sort::fields(RuleSearchCriteriaBuilder::SORTABLE_FIELDS, 'tax_calculation_rule_id', 'asc'))
            ->integer('page', fn (IntegerBuilder $i) => $i->minimum(1))
            ->integer('page_size', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(RuleSearchCriteriaBuilder::MAX_PAGE_SIZE)
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
        $result = $this->ruleRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $rule) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($rule, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? RuleSearchCriteriaBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax rule list as JSON.'));
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
