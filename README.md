# Magento2 MCP - Tax Tools

This is a sub-module for the [Magento2 MCP module](https://github.com/magebitcom/magento2-mcp-module)

----

Tax and currency MCP tools for `Magebit_Mcp`. Reads and writes over tax
rates, tax rules, tax classes, and Magento's currency directory (exchange
rates and currency configuration). The module name says "tax" but the
currency tools live here too — see the tool catalog below for the full
surface.

Each read tool is a thin wrapper over a Magento service contract
(`TaxRateRepositoryInterface`, `TaxRuleRepositoryInterface`,
`TaxClassRepositoryInterface`, `CurrencyInformationAcquirerInterface`) and
composes its response from field resolvers that 3rd-party modules can
extend.

## Install

```bash
composer require magebitcom/magento2-mcp-tax-tools
bin/magento module:enable Magebit_McpTaxTools
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Tool catalog

All writes require the global `magebit_mcp/general/allow_writes` flag **and**
the calling token's own `allow_writes` flag to be `1`; either off returns
`-32012 WRITE_NOT_ALLOWED`. Destructive or high-impact writes additionally
set `confirmationRequired: true` so MCP clients (Claude Desktop, etc.)
prompt before firing.

Every tool also implements `Magebit\Mcp\Api\UnderlyingAclAwareInterface`, so
the dispatcher enforces both the tool's own MCP-scoped ACL resource and the
underlying Magento admin resource — an MCP-only role denied the underlying
resource cannot use the tool, even if it was granted the MCP-specific one by
accident.

| Tool | Title | Write mode | Confirm? | ACL resource | Underlying ACL |
|---|---|---|---|---|---|
| `tax.class.list` | List Tax Classes | read | no | `Magebit_McpTaxTools::tool_tax_class_list` | `Magento_Tax::manage_tax` |
| `tax.class.get` | Get Tax Class | read | no | `Magebit_McpTaxTools::tool_tax_class_get` | `Magento_Tax::manage_tax` |
| `tax.rate.list` | List Tax Rates | read | no | `Magebit_McpTaxTools::tool_tax_rate_list` | `Magento_Tax::manage_tax` |
| `tax.rate.get` | Get Tax Rate | read | no | `Magebit_McpTaxTools::tool_tax_rate_get` | `Magento_Tax::manage_tax` |
| `tax.rule.list` | List Tax Rules | read | no | `Magebit_McpTaxTools::tool_tax_rule_list` | `Magento_Tax::manage_tax` |
| `tax.rule.get` | Get Tax Rule | read | no | `Magebit_McpTaxTools::tool_tax_rule_get` | `Magento_Tax::manage_tax` |
| `tax.rate.create` | Create Tax Rate | write | no | `Magebit_McpTaxTools::tool_tax_rate_create` | `Magento_Tax::manage_tax` |
| `tax.rate.update` | Update Tax Rate | write | no | `Magebit_McpTaxTools::tool_tax_rate_update` | `Magento_Tax::manage_tax` |
| `tax.rate.delete` | Delete Tax Rate | write | **yes** | `Magebit_McpTaxTools::tool_tax_rate_delete` | `Magento_Tax::manage_tax` |
| `tax.rule.create` | Create Tax Rule | write | no | `Magebit_McpTaxTools::tool_tax_rule_create` | `Magento_Tax::manage_tax` |
| `tax.rule.update` | Update Tax Rule | write | no | `Magebit_McpTaxTools::tool_tax_rule_update` | `Magento_Tax::manage_tax` |
| `tax.rule.delete` | Delete Tax Rule | write | **yes** | `Magebit_McpTaxTools::tool_tax_rule_delete` | `Magento_Tax::manage_tax` |
| `tax.class.create` | Create Tax Class | write | no | `Magebit_McpTaxTools::tool_tax_class_create` | `Magento_Tax::manage_tax` |
| `tax.class.update` | Update Tax Class | write | no | `Magebit_McpTaxTools::tool_tax_class_update` | `Magento_Tax::manage_tax` |
| `tax.class.delete` | Delete Tax Class | write | **yes** | `Magebit_McpTaxTools::tool_tax_class_delete` | `Magento_Tax::manage_tax` |
| `directory.currency.info` | Get Currency Information | read | no | `Magebit_McpTaxTools::tool_directory_currency_info` | `Magento_CurrencySymbol::currency_rates` |
| `directory.currency.rate.set` | Set Currency Rates | write | **yes** | `Magebit_McpTaxTools::tool_directory_currency_rate_set` | `Magento_CurrencySymbol::currency_rates` |
| `directory.currency.rate.import` | Import Currency Rates | write | **yes** | `Magebit_McpTaxTools::tool_directory_currency_rate_import` | `Magento_CurrencySymbol::currency_rates` |

`directory.currency.rate.set` and `directory.currency.rate.import` both
require confirmation even though neither deletes anything — a bad rate
mis-prices every order placed in the affected currency until corrected, so
the module treats them as consequential rather than routine.

## Auditing a tax setup

A rate that exists is not necessarily a rate that is charged, and a rule
that looks correctly configured can still reference the wrong classes. Work
through the setup in this order:

1. **`tax.class.list`** — enumerate the product and customer tax classes
   that exist, so you can recognise the `class_id` values used everywhere
   below.
2. **`tax.rate.list`** — list the configured rates and inspect each row's
   `linked_rules.orphaned` flag. `orphaned: true` means no tax rule
   references that rate: it is fully configured (country, region,
   percentage) but is never applied to a single order. This is invisible in
   a plain rate list and in the admin Tax Rates grid — both simply show the
   rate exists, with nothing to indicate it is wired to anything.
3. **`tax.rule.list`** — for each rule, check the resolved `rates` and
   `classes` slices. `rates` expands the rule's rate ids into full rate
   descriptors (country, region, postcode, percentage) instead of leaving
   you to cross-reference ids by hand; `classes` resolves the rule's
   customer/product tax class ids into `{id, name}` pairs so you can see at
   a glance whether the rule is scoped to the classes it should be.

## What this module cannot tell you

- **When exchange rates were last refreshed.** `directory_currency_rate`
  has exactly three columns — `currency_from`, `currency_to`, `rate` — and
  the admin Currency Rates grid renders no last-updated timestamp either.
  Nothing in Magento records when a rate was written, so no tool here can
  report it. Use `system.cron.status` with `job_code=currency_rates_update`
  to check whether the scheduled import job is actually running.
- **A CLI equivalent of the rate import.** Core Magento ships no
  `currency:import` command. `directory.currency.rate.import` wraps the
  same `Magento\Directory\Model\Currency\Import\*` service the
  `currency_rates_update` cron job and the admin Currency Rates "Import"
  button both use — it is the closest thing to a CLI trigger that exists.
- **Tax configuration values** — calculation basis, whether prices include
  tax, default customer/product tax classes, price display settings
  (`tax/*` in `system.xml`). Read these with `system.config.get`, not this
  module; it has no tool for `config.xml`-scoped settings. Most of these
  settings have **no row in `core_config_data`** because the store runs on
  the `config.xml` default — so querying the database directly, or running
  `bin/magento config:show`, returns nothing even though the setting is
  fully in effect. Only `ScopeConfigInterface` (which `system.config.get`
  uses) merges the `config.xml` default with any override.
- **Currencies excluded from `currency/options/allow`.**
  `directory.currency.info` reports only currencies the store allows for
  display — `CurrencyInformationAcquirerInterface` filters by that setting.
  A rate can be present and correct in `directory_currency_rate` and still
  never appear in this tool's output, because the currency itself isn't
  enabled for the store.

## Write-gating

Write tools need **both**:

1. `magebit_mcp/general/allow_writes = 1` (Stores → Configuration →
   Magebit → MCP Server → Allow Write Tools).
2. The calling token's own `allow_writes = 1`.

Either off → `-32012 WRITE_NOT_ALLOWED`.

## `directory.currency.rate.import` never saves a partial fetch

If the configured import service returns any warning message, or any
fetched rate is missing, zero, or non-numeric, the tool reports the full
fetch result — including the warnings and which rates are invalid — but
writes nothing. A partial result is surfaced for the caller to act on
rather than silently persisted alongside good rates. A fetch that yields
no usable rate at all is reported the same way (`partial: true`,
`saved: false`) rather than raised as an error.

## Extending

See `docs/EXTENDING.md` for:

- adding a new field to any tool response via `RateFieldResolverInterface`
  / `RuleFieldResolverInterface` / `TaxClassFieldResolverInterface`;
- the duplicate-resolver-key rule enforced by `ResolverPipeline`;
- the real extension path for list-tool filters (this module's search
  builders do not use a filter-translator DI array);
- how a third-party EU VAT / OSS-IOSS module would extend this one.

## License

Released under the [MIT License](LICENSE).

---

![Magebit](https://github.com/user-attachments/assets/cdc904ce-e839-40a0-a86f-792f7ab7961f)

*Have questions or need help? Contact us at info@magebit.com*
