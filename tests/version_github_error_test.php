<?php
/**
 * GitHub update-check error handling (private repo / API error responses).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/version.php';

$failed = 0;

function assert_true(string $label, bool $condition): void
{
    global $failed;
    if (!$condition) {
        echo "FAIL: $label\n";
        $failed++;
        return;
    }
    echo "OK: $label\n";
}

$notFound = [
    'message' => 'Not Found',
    'documentation_url' => 'https://docs.github.com/rest/releases/releases#get-the-latest-release',
    'status' => 404,
];

assert_true(
    'private repo 404 without token asks for github_token',
    str_contains(
        BirklyVersion::githubApiErrorMessageForTest($notFound, 404, $notFound, 404, false),
        'github_token'
    )
);

assert_true(
    '404 with token reports missing releases',
    str_contains(
        BirklyVersion::githubApiErrorMessageForTest($notFound, 404, $notFound, 404, true),
        'No published releases'
    )
);

assert_true(
    'github error object is not treated as tag list',
    !BirklyVersion::isGithubTagListForTest($notFound)
);

assert_true(
    'tag list is detected',
    BirklyVersion::isGithubTagListForTest([
        ['name' => 'v1.0.2', 'commit' => ['sha' => 'abc']],
        ['name' => 'v1.0.1', 'commit' => ['sha' => 'def']],
    ])
);

exit($failed === 0 ? 0 : 1);
