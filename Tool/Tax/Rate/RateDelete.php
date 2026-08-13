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
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magento\Framework\Exception\LocalizedException;
use Magento\Tax\Api\TaxRateRepositoryInterface;

class RateDelete implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'tax.rate.delete';
    public const ACL_RESOURCE = 'Magebit_McpTaxTools::tool_tax_rate_delete';

    /**
     * @param TaxRateRepositoryInterface $rateRepository
     */
    public function __construct(
        private readonly TaxRateRepositoryInterface $rateRepository
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
        return 'Delete Tax Rate';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Permanently delete a tax rate by id. This changes what customers are charged as soon as '
            . 'it completes, and cannot be undone. Rules referencing this rate alongside others simply '
            . 'drop it; Magento refuses the delete when the rate is a rule\'s only rate.';
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
        $rawId = $arguments['rate_id'] ?? null;
        if (!is_numeric($rawId)) {
            throw new LocalizedException(__('Parameter "rate_id" is required and must be an integer.'));
        }
        $rateId = (int) $rawId;

        $rate = $this->rateRepository->get($rateId);
        $removed = [
            'id' => $rateId,
            'code' => (string) $rate->getCode(),
            'country_id' => (string) $rate->getTaxCountryId(),
            'percent' => (float) $rate->getRate(),
        ];

        $this->rateRepository->deleteById($rateId);

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
