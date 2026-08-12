# Extending `Magebit_McpTaxTools`

Every read tool in this module composes its response from a DI-injected
array of **field resolvers**. Each resolver owns one named slice of the
output; 3rd parties add, replace, or remove slices from their own
`etc/di.xml` without touching this module.

The pattern applies to three entities, each with its own interface:

| Entity | Interface | Built-in tools using it |
|---|---|---|
| Tax rate | `Magebit\McpTaxTools\Api\RateFieldResolverInterface` | `tax.rate.get`, `tax.rate.list` |
| Tax rule | `Magebit\McpTaxTools\Api\RuleFieldResolverInterface` | `tax.rule.get`, `tax.rule.list` |
| Tax class | `Magebit\McpTaxTools\Api\TaxClassFieldResolverInterface` | `tax.class.get`, `tax.class.list` |

All three extend the base module's `Magebit\Mcp\Api\FieldResolverInterface`
marker, so the same `Magebit\Mcp\Model\Util\ResolverPipeline` sorts and
filters them for every tool, regardless of which satellite registered them.

Currency tools (`directory.currency.*`) do not use field resolvers — they
each return a fixed payload shape and have no `fieldResolvers` DI argument.

## Add a new field to `tax.rate.get` / `tax.rate.list`

### 1. Implement `RateFieldResolverInterface`

Built-in resolver keys already in use: `identity`, `location`,
`linked_rules`, `titles`. Pick a key none of these use.

```php
<?php
/**
 * @author    Vendor <info@vendor.example>
 * @copyright Copyright (c) Vendor
 * @license   MIT
 */
declare(strict_types=1);

namespace Vendor\EuVat\Mcp\Resolver;

use Magebit\McpTaxTools\Api\RateFieldResolverInterface;
use Magento\Tax\Api\Data\TaxRateInterface;

/**
 * Flags whether a rate's country is an EU member state, so callers can
 * separate domestic and intra-EU rates without a lookup table of their own.
 */
class EuMembershipResolver implements RateFieldResolverInterface
{
    private const EU_COUNTRIES = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
        'SI', 'ES', 'SE'];

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'eu_membership';
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        // Built-ins occupy 10-40; anything from 100 renders after them.
        return 100;
    }

    /**
     * @param TaxRateInterface $rate
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<string, bool>
     */
    public function resolve(TaxRateInterface $rate, array $args): array
    {
        unset($args);

        return [
            'is_eu_member' => in_array((string) $rate->getTaxCountryId(), self::EU_COUNTRIES, true),
        ];
    }
}
```

### 2. Register the resolver in `etc/di.xml`

```xml
<type name="Magebit\McpTaxTools\Tool\Tax\Rate\RateGet">
    <arguments>
        <argument name="fieldResolvers" xsi:type="array">
            <item name="eu_membership" xsi:type="object">
                Vendor\EuVat\Mcp\Resolver\EuMembershipResolver
            </item>
        </argument>
    </arguments>
</type>

<!-- Optional: also surface it on list rows. -->
<type name="Magebit\McpTaxTools\Tool\Tax\Rate\RateList">
    <arguments>
        <argument name="fieldResolvers" xsi:type="array">
            <item name="eu_membership" xsi:type="object">
                Vendor\EuVat\Mcp\Resolver\EuMembershipResolver
            </item>
        </argument>
    </arguments>
</type>
```

DI-array items are merged across modules, so the built-in resolvers stay in
place — you only pay for what you add.

### 3. Run `setup:upgrade` and `setup:di:compile`

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

No other changes needed. The next `tax.rate.get` call includes the new
`eu_membership` key.

### Duplicate keys fail loudly

