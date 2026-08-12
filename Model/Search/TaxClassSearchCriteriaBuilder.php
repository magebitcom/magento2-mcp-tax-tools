<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model\Search;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;

/**
 * Unhandled filter keys throw so the tool layer reports `INVALID_PARAMS` rather
 * than silently returning an unfiltered set.
 */
class TaxClassSearchCriteriaBuilder
{
    public const MAX_PAGE_SIZE = 100;
    public const DEFAULT_PAGE_SIZE = 25;

    public const FIELD_CLASS_ID = 'class_id';
    public const FIELD_CLASS_NAME = 'class_name';
    public const FIELD_CLASS_TYPE = 'class_type';

    /** @var array<int, string> */
    public const SORTABLE_FIELDS = [
        self::FIELD_CLASS_ID,
        self::FIELD_CLASS_NAME,
        self::FIELD_CLASS_TYPE,
    ];

    /** @var array<int, string> */
    public const CLASS_TYPES = ['PRODUCT', 'CUSTOMER'];

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly SortOrderBuilder $sortBuilder
    ) {
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return SearchCriteriaInterface
     * @throws LocalizedException
     */
    public function build(array $args): SearchCriteriaInterface
    {
        $filtersRaw = $args['filters'] ?? [];
        if (!is_array($filtersRaw)) {
            throw new LocalizedException(__('Filter payload must be an object.'));
        }

        foreach ($filtersRaw as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new LocalizedException(__('Filter keys must be non-empty strings.'));
            }
            $this->applyFilter($key, $value);
        }

        $this->applySort($args);
        $this->applyPaging($args);

        return $this->criteriaBuilder->create();
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function applyFilter(string $key, mixed $value): void
    {
        switch ($key) {
            case self::FIELD_CLASS_ID:
                $this->addEqualsOrIn(self::FIELD_CLASS_ID, $value);
                return;

            case self::FIELD_CLASS_NAME:
                $this->addStringFilter(self::FIELD_CLASS_NAME, $value);
                return;

            case self::FIELD_CLASS_TYPE:
                $this->addClassTypeFilter($value);
                return;
        }

        throw new LocalizedException(
            __('Unsupported filter "%1". Supported: %2.', $key, implode(', ', self::SORTABLE_FIELDS))
        );
    }

    /**
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addClassTypeFilter(mixed $value): void
    {
        $values = is_array($value) ? array_values($value) : [$value];
        $normalized = [];
        foreach ($values as $candidate) {
            if (!is_string($candidate) || !in_array(strtoupper($candidate), self::CLASS_TYPES, true)) {
                throw new LocalizedException(
                    __('Filter "class_type" accepts only PRODUCT or CUSTOMER.')
                );
            }
            $normalized[] = strtoupper($candidate);
        }

        $this->criteriaBuilder->addFilter(
            self::FIELD_CLASS_TYPE,
            count($normalized) === 1 ? $normalized[0] : $normalized,
            count($normalized) === 1 ? 'eq' : 'in'
        );
    }

    /**
     * Supports exact match, `*glob*` wildcards mapped to LIKE, and arrays as IN.
     *
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addStringFilter(string $field, mixed $value): void
    {
        if (is_array($value)) {
            $this->criteriaBuilder->addFilter($field, array_values($value), 'in');
            return;
        }

        if (is_scalar($value)) {
            $string = (string) $value;
        } elseif ($value === null) {
            $string = '';
        } else {
            throw new LocalizedException(__('Filter "%1" must be a scalar value.', $field));
        }

        if (str_contains($string, '*')) {
            $this->criteriaBuilder->addFilter($field, str_replace('*', '%', $string), 'like');
            return;
        }

        $this->criteriaBuilder->addFilter($field, $string, 'eq');
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function addEqualsOrIn(string $field, mixed $value): void
    {
        if (is_array($value)) {
            $this->criteriaBuilder->addFilter($field, array_values($value), 'in');
            return;
        }
        $this->criteriaBuilder->addFilter($field, $value, 'eq');
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applySort(array $args): void
    {
        $field = $args['sort_by'] ?? null;
        if (!is_string($field) || $field === '') {
            return;
        }

        if (!in_array($field, self::SORTABLE_FIELDS, true)) {
            throw new LocalizedException(
                __('Unsupported sort field "%1". Supported: %2.', $field, implode(', ', self::SORTABLE_FIELDS))
            );
        }

        $directionRaw = $args['sort_dir'] ?? 'asc';
        $direction = is_string($directionRaw) && strtolower($directionRaw) === 'desc'
            ? SortOrder::SORT_DESC
            : SortOrder::SORT_ASC;

        $this->sortBuilder->setField($field);
        $this->sortBuilder->setDirection($direction);
        $this->criteriaBuilder->addSortOrder($this->sortBuilder->create());
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     */
    private function applyPaging(array $args): void
    {
        $pageRaw = $args['page'] ?? 1;
        $page = is_numeric($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $sizeRaw = $args['page_size'] ?? self::DEFAULT_PAGE_SIZE;
        $size = is_numeric($sizeRaw) ? (int) $sizeRaw : self::DEFAULT_PAGE_SIZE;
        $size = max(1, min($size, self::MAX_PAGE_SIZE));

        $this->criteriaBuilder->setCurrentPage($page);
        $this->criteriaBuilder->setPageSize($size);
    }
}
