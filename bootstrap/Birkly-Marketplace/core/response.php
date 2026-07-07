<?php
/**
 * Birkly Marketplace — HTTP response helpers.
 */

/**
 * Send a JSON response and stop execution.
 */
function marketplace_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send an HTML response from a template and stop execution.
 */
function marketplace_html(string $template, array $vars = [], int $status = 200): void
{
    $templatePath = MARKETPLACE_ROOT . '/templates/' . $template;
    if (!is_file($templatePath)) {
        marketplace_json(['error' => 'template_not_found', 'template' => $template], 500);
    }

    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    extract($vars, EXTR_SKIP);
    require $templatePath;
    exit;
}

/**
 * Send a JSON error response.
 */
function marketplace_error(string $message, int $status = 400, array $extra = []): void
{
    marketplace_json(array_merge(['error' => $message], $extra), $status);
}
