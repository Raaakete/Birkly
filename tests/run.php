<?php
/**
 * M5 test harness — single entry point for CI and qa-verifier.
 * Run: php tests/run.php
 * Exit 0 = all pass, 1 = one or more failures.
 */

$suites = [
    'template_parser_test.php',
    'auth_boundary_test.php',
    'select_options_test.php',
    'entry_slug_test.php',
    'ai_slug_normalize_test.php',
    'roles_test.php',
    'collection_schema_test.php',
    'collection_groups_test.php',
    'content_groups_dnd_test.php',
    '../fieldtypes/ContentEditor/tests/html_roundtrip_test.php',
    '../fieldtypes/ContentEditor/tests/html_source_view_test.php',
    '../fieldtypes/ContentEditor/tests/preview_css_test.php',
    '../fieldtypes/ContentEditor/tests/component_registry_test.php',
    '../fieldtypes/ContentEditor/tests/commands_registry_test.php',
    '../fieldtypes/ContentEditor/tests/commands_layout_test.php',
    '../fieldtypes/ContentEditor/tests/caret_insert_test.php',
    '../fieldtypes/ContentEditor/tests/selection_restore_test.php',
    '../fieldtypes/ContentEditor/tests/layout_roundtrip_test.php',
    '../fieldtypes/ContentEditor/tests/table_layout_test.php',
    '../fieldtypes/ContentEditor/tests/grid_layout_test.php',
    '../fieldtypes/ContentEditor/tests/readme_test.php',
    'onboarding_test.php',
    'onboarding_e2e_test.php',
    'csrf_session_scope_test.php',
    'admin_routing_test.php',
    'admin_routing_http_test.php',
    'admin_template_stray_quote_test.php',
    'collection_editor_template_test.php',
    'admin_subdir_content_test.php',
    'admin_entries_direct_test.php',
    'entry_editor_template_test.php',
    'contenteditor_dropdown_test.php',
    'base_path_test.php',
    'birkly_users_test.php',
    'login_csrf_rewrite_test.php',
    'version_update_test.php',
    'ai_chat_connection_test.php',
    'ai_provider_mock_test.php',
    'mcp_endpoints_test.php',
    'mcp_hardening_test.php',
    'mcp_http_test.php',
    'mcp_oauth_test.php',
    'mcp_streamable_test.php',
    'content_resolve_test.php',
    'mcp_approval_review_test.php',
    'mcp_write_dispatch_test.php',
    'mcp_path_agent_test.php',
    'mcp_cimd_test.php',
    'ai_agents_test.php',
    'ai_agent_auth_test.php',
    'template_safe_renderer_test.php',
    'public_collection_meta_test.php',
    'page_snapshot_test.php',
    'ai_permissions_test.php',
    'ai_context_pack_test.php',
    'ai_knowledge_coverage_test.php',
    'ai_tool_registry_test.php',
    'automation_plugin_test.php',
    'automation_node_editor_test.php',
    'payments_shelf_test.php',
    'payments_webhook_test.php',
    'commerce_plugin_test.php',
    'commerce_shelf_test.php',
    'commerce_fieldtype_test.php',
    'commerce_fieldtype_v2_test.php',
    'commerce_fieldtype_profiles_test.php',
    'commerce_index_test.php',
    'commerce_checkout_test.php',
    'commerce_checkout_v2_test.php',
    'commerce_variants_test.php',
    'commerce_discount_test.php',
    'commerce_import_test.php',
    'commerce_fulfillment_test.php',
    'commerce_fulfillment_router_test.php',
    'commerce_mapping_test.php',
    'commerce_bundle_test.php',
    'plugin_docs_shelf_test.php',
    'plugin_admin_shell_test.php',
    'events_plugin_test.php',
    'events_shelf_test.php',
    'dashboard_widgets_test.php',
    'platform_test.php',
    'plugin_dependencies_test.php',
    'user_plugin_permissions_test.php',
    'user_plugin_admin_test.php',
    'feedback_test.php',
    'settings_general_test.php',
    'settings_security_test.php',
    'settings_storage_test.php',
    'settings_save_test.php',
    'settings_page_test.php',
];

$root = dirname(__DIR__);
$failures = 0;

echo "Birkly test harness (M5)\n";
echo str_repeat('=', 40) . "\n\n";

foreach ($suites as $suite) {
    $path = __DIR__ . '/' . $suite;
    if (!is_file($path)) {
        echo "MISSING: $suite\n";
        $failures++;
        continue;
    }

    echo "==> $suite\n";
    passthru('php ' . escapeshellarg($path), $code);
    echo "\n";
    if ($code !== 0) {
        $failures++;
    }
}

echo str_repeat('=', 40) . "\n";
if ($failures === 0) {
    echo "RESULT: PASS (" . count($suites) . " suites)\n";
    exit(0);
}

echo "RESULT: FAIL ($failures suite(s) failed)\n";
exit(1);
