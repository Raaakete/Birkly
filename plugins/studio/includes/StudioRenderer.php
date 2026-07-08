<?php
/**
 * SSR renderer for published Studio sites (P22-M9).
 */

require_once __DIR__ . '/StudioProjectRepository.php';
require_once dirname(__DIR__, 3) . '/admin/template_parser.php';
require_once dirname(__DIR__, 3) . '/core/page_bundle_runner.php';

class StudioRenderer
{
    private StudioProjectRepository $repo;

    public function __construct(?StudioProjectRepository $repo = null)
    {
        $this->repo = $repo ?? new StudioProjectRepository();
    }

    public function handlePublicRequest(string $uri): bool
    {
        if (!studio_plugin_active()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><body><h1>Studio unavailable</h1><p>Plugin is deactivated.</p></body></html>';
            return true;
        }

        if (!preg_match('#^/site/([^/]+)(?:/([^/]+)/?)?$#', $uri, $m)) {
            return false;
        }

        $projectSlug = urldecode($m[1]);
        $pageSlug = isset($m[2]) && $m[2] !== '' ? urldecode($m[2]) : null;

        if ($pageSlug === 'styles.css') {
            return $this->serveStyles($projectSlug);
        }

        $project = $this->repo->findBySlug($projectSlug);
        if (!$project || empty($project['published'])) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><body><h1>Not found</h1></body></html>';
            return true;
        }

        if ($pageSlug === null) {
            $pageSlug = $project['home_page'] ?? 'index';
        }

        $html = $this->repo->getPageHtml($project['id'], $pageSlug);
        if ($html === null) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><body><h1>Page not found</h1></body></html>';
            return true;
        }

        $rendered = $this->renderHtml($html);
        header('Content-Type: text/html; charset=utf-8');
        if (function_exists('set_public_read_cache_headers')) {
            set_public_read_cache_headers(60);
        }
        echo $rendered;
        return true;
    }

    public function renderHtml(string $html): string
    {
        $requests = $this->collectBundleRequests($html);
        $data = [];
        if ($requests !== []) {
            $root = dirname(__DIR__, 3);
            $storage = get_storage($root . '/content/collections');
            $bundle = birkly_run_page_bundle_requests($storage, $requests, 'en', ['lean' => true]);
            if (($bundle['status'] ?? '') === 'ok') {
                $data = $bundle['data'] ?? [];
            } else {
                error_log('studio_ssr: page_bundle failed: ' . json_encode($bundle['errors'] ?? []));
            }
        }

        try {
            $parser = new TemplateParser($data);
            return $parser->render($html);
        } catch (Throwable $e) {
            error_log('studio_ssr: render failed: ' . $e->getMessage());
            return $html . "\n<!-- studio_ssr: render error logged -->";
        }
    }

    private function serveStyles(string $projectSlug): bool
    {
        $project = $this->repo->findBySlug($projectSlug);
        if (!$project || empty($project['published'])) {
            http_response_code(404);
            return true;
        }
        $dir = $this->repo->projectDir($project['id']) . '/styles';
        $kit = is_file($dir . '/kit.css') ? (string) file_get_contents($dir . '/kit.css') : '';
        $custom = is_file($dir . '/custom.css') ? (string) file_get_contents($dir . '/custom.css') : '';
        $css = "/* Studio styles */\n" . $kit . "\n" . $custom;
        header('Content-Type: text/css; charset=utf-8');
        if (function_exists('set_public_read_cache_headers')) {
            set_public_read_cache_headers(300);
        }
        echo $css;
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectBundleRequests(string $html): array
    {
        $requests = [];
        $seen = [];

        if (preg_match_all("/\{for each entry in '([a-z0-9_-]+)'(?: with ([a-z0-9_-]+) = ([a-z0-9_-]+))?\}/i", $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $col = $m[1];
                if (isset($seen['list:' . $col])) {
                    continue;
                }
                $seen['list:' . $col] = true;
                $req = ['type' => 'list_entries', 'into' => $col, 'collection' => $col];
                if (!empty($m[2]) && !empty($m[3])) {
                    $req['filter_field'] = $m[2];
                    $req['filter_value'] = $m[3];
                }
                $requests[] = $req;
            }
        }

        if (preg_match_all("/\{from '([a-z0-9_-]+)' get entry '([a-z0-9_-]+)'\}/i", $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $col = $m[1];
                $slug = $m[2];
                $into = '__entry__:' . $col . ':' . $slug;
                if (isset($seen['get:' . $into])) {
                    continue;
                }
                $seen['get:' . $into] = true;
                $requests[] = ['type' => 'get_entry', 'into' => $into, 'collection' => $col, 'slug' => $slug];
            }
        }

        if (str_contains($html, 'site_elements') && !isset($seen['list:site_elements'])) {
            $requests[] = ['type' => 'list_entries', 'into' => 'site_elements', 'collection' => 'site_elements'];
        }

        return $requests;
    }
}
