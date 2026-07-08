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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

$adminActions = [
    'list_projects', 'get_project', 'create_project', 'update_project', 'delete_project',
    'duplicate_project', 'publish_project', 'list_pages', 'get_page', 'save_page',
    'add_page', 'delete_page', 'reorder_pages', 'set_home_page', 'compile_page',
    'save_custom_css', 'get_custom_css', 'export_project', 'parse_code', 'list_collections',
    'list_style_kits', 'preview_url',
];

if (in_array($action, $adminActions, true)) {
    studio_require_manage();
}

$repo = new StudioProjectRepository();
$compiler = new StudioCompiler($repo);
$exporter = new StudioExporter($repo);

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
        $project ? studio_ok(['project' => $project]) : studio_err('Not found', 404);
    case 'list_pages':
        $id = (string) ($input['id'] ?? $_GET['id'] ?? '');
        studio_ok(['pages' => $repo->listPages($id)]);
    case 'get_page':
        $id = (string) ($input['project_id'] ?? $_GET['project_id'] ?? '');
        $slug = (string) ($input['page_slug'] ?? $_GET['page_slug'] ?? 'index');
        $canvas = $repo->getPageCanvas($id, $slug);
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
    default:
        studio_err('Unknown action', 400);
}
