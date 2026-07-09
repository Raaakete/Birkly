<?php
/**
 * Studio admin API (P22-M2+).
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../api/session.php';
haru_secure_session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../init.php';

if (!studio_plugin_active()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Studio plugin is not active']);
    exit;
}

require_once __DIR__ . '/../includes/StudioProjectRepository.php';
require_once __DIR__ . '/../includes/StudioCompiler.php';
require_once __DIR__ . '/../includes/StudioExporter.php';
require_once __DIR__ . '/../includes/StudioFileSystem.php';
require_once __DIR__ . '/../includes/StudioRevisionStore.php';
require_once __DIR__ . '/../includes/StudioBlockRegistry.php';
require_once __DIR__ . '/../includes/StudioTemplateEngine.php';
require_once __DIR__ . '/../includes/StudioCanvasMigrator.php';
require_once __DIR__ . '/../includes/StudioCreative.php';
require_once __DIR__ . '/../includes/StudioFigmaImporter.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

$adminActions = [
    'list_projects', 'get_project', 'create_project', 'update_project', 'delete_project',
    'duplicate_project', 'publish_project', 'list_pages', 'get_page', 'save_page',
    'add_page', 'delete_page', 'reorder_pages', 'set_home_page', 'compile_page',
    'save_custom_css', 'get_custom_css', 'export_project', 'parse_code', 'list_collections',
    'list_style_kits', 'preview_url', 'open_project_url', 'diagnose_public',
    'list_files', 'read_file', 'write_file', 'create_file', 'create_directory',
    'delete_path', 'rename_path', 'list_revisions', 'list_studio_blocks', 'list_sections',
    'insert_section', 'get_tokens', 'save_tokens', 'list_components', 'save_component',
    'get_canvas_styles', 'preview_live', 'get_page_settings', 'save_page_settings',
    'get_funnel', 'save_funnel', 'import_figma',
];

if (in_array($action, $adminActions, true)) {
    studio_require_manage();
}

$repo = new StudioProjectRepository();
$compiler = new StudioCompiler($repo);
$exporter = new StudioExporter($repo);
$fs = new StudioFileSystem($repo);
$revisions = new StudioRevisionStore($repo);
studio_load_plugin_blocks();

function studio_ok($data = []): void
{
    echo json_encode(array_merge(['status' => 'ok'], is_array($data) ? $data : []));
    exit;
}

function studio_err(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

switch ($action) {
    case 'list_projects':
        studio_ok(['projects' => $repo->listProjects()]);
    case 'get_project':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $project = $repo->getProject($id);
        $project ? studio_ok(['project' => $project]) : studio_err('Not found', 404);
    case 'create_project':
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            studio_err('Name required');
        }
        $kit = (string) ($input['style_kit'] ?? 'minimal');
        $slug = isset($input['slug']) ? (string) $input['slug'] : null;
        studio_ok(['project' => $repo->createProject($name, $kit, $slug)]);
    case 'update_project':
        $id = (string) ($input['id'] ?? '');
        $project = $repo->updateProject($id, $input);
        $project ? studio_ok(['project' => $project]) : studio_err('Not found', 404);
    case 'delete_project':
        $id = (string) ($input['id'] ?? '');
        studio_ok(['deleted' => $repo->deleteProject($id)]);
    case 'duplicate_project':
        $id = (string) ($input['id'] ?? '');
        $name = trim((string) ($input['name'] ?? 'Copy'));
        $project = $repo->duplicateProject($id, $name);
        $project ? studio_ok(['project' => $project]) : studio_err('Not found', 404);
    case 'publish_project':
        $id = (string) ($input['id'] ?? '');
        $project = $repo->publishProject($id);
        if (!$project) {
            studio_err('Not found', 404);
        }
        require_once __DIR__ . '/../../../core/health.php';
        $route = birkly_health_studio();
        studio_ok([
            'project' => $project,
            'public_url' => studio_public_site_url($project['slug'] ?? $id, false),
            'view_url' => studio_view_url($project['slug'] ?? $id, false),
            'route_ready' => $route['route_ready'],
            'route_hints' => $route['hints'],
        ]);
    case 'list_pages':
        $id = (string) ($input['id'] ?? $_GET['id'] ?? '');
        studio_ok(['pages' => $repo->listPages($id)]);
    case 'get_page':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? $_GET['page_slug'] ?? 'index');
        $canvas = $repo->getPageCanvas($id, $slug);
        if ($canvas) {
            $canvas = StudioCanvasMigrator::upgrade($canvas);
        }
        $html = $repo->getPageHtml($id, $slug);
        $canvas ? studio_ok(['canvas' => $canvas, 'html' => $html]) : studio_err('Not found', 404);
    case 'save_page':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? 'index');
        $canvas = $input['canvas'] ?? null;
        if (!is_array($canvas)) {
            studio_err('canvas required');
        }
        $saved = $repo->savePageCanvas($id, $slug, $canvas);
        $saved ? studio_ok(['canvas' => $saved]) : studio_err('Save failed', 500);
    case 'add_page':
        $id = (string) ($input['project_id'] ?? '');
        $title = trim((string) ($input['title'] ?? 'New page'));
        studio_ok(['page' => $repo->addPage($id, $title, $input['slug'] ?? null)]);
    case 'delete_page':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? '');
        studio_ok(['deleted' => $repo->deletePage($id, $slug)]);
    case 'reorder_pages':
        $id = (string) ($input['project_id'] ?? '');
        $order = $input['order'] ?? [];
        studio_ok(['ok' => $repo->reorderPages($id, is_array($order) ? $order : [])]);
    case 'set_home_page':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? 'index');
        $project = $repo->setHomePage($id, $slug);
        $project ? studio_ok(['project' => $project]) : studio_err('Not found', 404);
    case 'compile_page':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? 'index');
        $html = $compiler->compilePage($id, $slug);
        studio_ok(['html' => $html]);
    case 'save_custom_css':
        $id = (string) ($input['project_id'] ?? '');
        $css = (string) ($input['css'] ?? '');
        studio_ok(['saved' => $repo->saveCustomCss($id, $css)]);
    case 'get_custom_css':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        studio_ok(['css' => $repo->getCustomCss($id)]);
    case 'get_canvas_styles':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $project = $repo->getProject($id);
        if (!$project) {
            studio_err('Not found', 404);
        }
        $compiler->writeGeneratedStyles($id);
        $styleDir = $repo->projectDir($id) . '/styles';
        $readCss = static function (string $path): string {
            return is_file($path) ? (string) file_get_contents($path) : '';
        };
        $kit = $readCss($styleDir . '/kit.css');
        $generated = $readCss($styleDir . '/generated.css');
        $custom = $readCss($styleDir . '/custom.css');
        $projectSlug = $project['slug'] ?? $id;
        $revision = (int) ($project['revision'] ?? 1);
        $base = '/site/' . rawurlencode($projectSlug) . '/styles.css?v=' . $revision;
        $stylesUrl = function_exists('birkly_url') ? birkly_url($base) : $base;
        studio_ok([
            'css' => "/* kit */\n" . $kit . "\n/* generated */\n" . $generated . "\n/* custom */\n" . $custom,
            'kit' => $kit,
            'generated' => $generated,
            'custom' => $custom,
            'urls' => [
                'bundle' => $stylesUrl,
                'kit' => $stylesUrl,
                'generated' => $stylesUrl,
                'custom' => $stylesUrl,
            ],
        ]);
    case 'preview_live':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? 'index');
        $project = $repo->getProject($id);
        if (!$project) {
            studio_err('Not found', 404);
        }
        $compiler->compilePage($id, $slug);
        $compiled = $repo->getPageHtml($id, $slug) ?? '';
        $requests = StudioTemplateEngine::collectBundleRequests($compiled);
        $bundleData = [];
        if ($requests !== []) {
            require_once __DIR__ . '/../../../core/page_bundle_runner.php';
            require_once __DIR__ . '/../../../core/storage/StorageFactory.php';
            $root = dirname(__DIR__, 3);
            $storage = get_storage($root . '/content/collections');
            $bundle = birkly_run_page_bundle_requests($storage, $requests, 'en', ['lean' => true]);
            if (($bundle['status'] ?? '') === 'ok') {
                $bundleData = $bundle['data'] ?? [];
            }
        }
        $engine = new StudioTemplateEngine();
        $rendered = $engine->render($compiled, is_array($bundleData) ? $bundleData : []);
        studio_ok(['html' => $rendered, 'bundle_requests' => count($requests)]);
    case 'get_page_settings':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? $_GET['page_slug'] ?? 'index');
        $canvas = $repo->getPageCanvas($id, $slug);
        if (!$canvas) {
            studio_err('Not found', 404);
        }
        $meta = $canvas['page']['meta'] ?? $canvas['meta'] ?? [];
        studio_ok([
            'settings' => is_array($meta) ? $meta : [],
            'page' => $canvas['page'] ?? ['slug' => $slug],
        ]);
    case 'save_page_settings':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? 'index');
        $canvas = $repo->getPageCanvas($id, $slug);
        if (!$canvas) {
            studio_err('Not found', 404);
        }
        $meta = $input['meta'] ?? $input['settings'] ?? [];
        if (!is_array($meta)) {
            studio_err('meta must be object');
        }
        if (!isset($canvas['page']) || !is_array($canvas['page'])) {
            $canvas['page'] = ['slug' => $slug];
        }
        $canvas['page']['meta'] = $meta;
        $canvas['meta'] = $meta;
        $saved = $repo->savePageCanvas($id, $slug, $canvas);
        $saved ? studio_ok(['settings' => $meta, 'canvas' => $saved]) : studio_err('Save failed', 500);
    case 'export_project':
        $id = (string) ($input['id'] ?? '');
        $result = $exporter->exportZip($id);
        if (empty($result['ok'])) {
            studio_err($result['error'] ?? 'Export failed', 500);
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . ($result['filename'] ?? 'export.zip') . '"');
        readfile($result['path']);
        @unlink($result['path']);
        exit;
    case 'parse_code':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? 'index');
        $html = (string) ($input['html'] ?? '');
        $existing = $repo->getPageCanvas($id, $slug) ?? [];
        $canvas = $compiler->parseHtmlToCanvas($html, $existing);
        file_put_contents($repo->pageHtmlPath($id, $slug), $html);
        $saved = $repo->savePageCanvas($id, $slug, $canvas, false);
        studio_ok(['canvas' => $saved, 'html' => $html]);
    case 'list_collections':
        $root = dirname(__DIR__, 3) . '/content/collections';
        $cols = [];
        if (is_dir($root)) {
            foreach (scandir($root) ?: [] as $d) {
                if ($d === '.' || $d === '..' || !is_dir($root . '/' . $d)) {
                    continue;
                }
                $cols[] = $d;
            }
        }
        sort($cols);
        studio_ok(['collections' => $cols]);
    case 'list_style_kits':
        studio_ok(['kits' => array_values(studio_style_kits())]);
    case 'preview_url':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? $_GET['page_slug'] ?? 'index');
        $project = $repo->getProject($id);
        if (!$project) {
            studio_err('Not found', 404);
        }
        $compiler->compilePage($id, $slug);
        $htmlPath = $repo->pageHtmlPath($id, $slug);
        $base = function_exists('birkly_url') ? birkly_url('') : '';
        studio_ok([
            'preview_html' => is_file($htmlPath) ? file_get_contents($htmlPath) : '',
            'cms_url' => $base,
        ]);
    case 'open_project_url':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $project = $repo->getProject($id);
        if (!$project) {
            studio_err('Not found', 404);
        }
        $slug = $project['slug'] ?? $id;
        $published = !empty($project['published']);
        if (!$published) {
            foreach ($repo->listPageSlugs($id) as $pageSlug) {
                $compiler->compilePage($id, $pageSlug);
            }
        }
        $url = studio_view_url($slug, !$published);
        require_once __DIR__ . '/../../../core/health.php';
        $route = birkly_health_studio();
        studio_ok([
            'url' => $url,
            'public_url' => studio_public_site_url($slug, !$published),
            'published' => $published,
            'route_ready' => $route['route_ready'],
            'route_hints' => $route['hints'],
        ]);
    case 'diagnose_public':
        require_once __DIR__ . '/../../../core/health.php';
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $project = $id !== '' ? $repo->getProject($id) : null;
        $route = birkly_health_studio();
        $out = ['route' => $route];
        if ($project) {
            $out['project'] = [
                'id' => $project['id'],
                'slug' => $project['slug'] ?? $project['id'],
                'published' => !empty($project['published']),
            ];
            if (empty($project['published'])) {
                $out['route']['hints'][] = 'Project is not published — use draft preview (?birkly_studio_preview=1) while logged in, or click Publish.';
            }
        }
        studio_ok($out);
    case 'list_files':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        studio_ok(['tree' => $fs->listTree($id)]);
    case 'read_file':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $path = (string) ($input['path'] ?? $_GET['path'] ?? '');
        $file = $fs->readFile($id, $path);
        $file ? studio_ok(['file' => $file]) : studio_err('Not found', 404);
    case 'write_file':
        $id = (string) ($input['project_id'] ?? '');
        $path = (string) ($input['path'] ?? '');
        $content = (string) ($input['content'] ?? '');
        studio_ok(['saved' => $fs->writeFile($id, $path, $content)]);
    case 'create_file':
        $id = (string) ($input['project_id'] ?? '');
        $path = (string) ($input['path'] ?? '');
        $content = (string) ($input['content'] ?? '');
        $fs->createFile($id, $path, $content) ? studio_ok() : studio_err('Create failed', 400);
    case 'create_directory':
        $id = (string) ($input['project_id'] ?? '');
        $path = (string) ($input['path'] ?? '');
        $fs->createDirectory($id, $path) ? studio_ok() : studio_err('Create failed', 400);
    case 'delete_path':
        $id = (string) ($input['project_id'] ?? '');
        $path = (string) ($input['path'] ?? '');
        studio_ok(['deleted' => $fs->deletePath($id, $path)]);
    case 'rename_path':
        $id = (string) ($input['project_id'] ?? '');
        $from = (string) ($input['from'] ?? '');
        $to = (string) ($input['to'] ?? '');
        studio_ok(['renamed' => $fs->renamePath($id, $from, $to)]);
    case 'list_revisions':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $path = (string) ($input['path'] ?? $_GET['path'] ?? '');
        studio_ok(['revisions' => $revisions->listForPath($id, $path)]);
    case 'list_studio_blocks':
        studio_ok(['blocks' => array_values(get_studio_blocks())]);
    case 'list_sections':
        $sections = [];
        $dir = dirname(__DIR__) . '/sections';
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $sections[] = $data;
            }
        }
        studio_ok(['sections' => $sections]);
    case 'insert_section':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? 'index');
        $sectionId = (string) ($input['section_id'] ?? 'hero');
        $libDir = dirname(__DIR__) . '/sections';
        $libFile = $libDir . '/' . preg_replace('/[^a-z0-9_-]/', '', $sectionId) . '.json';
        if (!is_file($libFile)) {
            studio_err('Section not found', 404);
        }
        $section = json_decode((string) file_get_contents($libFile), true);
        $canvas = $repo->getPageCanvas($id, $slug) ?? StudioCanvasMigrator::upgrade(['version' => 2, 'sections' => []]);
        $canvas['sections'][] = $section['section'] ?? $section;
        $saved = $repo->savePageCanvas($id, $slug, $canvas);
        studio_ok(['canvas' => $saved]);
    case 'get_tokens':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $path = $repo->projectDir($id) . '/tokens.json';
        $tokens = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        studio_ok(['tokens' => is_array($tokens) ? $tokens : []]);
    case 'save_tokens':
        $id = (string) ($input['project_id'] ?? '');
        $tokens = $input['tokens'] ?? [];
        if (!is_array($tokens)) {
            studio_err('tokens must be object');
        }
        $path = $repo->projectDir($id) . '/tokens.json';
        file_put_contents($path, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        studio_ok();
    case 'list_components':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $dir = $repo->projectDir($id) . '/components';
        $list = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') ?: [] as $f) {
                $list[] = ['name' => basename($f, '.json'), 'data' => json_decode((string) file_get_contents($f), true)];
            }
        }
        studio_ok(['components' => $list]);
    case 'save_component':
        $id = (string) ($input['project_id'] ?? '');
        $name = preg_replace('/[^a-z0-9_-]/', '', (string) ($input['name'] ?? ''));
        $data = $input['data'] ?? [];
        if ($name === '' || !is_array($data)) {
            studio_err('Invalid component');
        }
        $dir = $repo->projectDir($id) . '/components';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $name . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        studio_ok();
    case 'get_funnel':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $project = $repo->getProject($id);
        if (!$project) {
            studio_err('Not found', 404);
        }
        $funnel = StudioCreative::readFunnel($repo->projectDir($id));
        studio_ok(['funnel' => $funnel ?? ['enabled' => false, 'steps' => [], 'title' => 'Progress']]);
    case 'save_funnel':
        $id = (string) ($input['project_id'] ?? '');
        $project = $repo->getProject($id);
        if (!$project) {
            studio_err('Not found', 404);
        }
        $funnel = $input['funnel'] ?? [];
        if (!is_array($funnel)) {
            studio_err('funnel must be object');
        }
        StudioCreative::writeFunnel($repo->projectDir($id), $funnel);
        studio_ok(['funnel' => $funnel]);
    case 'import_figma':
        $id = (string) ($input['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? 'index');
        $json = $input['json'] ?? null;
        if (!is_array($json)) {
            studio_err('json required');
        }
        $canvas = $repo->getPageCanvas($id, $slug);
        if (!$canvas) {
            studio_err('Not found', 404);
        }
        $result = StudioFigmaImporter::import($json);
        foreach ($result['sections'] as $section) {
            $canvas['sections'][] = $section;
        }
        if ($result['tokens'] !== []) {
            $tokenPath = $repo->projectDir($id) . '/tokens.json';
            $existing = is_file($tokenPath) ? json_decode((string) file_get_contents($tokenPath), true) : [];
            if (!is_array($existing)) {
                $existing = [];
            }
            foreach ($result['tokens'] as $group => $vals) {
                if (!is_array($vals)) {
                    continue;
                }
                $existing[$group] = array_merge($existing[$group] ?? [], $vals);
            }
            file_put_contents($tokenPath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            $compiler->writeGeneratedStyles($id);
        }
        $saved = $repo->savePageCanvas($id, $slug, $canvas);
        studio_ok([
            'canvas' => $saved,
            'sections_added' => count($result['sections']),
            'tokens' => $result['tokens'],
            'warnings' => $result['warnings'],
        ]);
    default:
        studio_err('Unknown action', 400);
}
