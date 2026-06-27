<?php
/**
 * CSRF session scope — admin/onboarding must share PHPSESSID, not site session.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/api/session.php';

test_reset_failures();
echo "CSRF session scope tests\n\n";

test_assert(
    'logged-in admin uses cms scope',
    birkly_resolve_csrf_session_scope(['birkly_cms_sid' => 'abc123']) === 'cms'
);

test_assert(
    'legacy PHPSESSID still uses cms scope',
    birkly_resolve_csrf_session_scope(['PHPSESSID' => 'abc123']) === 'cms'
);

test_assert(
    'admin referer uses cms scope without cookies',
    birkly_resolve_csrf_session_scope([], 'http://localhost:8080/admin/index.php?page=onboarding') === 'cms'
);

test_assert(
    'site cookie alone uses site scope',
    birkly_resolve_csrf_session_scope(['birkly_site_sid' => 'site123']) === 'site'
);

test_assert(
    'user plugin admin cookie uses admin scope',
    birkly_resolve_csrf_session_scope(['birkly_admin_sid' => 'admin123']) === 'admin'
);

test_assert(
    'api request uri uses cms scope over site cookie',
    birkly_resolve_csrf_session_scope(
        ['birkly_site_sid' => 'site123'],
        '',
        '/birkly-test/Birkly-0.1/api/csrf.php'
    ) === 'cms'
);

test_assert(
    'admin request uri uses cms scope without referer',
    birkly_resolve_csrf_session_scope(
        ['birkly_site_sid' => 'site123'],
        '',
        '/birkly-test/Birkly-0.1/admin/login.php'
    ) === 'cms'
);

exit(test_finish('csrf_session_scope_test'));
