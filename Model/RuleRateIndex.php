<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Tax\Api\TaxRuleRepositoryInterface;

/**
 * Reverse rate → rules mapping, built by inverting `getTaxRateIds()` across
 * every rule rather than querying `tax_calculation` directly.
 */
class RuleRateIndex
{
    /** @var array<int, array<int, array{id: int, code: string}>>|null */
    private ?array $byRate = null;

    /**
     * @param TaxRuleRepositoryInterface $ruleRepository
     * @param SearchCriteriaBuilder $criteriaBuilder
     */
    public function __construct(
        private readonly TaxRuleRepositoryInterface $ruleRepository,
        private readonly SearchCriteriaBuilder $criteriaBuilder
    ) {
    }

    /**
     * Empty result means the rate is configured but never charged.
     *
     * @param int $rateId
     * @return array<int, array{id: int, code: string}>
     */
    public function rulesForRate(int $rateId): array
    {
        return $this->load()[$rateId] ?? [];
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $this->byRate = null;
    }

    /**
     * @return array<int, array<int, array{id: int, code: string}>>
     */
    private function load(): array
    {
        if ($this->byRate !== null) {
            return $this->byRate;
        }

        $criteria = $this->criteriaBuilder->create();
        $byRate = [];

        foreach ($this->ruleRepository->getList($criteria)->getItems() as $rule) {
            $ruleId = $rule->getId();
            if ($ruleId === null) {
                continue;
            }
            $descriptor = ['id' => (int) $ruleId, 'code' => (string) $rule->getCode()];

            foreach ($rule->getTaxRateIds() ?? [] as $rateId) {
                if (!is_numeric($rateId)) {
                    continue;
                }
                $byRate[(int) $rateId][] = $descriptor;
            }
        }

        $this->byRate = $byRate;
        return $byRate;
    }
}
