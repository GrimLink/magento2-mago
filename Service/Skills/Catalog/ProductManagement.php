<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Catalog;

use MaggyAssistant\Base\Service\Skills\AbstractSkill;

class ProductManagement extends AbstractSkill
{
    public function getName(): string
    {
        return 'product_management';
    }

    protected function getBaseDescription(): string
    {
        return 'Create catalog products of any type (simple, virtual, downloadable, configurable, grouped, bundle).';
    }

    public function getMagentoAcl(array $input = []): string
    {
        return 'Magento_Catalog::products';
    }

    protected function getBaseInstructions(): string
    {
        return <<<'TEXT'
Merchants think in SKU, name, price, image and description — not in product types or attribute sets.
Work from what they give you:
- Only the name and a price are truly needed. Derive a SKU from the name when none is given (uppercase,
  dashes) and mention it. Assume product_type "simple" and the default attribute set unless the request
  clearly says otherwise (variants → configurable, service → virtual, file → downloadable, set of
  existing products → grouped, build-your-own kit → bundle).
- Ask at most one short follow-up, and only when something essential is missing (e.g. no price). Never
  ask about product type, attribute sets or stock. Do not invent descriptions, stock, weight or
  categories. Propose the minimal product; the confirmation prompt is the user's check.
- If a request needs many details (images, several attributes, long descriptions), create the minimal
  product anyway and point the user to the admin edit link to finish it there — do not interrogate.
- Attribute sets matter only for configurable products: the parent's set must contain the variant
  attributes (e.g. color). Use "list_attribute_sets" to pick one that lists them under
  variant_attributes and tell the user which set you picked and why. Never guess set IDs.
- Configurable flow: "create_product" for the parent, then in the same turn "add_configurable_variants"
  with one variant per combination (check labels with "get_attribute_options" first). Never reply with
  "I will now add the variants" without actually calling the action.
- Grouped and bundle children must already exist; create_product reports every unknown child SKU. Offer
  to create those as simple products first (ask name/price only if not given), then retry the parent.
- create_product refuses once when products with a similar name exist and returns them. Show the user
  the list briefly and ask whether to continue; on yes, call again with ignore_similar: true.
After creating, reply short: what was created (SKU, name, price), the admin edit link from the result,
and the "missing" list as things they can fill in there (image, description, stock, categories).
Prices are in the store's base currency.
TEXT;
    }
}
