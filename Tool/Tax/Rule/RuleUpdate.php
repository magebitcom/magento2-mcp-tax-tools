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
use Magebit\Mcp\Model\Tool\Schema\Builder\BooleanBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Model\RuleLinkIds;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxRuleRepositoryInterface;

class RuleUpdate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.rule.update';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_rule_update';

    /**
     * @param TaxRuleRepositoryInterface $ruleRepository
     * @param RuleFieldApplier $applier
     * @param RuleLinkIds $linkIds
     */
    public function __construct(
        private readonly TaxRuleRepositoryInterface $ruleRepository,
        private readonly RuleFieldApplier $applier,
        private readonly RuleLinkIds $linkIds
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
        return 'Update Tax Rule';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Update an existing tax rule. Only the fields you supply change, but `tax_rate_ids`, '
            . '`customer_tax_class_ids` and `product_tax_class_ids` are replaced wholesale, not merged — '
            . 'sending one id drops every other id currently on the rule. Replacing the linked rate or '
            . 'class ids changes what is charged immediately.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('rule_id', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->description('Tax rule id.')
                ->required())
            ->string('code', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('Unique rule code.'))
            ->array('tax_rate_ids', fn (ArrayBuilder $a) => $a->ofIntegers()
                ->description('Ids of the tax rates this rule applies.'))
            ->array('customer_tax_class_ids', fn (ArrayBuilder $a) => $a->ofIntegers()
                ->description('Customer tax class ids — see tax.class.list.'))
            ->array('product_tax_class_ids', fn (ArrayBuilder $a) => $a->ofIntegers()
                ->description('Product tax class ids — see tax.class.list.'))
            ->integer('priority', fn (IntegerBuilder $i) => $i
                ->minimum(0)
                ->description('Rules with the same priority are summed; higher priorities compound.'))
            ->integer('position', fn (IntegerBuilder $i) => $i
                ->minimum(0)
                ->description('Sort position in the admin grid.'))
            ->boolean('calculate_subtotal', fn (BooleanBuilder $b) => $b
                ->description('Calculate off the subtotal rather than per line.'))
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
        $rawId = $arguments['rule_id'] ?? null;
        if (!is_numeric($rawId)) {
            throw new LocalizedException(__('Parameter "rule_id" is required and must be an integer.'));
        }
        $ruleId = (int) $rawId;

        $rule = $this->ruleRepository->get($ruleId);
        $before = [
            'code' => (string) $rule->getCode(),
            'tax_rate_ids' => $this->linkIds->toIntegers($rule->getTaxRateIds()),
            'customer_tax_class_ids' => $this->linkIds->toIntegers($rule->getCustomerTaxClassIds()),
            'product_tax_class_ids' => $this->linkIds->toIntegers($rule->getProductTaxClassIds()),
        ];

        $saved = $this->ruleRepository->save($this->applier->apply($rule, $arguments));

        $payload = [
            'id' => $saved->getId() !== null ? (int) $saved->getId() : null,
            'code' => (string) $saved->getCode(),
            'tax_rate_ids' => $this->linkIds->toIntegers($saved->getTaxRateIds()),
            'customer_tax_class_ids' => $this->linkIds->toIntegers($saved->getCustomerTaxClassIds()),
            'product_tax_class_ids' => $this->linkIds->toIntegers($saved->getProductTaxClassIds()),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax rule as JSON.'));
        }

        $changed = array_values(array_diff(array_keys($arguments), ['rule_id']));

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'rule_id' => $ruleId,
                'changed_fields' => $changed,
                'before' => $before,
            ]
        );
    }
}
