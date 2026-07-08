<?php
/**
 * SSR renderer for published Studio sites (P22-M9, P24-M2).
 */

require_once __DIR__ . '/StudioProjectRepository.php';
require_once __DIR__ . '/StudioTemplateEngine.php';
require_once dirname(__DIR__, 3) . '/core/page_bundle_runner.php';

class StudioRenderer
{
    private StudioProjectRepository $repo;
    private StudioTemplateEngine $engine;

    public function __construct(?StudioProjectRepository $repo = null, ?StudioTemplateEngine $engine = null)
    {
        $this->repo = $repo ?? new StudioProjectRepository();
        $this->engine = $engine ?? new StudioTemplateEngine();
    }

    public function handlePublicRequest(string $uri): bool
    {
        if (!studio_plugin_active()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><body><h1>Studio unavailable</h1><p>Plugin is deactivated.</p></body></html>';
            return true;
        }

        if (!preg_match('#^/site/([^/]+)(?:/([^/]+))?/?$#', $uri, $m)) {
            return false;
        }

        $projectSlug = urldecode($m[1]);
        $pageSlug = isset($m[2]) && $m[2] !== '' ? urldecode($m[2]) : null;

        if ($pageSlug === 'styles.css') {
            return $this->serveStyles($projectSlug);
        }

        $project = $this->repo->findBySlug($projectSlug);
        $preview = $this->isPreviewRequest();
        $allowDraft = $preview && $this->allowAdminPreview();

        if (!$project || (empty($project['published']) && !$allowDraft)) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            $hint = $preview && !$allowDraft
                ? '<p>Draft preview requires an active admin session. Log in to Birkly admin, then try again — or click <strong>Publish</strong> in Studio.</p>'
                : '<p>Publish the project in Studio first, or use draft preview while logged in as admin.</p>';
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not found</title></head><body>'
                . '<h1>Studio site not available</h1>' . $hint
                . '<p>Project slug: <code>' . htmlspecialchars($projectSlug, ENT_QUOTES) . '</code></p></body></html>';
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
        $requests = StudioTemplateEngine::collectBundleRequests($html);
        $data = [];
        if ($requests !== []) {
            require_once dirname(__DIR__, 3) . '/core/storage/StorageFactory.php';
            $root = dirname(__DIR__, 3);
            $storage = get_storage($root . '/content/collections');
            $bundle = birkly_run_page_bundle_requests($storage, $requests, 'en', ['lean' => true]);
            if (($bundle['status'] ?? '') === 'ok') {
                $data = $bundle['data'] ?? [];
            } else {
                error_log('studio_ssr: page_bundle failed: ' . json_encode($bundle['errors'] ?? []));
            }
        }

        return $this->engine->render($html, $data);
    }

    private function serveStyles(string $projectSlug): bool
    {
        $project = $this->repo->findBySlug($projectSlug);
        $preview = $this->isPreviewRequest();
        $allowDraft = $preview && $this->allowAdminPreview();
        if (!$project || (empty($project['published']) && !$allowDraft)) {
            http_response_code(404);
            return true;
        }
        $dir = $this->repo->projectDir($project['id']) . '/styles';
        $kit = is_file($dir . '/kit.css') ? (string) file_get_contents($dir . '/kit.css') : '';
        $custom = is_file($dir . '/custom.css') ? (string) file_get_contents($dir . '/custom.css') : '';
        $generated = is_file($dir . '/generated.css') ? (string) file_get_contents($dir . '/generated.css') : '';
        $css = "/* Studio styles */\n" . $kit . "\n" . $generated . "\n" . $custom;
        header('Content-Type: text/css; charset=utf-8');
        if (function_exists('set_public_read_cache_headers')) {
            set_public_read_cache_headers(300);
        }
        echo $css;
        return true;
    }

    private function isPreviewRequest(): bool
    {
        return isset($_GET['birkly_studio_preview']) && $_GET['birkly_studio_preview'] === '1';
    }

    private function allowAdminPreview(): bool
    {
        if (!function_exists('haru_secure_session_start')) {
            require_once dirname(__DIR__, 3) . '/api/session.php';
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            haru_secure_session_start();
        }
        return function_exists('studio_user_can_manage') && studio_user_can_manage();
    }
}
