<?php
/**
 * Product collection resolution for Commerce CSV import.
 */

if (!class_exists('CommerceSchema')) {
    require_once __DIR__ . '/CommerceSchema.php';
}

class ImportCollectionHelper
{
    public const COMMERCE_FIELD_NAME = 'pricing';
    public const VARIANTS_SUB_SLUG = 'variants';

    private string $contentRoot;

    public function __construct(?string $contentRoot = null)
    {
        $this->contentRoot = $contentRoot ?? dirname(__DIR__, 3) . '/content';
    }

    public static function isCommerceFieldType(string $type): bool
    {
        $type = strtolower(trim($type));
        if ($type === 'commerce_bundle') {
            return false;
        }

        return $type === 'commerce' || str_starts_with($type, 'commerce_');
    }

    /** @return list<array<string, mixed>> */
    public function listProductCollections(): array
    {
        $collectionsDir = $this->contentRoot . '/collections';
        if (!is_dir($collectionsDir)) {
            return [];
        }

        $out = [];
        foreach (scandir($collectionsDir) ?: [] as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $metaPath = $collectionsDir . '/' . $dir . '/collection.json';
            if (!is_file($metaPath)) {
                continue;
            }
            $meta = json_decode((string) file_get_contents($metaPath), true);
            if (!is_array($meta)) {
                continue;
            }
            $slug = $meta['slug'] ?? $dir;
            $info = $this->describeCollection($slug);
            if ($info === null) {
                continue;
            }
            $out[] = array_merge($info, [
                'id' => $meta['id'] ?? ('col-' . $slug),
                'title' => $meta['title'] ?? $meta['name'] ?? $slug,
                'slug' => $slug,
            ]);
        }

        usort($out, fn ($a, $b) => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));

