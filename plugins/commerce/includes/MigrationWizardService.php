<?php
/**
 * P6.6 — Scan site for Shopping Cart / Events → Commerce migration opportunities.
 */

class MigrationWizardService
{
    /** @return array<string, mixed> */
    public function scan(?string $contentRoot = null): array
    {
        $root = $contentRoot ?? dirname(__DIR__, 3) . '/content';
        $collectionsDir = $root . '/collections';
        $findings = [
            'legacy_shopping_cart' => [],
            'legacy_events' => [],
            'commerce_ready' => [],
            'recommendations' => [],
        ];

        if (!is_dir($collectionsDir)) {
            return $findings;
        }

        foreach (scandir($collectionsDir) ?: [] as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }
            $schemaFile = $collectionsDir . '/' . $slug . '/schema.json';
            if (!is_file($schemaFile)) {
                continue;
            }
            $schema = json_decode((string) file_get_contents($schemaFile), true);
            if (!is_array($schema)) {
                continue;
            }

            $fields = $schema['fields'] ?? [];
            $hasCommerce = false;
            $hasBundle = false;
            $commerceTypes = [];

            foreach ($fields as $field) {
                $type = strtolower((string) ($field['type'] ?? ''));
                if ($type === 'commerce' || str_starts_with($type, 'commerce_')) {
                    $hasCommerce = true;
                    $commerceTypes[] = $type;
                }
                if ($type === 'commerce_bundle') {
                    $hasBundle = true;
                }
            }

            if ($hasCommerce || $hasBundle) {
                $findings['commerce_ready'][] = [
                    'collection' => $slug,
                    'name' => $schema['name'] ?? $slug,
                    'fieldtypes' => array_values(array_unique(array_merge($commerceTypes, $hasBundle ? ['commerce_bundle'] : []))),
                ];
                continue;
            }

            $nameLower = strtolower($slug . ' ' . ($schema['name'] ?? ''));
            if (preg_match('/\b(products?|shop|store|cart)\b/', $nameLower)) {
                $findings['legacy_shopping_cart'][] = [
                    'collection' => $slug,
                    'name' => $schema['name'] ?? $slug,
                    'suggestion' => 'Add commerce or commerce_physical fieldtype; enable variants sub-collection for SKUs.',
                ];
            }
            if (preg_match('/\b(events?|classes?|workshop|ticket|rsvp)\b/', $nameLower)) {
                $findings['legacy_events'][] = [
                    'collection' => $slug,
                    'name' => $schema['name'] ?? $slug,
                    'suggestion' => 'Add commerce_events fieldtype profile (fulfillment: register) or legacy Events plugin.',
                ];
            }
        }

        if (!empty($findings['legacy_shopping_cart'])) {
            $findings['recommendations'][] = 'Migrate product collections to Commerce fieldtype with ship fulfillment and purchase_options.';
        }
        if (!empty($findings['legacy_events'])) {
            $findings['recommendations'][] = 'Paid events: use Commerce fieldtype with register fulfillment. Free RSVP can keep Events plugin.';
        }
        if (empty($findings['commerce_ready']) && empty($findings['legacy_shopping_cart']) && empty($findings['legacy_events'])) {
            $findings['recommendations'][] = 'No legacy patterns detected. Create a collection and add a Commerce fieldtype from Commerce → Fieldtypes.';
        }

        return $findings;
    }

    /** @return list<array<string, string>> */
    public function getGuides(): array
    {
        return [
            [
                'id' => 'shopping_cart',
                'title' => 'Shopping Cart → Commerce',
                'steps' => '1. Commerce → Fieldtypes → use Physical Products preset. 2. Add commerce field to product collection. 3. Reindex catalog. 4. Update templates to commerce_buy_url. 5. Deactivate shopping_cart when ready.',
            ],
            [
                'id' => 'events',
                'title' => 'Events → Commerce (paid tickets)',
                'steps' => '1. Fieldtypes → Events preset (register fulfillment). 2. Add field to classes collection. 3. Set capacity on entry. 4. Automation on commerce_event_registered. 5. Legacy Events plugin optional for free RSVP.',
            ],
            [
                'id' => 'bundles',
                'title' => 'Product bundles',
                'steps' => '1. Ensure component entries are in catalog. 2. Create offers/bundles collection with commerce_bundle fieldtype. 3. Pick components from catalog picker. 4. Set pricing_mode (sum, discount, or fixed).',
            ],
        ];
    }
}
