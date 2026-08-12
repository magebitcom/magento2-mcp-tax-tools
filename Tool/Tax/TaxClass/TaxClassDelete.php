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
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxClassRepositoryInterface;

class TaxClassDelete implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.class.delete';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_class_delete';

    /**
     * @param TaxClassRepositoryInterface $taxClassRepository
     */
    public function __construct(
        private readonly TaxClassRepositoryInterface $taxClassRepository
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
        return 'Delete Tax Class';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Permanently delete a tax class by id. Magento refuses the delete when the class is '
            . 'still referenced by a tax rule, a product or a customer group.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('class_id', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->description('Tax class id.')
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
        $rawId = $arguments['class_id'] ?? null;
        if (!is_numeric($rawId)) {
            throw new LocalizedException(__('Parameter "class_id" is required and must be an integer.'));
        }
        $classId = (int) $rawId;

        $taxClass = $this->taxClassRepository->get($classId);
        $removed = [
            'id' => $classId,
            'name' => (string) $taxClass->getClassName(),
            'type' => (string) $taxClass->getClassType(),
        ];

        $this->taxClassRepository->deleteById($classId);

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