        return $out;
    }

    /**
     * @param array{create?: bool, title?: string|null} $options
     * @return array{ok: bool, slug?: string, commerce_field?: string, variants_sub?: string, created?: bool, message?: string}
     */
    public function resolveForImport(string $slug, array $options = []): array
    {
        $slug = $this->slugify($slug);
        $create = !empty($options['create']);
        $title = trim((string) ($options['title'] ?? ''));

        if ($create) {
            if ($title === '') {
                $title = ucwords(str_replace('-', ' ', $slug));
            }
            $slug = $this->slugify($title);
            $slug = $this->ensureUniqueSlug($slug);
            $created = $this->createProductCollection($slug, $title);
            if (!$created) {
                return ['ok' => false, 'message' => 'Failed to create collection'];
            }

            return [
                'ok' => true,
                'slug' => $slug,
                'commerce_field' => self::COMMERCE_FIELD_NAME,
                'variants_sub' => self::VARIANTS_SUB_SLUG,
                'created' => true,
            ];
        }

        $info = $this->describeCollection($slug);
        if ($info === null) {
            return [
                'ok' => false,
                'message' => 'Collection not found or missing a Commerce fieldtype. Choose an existing product collection or create a new one.',
            ];
        }

        return [
            'ok' => true,
            'slug' => $slug,
            'commerce_field' => $info['commerce_field'],
            'variants_sub' => $info['variants_sub'],
            'created' => false,
        ];
    }

    public function ensureVariantsSubCollection(string $collectionSlug, string $commerceField = self::COMMERCE_FIELD_NAME): bool
    {
        $metaPath = $this->contentRoot . '/collections/' . $collectionSlug . '/collection.json';
        if (!is_file($metaPath)) {
            return false;
        }
        $meta = json_decode((string) file_get_contents($metaPath), true);
        if (!is_array($meta)) {
            return false;
        }

        $subCollections = $meta['sub_collections'] ?? [];
        foreach ($subCollections as $sub) {
            if (($sub['slug'] ?? '') === self::VARIANTS_SUB_SLUG) {
                return true;
            }
        }

        $subCollections[] = $this->defaultVariantsSubCollection($commerceField);
        $meta['sub_collections'] = $subCollections;
        $meta['updated_at'] = date('c');

        return $this->writeJson($metaPath, $meta);
    }

    /** @return array{commerce_field: string, variants_sub: string, has_variants_sub: bool}|null */
    public function describeCollection(string $slug): ?array
    {
        $dir = $this->contentRoot . '/collections/' . $slug;
        if (!is_dir($dir)) {
            return null;
        }

        $schema = $this->readSchema($slug);
        $commerceField = $this->findCommerceFieldInFields($schema['fields'] ?? []);
        $variantsSub = self::VARIANTS_SUB_SLUG;
        $hasVariantsSub = false;

        $metaPath = $dir . '/collection.json';
        if (is_file($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true);
            foreach ($meta['sub_collections'] ?? [] as $sub) {
                if (($sub['slug'] ?? '') !== self::VARIANTS_SUB_SLUG) {
                    continue;
                }
                $hasVariantsSub = true;
                $subFields = $sub['schema']['fields'] ?? $sub['fields'] ?? [];
                $subCommerce = $this->findCommerceFieldInFields($subFields);
                if ($subCommerce !== null) {
                    $commerceField = $commerceField ?? $subCommerce;
                }
            }
        }

        if ($commerceField === null) {
            return null;
        }

        return [
            'commerce_field' => $commerceField ?? self::COMMERCE_FIELD_NAME,
            'variants_sub' => $variantsSub,
            'has_variants_sub' => $hasVariantsSub,
        ];
    }

    public function createProductCollection(string $slug, string $title): bool
    {
        $dir = $this->contentRoot . '/collections/' . $slug;
        if (is_dir($dir)) {
            return is_file($dir . '/schema.json');
        }

        if (!mkdir($dir . '/entries', 0755, true) && !is_dir($dir . '/entries')) {
            return false;
        }

        $now = date('c');
        $meta = [
            'id' => 'col-' . uniqid(),
            'title' => $title,
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
            'sub_collections' => [$this->defaultVariantsSubCollection()],
        ];

        $schema = ['fields' => $this->defaultProductFields()];

        return $this->writeJson($dir . '/collection.json', $meta)
            && $this->writeJson($dir . '/schema.json', $schema);
    }

    /** @return list<array<string, mixed>> */
    private function defaultProductFields(): array
    {
        return [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
            ['name' => 'slug', 'type' => 'text', 'label' => 'Slug'],
            ['name' => 'body', 'type' => 'textarea', 'label' => 'Description'],
            ['name' => self::COMMERCE_FIELD_NAME, 'type' => 'commerce', 'label' => 'Pricing'],
            [
                'name' => 'status',
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    ['label' => 'Draft', 'value' => 'draft'],
                    ['label' => 'Published', 'value' => 'published'],
                ],
                'default' => 'published',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function defaultVariantsSubCollection(string $commerceField = self::COMMERCE_FIELD_NAME): array
    {
        return [
            'name' => 'Variants',
            'slug' => self::VARIANTS_SUB_SLUG,
            'entry_method' => 'admin_only',
            'schema' => [
                'fields' => [
                    ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
                    ['name' => 'variant_label', 'type' => 'text', 'label' => 'Variant label'],
                    ['name' => $commerceField, 'type' => 'commerce', 'label' => 'Pricing'],
                ],
            ],
        ];
    }

    /** @param list<array<string, mixed>> $fields */
    private function findCommerceFieldInFields(array $fields): ?string
    {
        foreach ($fields as $field) {
            $type = (string) ($field['type'] ?? '');
            if (self::isCommerceFieldType($type)) {
                $name = trim((string) ($field['name'] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return null;
    }

    /** @return array{fields: list<array<string, mixed>>} */
    private function readSchema(string $slug): array
    {
        $schemaPath = $this->contentRoot . '/collections/' . $slug . '/schema.json';
        if (!is_file($schemaPath)) {
            return ['fields' => []];
        }
        $schema = json_decode((string) file_get_contents($schemaPath), true);

        return is_array($schema) ? $schema : ['fields' => []];
    }

    private function ensureUniqueSlug(string $slug): string
    {
        $base = $slug;
        $i = 2;
        while (is_dir($this->contentRoot . '/collections/' . $slug)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-') ?: 'products';
    }

    public function previewSlugForTitle(string $title): string
    {
        return $this->ensureUniqueSlug($this->slugify($title));
    }

    private function writeJson(string $path, array $data): bool
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json) !== false;
    }
}
