<?php
/**
 * Idempotent repairs for starter-pack content on existing installs.
 *
 * Reinstalling a starter pack wipes collections; upgrades keep live entries.
 * These repairs patch known bad seeds without manual CMS edits.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * Canonical Marsk about-page intro (template owns principles, quote, facts).
 */
function birkly_starter_repair_marsk_about_content(): string
{
    return '<p class="statement">Marsk started as a stack of paper notebooks and became a website when the stack fell over.</p>'
        . '<p class="text-soft">I write about the unspectacular: mending, baking, walking, waiting. The entries are short by intention and slow by nature. Nothing here is optimized, monetized or hurried.</p>'
        . '<p class="text-soft">By day I bind books in a small studio. By early morning — the only time that\'s really mine — I write this journal.</p>';
}

/**
 * @return array<string, array{path: string, detect: callable, patch: callable}>
 */
function birkly_starter_content_repair_catalog(): array
{
    return [
        'marsk_about_page_v1' => [
            'path' => 'content/collections/about/entries/about-page.json',
            'detect' => static function (array $entry): bool {
                foreach (['en', 'de'] as $lang) {
                    if (!isset($entry[$lang]['content']) || !is_string($entry[$lang]['content'])) {
                        continue;
                    }
                    $content = $entry[$lang]['content'];
                    if (str_contains($content, 'principle-num')
                        || str_contains($content, 'class="facts"')
                        || str_contains($content, 'pull-quote')
                        || str_contains($content, 'How this journal works')) {
                        return true;
                    }
                }
                return false;
            },
            'patch' => static function (array $entry): array {
                $canonical = birkly_starter_repair_marsk_about_content();
                foreach (['en', 'de'] as $lang) {
                    if (!isset($entry[$lang]) || !is_array($entry[$lang])) {
                        continue;
                    }
                    $entry[$lang]['content'] = $canonical;
                    if (!array_key_exists('art_class', $entry[$lang])) {
                        $entry[$lang]['art_class'] = 'art--02';
                    }
                    if (!array_key_exists('caption', $entry[$lang])) {
                        $entry[$lang]['caption'] = 'Early morning, studio window';
                    }
                }
                $entry['updated_at'] = date('c');
                return $entry;
            },
        ],
    ];
}

/**
 * Run all registered starter content repairs.
 *
 * @return array{repaired: list<string>, skipped: list<string>, errors: list<string>}
 */
function birkly_run_starter_content_repairs(?string $projectRoot = null): array
{
    $projectRoot = $projectRoot ?? birkly_project_root();
    $results = [
        'repaired' => [],
        'skipped' => [],
        'errors' => [],
    ];

    foreach (birkly_starter_content_repair_catalog() as $repairId => $repair) {
        $path = $projectRoot . '/' . $repair['path'];
        if (!is_file($path)) {
            $results['skipped'][] = $repairId;
            continue;
        }

        $raw = file_get_contents($path);
        $entry = json_decode($raw ?: '', true);
        if (!is_array($entry)) {
            $results['skipped'][] = $repairId;
            continue;
        }

        if (!$repair['detect']($entry)) {
            $results['skipped'][] = $repairId;
            continue;
        }

        $patched = $repair['patch']($entry);
        $encoded = json_encode(
            $patched,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($encoded === false || file_put_contents($path, $encoded . "\n") === false) {
            $results['errors'][] = $repairId;
            continue;
        }

        $results['repaired'][] = $repairId;
    }

    return $results;
}
