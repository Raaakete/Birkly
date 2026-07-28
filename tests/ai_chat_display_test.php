<?php
/**
 * AI chat display sanitization and project path normalization tests.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/ai_helpers.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

assert_true(
    'strips project/ prefix preserving filename',
    birkly_ai_normalize_project_file_path('project/index.html') === 'index.html'
);
assert_true(
    'strips project/ from about.html',
    birkly_ai_normalize_project_file_path('project/about.html') === 'about.html'
);
assert_true(
    'fixes ndex typo to index',
    birkly_ai_normalize_project_file_path('ndex.html') === 'index.html'
);

assert_true(
    'delete keeps ndex typo filename',
    birkly_ai_normalize_project_file_path_for_delete('ndex.html') === 'ndex.html'
);

assert_true(
    'strips nested project/ prefix',
    birkly_ai_normalize_project_file_path('project/project/css/style.css') === 'css/style.css'
);

$raw = "Here is my answer.\n\n{\"actions\":[{\"type\":\"write_project_file\",\"path\":\"index.html\",\"content\":\"test\"}]}\n\nDone.";
$clean = ai_chat_sanitize_display_message($raw);
assert_true('removes inline actions JSON', !str_contains($clean, '"actions"') && str_contains($clean, 'Here is my answer'));
assert_true('keeps prose after strip', str_contains($clean, 'Done.'));

$withNotes = "Hello\n\n[Session notes — already done: project files: index.html]";
assert_true('removes session notes from display', ai_chat_sanitize_display_message($withNotes) === 'Hello');

exit($failures > 0 ? 1 : 0);
