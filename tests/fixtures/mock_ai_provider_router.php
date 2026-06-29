<?php
/**
 * Minimal OpenAI-compatible mock for BYOAI provider tests (P4.1-M4).
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uri === '/v1/chat/completions' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    header('Content-Type: application/json');

    if ($auth !== 'Bearer valid-mock-key') {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'Invalid API key', 'type' => 'invalid_request_error']]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'id' => 'mock-chat',
        'object' => 'chat.completion',
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => 'pong'],
            'finish_reason' => 'stop',
        ]],
    ]);
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'not found']);
