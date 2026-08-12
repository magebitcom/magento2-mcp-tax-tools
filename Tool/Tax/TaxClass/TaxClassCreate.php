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
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\Data\TaxClassInterfaceFactory;
use Magento\Tax\Api\TaxClassRepositoryInterface;

class TaxClassCreate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.class.create';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_class_create';

    /**
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param TaxClassInterfaceFactory $taxClassFactory
     */
    public function __construct(
        private readonly TaxClassRepositoryInterface $taxClassRepository,
        private readonly TaxClassInterfaceFactory $taxClassFactory
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
        return 'Create Tax Class';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Create a product or customer tax class. A new class does nothing until a tax rule '
            . 'references it and products or customer groups are assigned to it.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->string('class_name', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('Display name, e.g. `Reduced Rate`.')
                ->required())
            ->string('class_type', fn (StringBuilder $s) => $s
                ->enum(['PRODUCT', 'CUSTOMER'])
                ->description('PRODUCT for product classes, CUSTOMER for customer-group classes.')
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
        $name = $this->requireScalarString($arguments['class_name'] ?? null, 'class_name');
        $type = strtoupper($this->requireScalarString($arguments['class_type'] ?? null, 'class_type'));

        $taxClass = $this->taxClassFactory->create();
        $taxClass->setClassName($name);
        $taxClass->setClassType($type);
        $id = $this->taxClassRepository->save($taxClass);

        $payload = [
            'id' => (int) $id,
            'name' => $name,
            'type' => $type,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode tax class as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: $payload
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
