<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Tax\Api\TaxClassRepositoryInterface;

/**
 * Lazily loads every tax class once so per-rule class-name lookups do not
 * become an N+1 across a rule list.
 */
class TaxClassNameIndex
{
    /** @var array<int, string>|null */
    private ?array $names = null;

    /**
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param SearchCriteriaBuilder $criteriaBuilder
     */
    public function __construct(
        private readonly TaxClassRepositoryInterface $taxClassRepository,
        private readonly SearchCriteriaBuilder $criteriaBuilder
    ) {
    }

    /**
     * @param int $classId
     * @return string|null
     */
    public function nameFor(int $classId): ?string
    {
        return $this->load()[$classId] ?? null;
    }

    /**
     * Drops the cache; the next lookup reloads. For long-lived processes.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->names = null;
    }

    /**
     * @return array<int, string>
     */
    private function load(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }

        $criteria = $this->criteriaBuilder->create();
        $names = [];
        foreach ($this->taxClassRepository->getList($criteria)->getItems() as $taxClass) {
            $classId = $taxClass->getClassId();
            if ($classId === null) {
                continue;
            }
            $names[(int) $classId] = (string) $taxClass->getClassName();
        }

        $this->names = $names;
        return $names;
    }
}
