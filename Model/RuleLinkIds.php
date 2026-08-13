<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpTaxTools\Model;

/**
 * Magento returns a rule's linked ids as ints on create but as numeric strings
 * once the rule has been loaded, so every tool normalizes them through here.
 */
class RuleLinkIds
{
    /**
     * @param mixed $ids
     * @return array<int, int>
     */
    public function toIntegers(mixed $ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $out[] = (int) $id;
            }
        }

        return $out;
    }
}
