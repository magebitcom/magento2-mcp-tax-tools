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
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxClassRepositoryInterface;

class TaxClassUpdate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.class.update';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_class_update';

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
        return 'Update Tax Class';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Rename an existing tax class. The class type cannot be changed after creation.';
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
            ->string('class_name', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('Display name, e.g. `Reduced Rate`.')
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
        return false;
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
        $name = $this->requireScalarString($arguments['class_name'] ?? null, 'class_name');

        $taxClass = $this->taxClassRepository->get($classId);
        $previousName = (string) $taxClass->getClassName();

        $taxClass->setClassName($name);
        $this->taxClassRepository->save($taxClass);

        $payload = [
            'id' => $classId,
            'name' => $name,
            'type' => (string) $taxClass->getClassType(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax class as JSON.'));
        }

        $changed = array_values(array_diff(array_keys($arguments), ['class_id']));

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'class_id' => $classId,
                'changed_fields' => $changed,
                'before' => ['name' => $previousName],
            ]
        );
    }

    /**
     * @param mixed $value
     * @param string $field
     * @return string
     * @throws LocalizedException
     */
    private function requireScalarString(mixed $value, string $field): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new LocalizedException(__('Parameter "%1" is required and must be a scalar value.', $field));
    }
}
