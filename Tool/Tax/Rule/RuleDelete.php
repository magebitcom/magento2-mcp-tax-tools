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
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpTaxTools\Model\RuleLinkIds;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxRuleRepositoryInterface;

class RuleDelete implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.rule.delete';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_rule_delete';

    /**
     * @param TaxRuleRepositoryInterface $ruleRepository
     * @param RuleLinkIds $linkIds
     */
    public function __construct(
        private readonly TaxRuleRepositoryInterface $ruleRepository,
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
        return 'Delete Tax Rule';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Permanently delete a tax rule by id. Every rate this rule linked stops being charged '
            . 'immediately. This cannot be undone.';
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
        return true;
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
        $removed = [
            'id' => $ruleId,
            'code' => (string) $rule->getCode(),
            'tax_rate_ids' => $this->linkIds->toIntegers($rule->getTaxRateIds()),
            'customer_tax_class_ids' => $this->linkIds->toIntegers($rule->getCustomerTaxClassIds()),
            'product_tax_class_ids' => $this->linkIds->toIntegers($rule->getProductTaxClassIds()),
        ];

        $this->ruleRepository->deleteById($ruleId);

        $json = json_encode(['deleted' => $removed], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode delete result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: $removed
        );
    }
}
