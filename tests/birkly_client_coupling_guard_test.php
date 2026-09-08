<?php
/**
 * P82-M4 — birkly-client.js coupling guard (forbidden plugin literals).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$client = (string) file_get_contents($root . '/birkly-client.js');

$forbidden = [
    'user_management',
    'syncClubProfileForms',
    'user_signup',
    'user_auth',
    'user_account',
    'club_profile',
    'club-profiles',
    "page === 'event') return 'events'",
];

$failures = [];
foreach ($forbidden as $needle) {
    if (str_contains($client, $needle)) {
        $failures[] = "forbidden literal present: {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "birkly_client_coupling_guard_test: OK\n";