`ResolverPipeline::plan()` throws `InvalidArgumentException` the moment two
registered resolvers report the same `getKey()` — it does not silently let
the second registration win. If your resolver's key collides with a
built-in one (or another 3rd-party module's), every call to that tool
starts failing until the collision is resolved. Pick a key that is
unlikely to collide — prefixing with your vendor or feature name
(`euvat_membership` instead of `membership`) is the safe default for
public extensions.

### Opt-out from a caller

Every read tool in this module accepts the same two arguments:

- `fields: ["identity", "location"]` — only render those keys.
- `exclude: ["titles"]` — everything except those keys.

## Add a new field to `tax.rule.get` / `tax.rule.list` or `tax.class.get` / `tax.class.list`

Identical mechanics, against `RuleFieldResolverInterface` (built-in keys:
`identity`, `rates`, `classes`) or `TaxClassFieldResolverInterface`
(built-in key: `identity`), registered against `RuleGet` / `RuleList` or
`TaxClassGet` / `TaxClassList` respectively.

## Adding a filter to a list tool

**This module does not use a `filterTranslators` DI array.** Unlike some
sibling `Mcp*Tools` modules, `Magebit\McpTaxTools\Model\Search\RateSearchCriteriaBuilder`,
`RuleSearchCriteriaBuilder`, and `TaxClassSearchCriteriaBuilder` have no
`*FilterTranslatorInterface` and no filter-translator constructor argument —
their `applyFilter()` is a private method with a hardcoded `switch`, and the
`SearchCriteriaBuilder`/`SortOrderBuilder` collaborators are private
constructor-promoted properties. There is no DI seam to plug a new filter
key into the existing instance.

The tools depend on the concrete builder class directly (not an interface),
so the working extension path is a full `<preference>` for that concrete
class, overriding the public `build()` method and layering an extra filter
group onto the `SearchCriteriaInterface` the parent already built — rather
than trying to intercept the parent's private filter-application logic:

```php
<?php
/**
 * @author    Vendor <info@vendor.example>
 * @copyright Copyright (c) Vendor
 * @license   MIT
 */
declare(strict_types=1);

namespace Vendor\EuVat\Mcp\Search;

use Magebit\McpTaxTools\Model\Search\RateSearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrderBuilder;

/**
 * Adds an `oss_scheme` filter on top of the built-in rate filters, matching
 * against a custom attribute a hypothetical OSS-IOSS module attaches to
 * tax rates. Delegates every other filter, sort and paging concern to the
 * parent builder unchanged.
 */
class RateSearchCriteriaBuilderWithOssScheme extends RateSearchCriteriaBuilder
{
    /**
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     */
    public function __construct(
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
        SearchCriteriaBuilder $criteriaBuilder,
        SortOrderBuilder $sortBuilder
    ) {
        parent::__construct($criteriaBuilder, $sortBuilder);
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return SearchCriteriaInterface
     */
    public function build(array $args): SearchCriteriaInterface
    {
        $scheme = null;
        if (isset($args['filters']) && is_array($args['filters']) && isset($args['filters']['oss_scheme'])) {
            $scheme = $args['filters']['oss_scheme'];
            unset($args['filters']['oss_scheme']);
        }

        $criteria = parent::build($args);

        if ($scheme !== null) {
            $filter = $this->filterBuilder
                ->setField('oss_scheme')
                ->setValue($scheme)
                ->setConditionType('eq')
                ->create();
            $group = $this->filterGroupBuilder->addFilter($filter)->create();
            $criteria->setFilterGroups(array_merge($criteria->getFilterGroups(), [$group]));
        }

        return $criteria;
    }
}
```

```xml
<preference for="Magebit\McpTaxTools\Model\Search\RateSearchCriteriaBuilder"
            type="Vendor\EuVat\Mcp\Search\RateSearchCriteriaBuilderWithOssScheme"/>
```

The `<preference>` works because `RateList` type-hints the concrete
`RateSearchCriteriaBuilder` class in its constructor rather than an
interface — Magento's object manager resolves that type hint through the
preference table the same way it would for an interface. `RateGet` takes no
search builder at all (`tax.rate.get` fetches a single rate by id and
accepts no filters), so this preference has no effect on it. Apply the same
pattern to `RuleSearchCriteriaBuilder` or `TaxClassSearchCriteriaBuilder` for
rule or class filters — those apply to `RuleList`/`TaxClassList` only, for
the same reason. A custom column filtered this way still needs to resolve
to a real, indexed column or attribute on the underlying repository
collection — this pattern adds the filter group, it does not add the
column.

## Write-tool ACL layering

Every write tool in this module implements both `ToolInterface` and
`Magebit\Mcp\Api\UnderlyingAclAwareInterface`, returning
`Magento_Tax::manage_tax` (tax rates, rules, classes) or
`Magento_CurrencySymbol::currency_rates` (currency tools). The dispatcher
enforces both ACL checks per call:

1. The tool's own MCP-scoped resource, e.g.
   `Magebit_McpTaxTools::tool_tax_rate_delete`.
2. The underlying Magento resource from `getUnderlyingAclResource()`, e.g.
   `Magento_Tax::manage_tax`.

Both must pass. This preserves "MCP cannot do what the admin UI cannot" —
an MCP-only role denied `Magento_Tax::manage_tax` cannot delete a tax rate
via MCP even if it was granted the MCP-specific resource by accident.

Custom write tools built on top of this module SHOULD implement
`UnderlyingAclAwareInterface` whenever they delegate to a Magento service
contract that already has an established admin ACL.

## Scope of this module

This module reads and writes what core Magento's `Magento_Tax` and
`Magento_Directory` / `Magento_CurrencySymbol` modules expose:
country/region/postcode tax rates, rules linking rates to tax classes, tax
classes themselves, and the currency directory (base/display currency,
allowed currencies, exchange rates). It has no visibility into anything a
third-party tax engine or VAT-compliance extension computes internally.

A third-party EU VAT / OSS-IOSS extension — one-stop-shop registration
thresholds, per-country VAT number validation, distance-selling reports —
would ship its **own** satellite module (`Vendor_McpEuVatTools` or similar)
following this exact pattern: its own `ToolInterface` implementations, its
own ACL resources under its own top-level `Vendor_Module::mcp` node, and
its own field-resolver / search-builder extension points if it wants to be
extensible in turn. It would not extend `Magebit_McpTaxTools` directly
unless it specifically wanted to add fields or filters to core tax rates,
rules, and classes as documented above.
