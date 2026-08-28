<?php
/**
 * P3-M4: Role-scoped AI tool definitions derived from core/ai_permissions.php.
 */

require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/ai_permissions.php';
require_once __DIR__ . '/connections/mcp_tools.php';
require_once __DIR__ . '/plugins/mcp_shelf.php';
require_once __DIR__ . '/security_policy.php';

/** @return array<string, array{name: string, description: string, category: string, inputSchema: array}> */
function birkly_ai_core_tool_definitions(): array
{
    return [
        'read_entry' => ['name' => 'read_entry', 'description' => 'Read a specific entry by collection slug and entry slug or ID', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'id' => ['type' => 'string']], 'required' => ['collection']]],
        'list_entries' => ['name' => 'list_entries', 'description' => 'List entries in a collection. Use the slug from list_collections (or the display name — both work). Returns id, slug, title, and status summaries.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Collection slug or name from list_collections'], 'limit' => ['type' => 'number']], 'required' => ['collection']]],
        'read_collection' => ['name' => 'read_collection', 'description' => 'Get details and schema of a collection', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string']], 'required' => ['slug']]],
        'list_collections' => ['name' => 'list_collections', 'description' => 'List all content collections (slug, name, entry_method, field summary)', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'list_collection_groups' => ['name' => 'list_collection_groups', 'description' => 'List admin collection groups and which collection slugs belong to each (for sidebar organization)', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'get_collection' => ['name' => 'get_collection', 'description' => 'Get details of a specific collection (MCP alias)', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string']], 'required' => ['slug']]],
        'get_system_context' => ['name' => 'get_system_context', 'description' => 'Get curated system context including deployed_at (verify whether a code change is live)', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_birkly_docs' => ['name' => 'read_birkly_docs', 'description' => 'Read a Birkly playbook or doc topic. Start with playbooks-index, mcp-capabilities, or ai-quality-guide. Topics: ai-quality-guide, playbooks-index, mcp-capabilities, content-model, fieldtypes, site-building, web-project-patterns, public-forms, sub-collections, collection-groups, templating, commerce, commerce-store-kit, automation, events, user-management, agent-guide, mcp-setup, ai-connections, plugins-overview, media, cms-pages, content-editor, settings-roles, admin, ai, mcp, plugins; active plugin slugs when installed. Active plugins also register commerce_* / automation_* MCP tools — see mcp-capabilities. Playbooks auto-load for many intents — call only when not pre-loaded.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['topic' => ['type' => 'string', 'description' => 'Topic slug — see playbooks-index for full list']], 'required' => ['topic']]],
        'list_media' => ['name' => 'list_media', 'description' => 'List media library files', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_media' => ['name' => 'read_media', 'description' => 'Read metadata for a media file', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],
        'list_users' => ['name' => 'list_users', 'description' => 'List human users', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_user' => ['name' => 'read_user', 'description' => 'Read a user by ID or email', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'email' => ['type' => 'string']]]],
        'read_settings' => ['name' => 'read_settings', 'description' => 'Read system settings', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'list_roles' => ['name' => 'list_roles', 'description' => 'List defined roles', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_role' => ['name' => 'read_role', 'description' => 'Read a role by ID or name', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'name' => ['type' => 'string']]]],
        'list_pages' => ['name' => 'list_pages', 'description' => 'List site pages', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'read_page' => ['name' => 'read_page', 'description' => 'Read a page by slug or ID', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string'], 'id' => ['type' => 'string']]]],
        'list_activity' => ['name' => 'list_activity', 'description' => 'List recent activity log entries and P61 plugin activity bus events (bell/nav attention). Optional plugin filter (e.g. commerce).', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'number'], 'plugin' => ['type' => 'string', 'description' => 'Optional plugin id filter for plugin_activity (e.g. commerce)']]]],
        'list_plugins' => ['name' => 'list_plugins', 'description' => 'List installed plugins', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'list_sub_entries' => ['name' => 'list_sub_entries', 'description' => 'List sub-entries for a parent entry. Requires parent collection slug, parent entry ID, and sub-collection slug.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Parent collection slug'], 'entry_id' => ['type' => 'string', 'description' => 'Parent entry ID'], 'sub_collection' => ['type' => 'string', 'description' => 'Sub-collection slug (e.g. comments)'], 'limit' => ['type' => 'number']], 'required' => ['collection', 'entry_id', 'sub_collection']]],
        'read_sub_entry' => ['name' => 'read_sub_entry', 'description' => 'Read a single sub-entry by ID', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Parent collection slug'], 'entry_id' => ['type' => 'string', 'description' => 'Parent entry ID'], 'sub_collection' => ['type' => 'string', 'description' => 'Sub-collection slug'], 'sub_entry_id' => ['type' => 'string', 'description' => 'Sub-entry ID']], 'required' => ['collection', 'entry_id', 'sub_collection', 'sub_entry_id']]],
        'create_sub_entry' => ['name' => 'create_sub_entry', 'description' => 'Create a new sub-entry under a parent entry (approval-gated)', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Parent collection slug'], 'entry_id' => ['type' => 'string', 'description' => 'Parent entry ID'], 'sub_collection' => ['type' => 'string', 'description' => 'Sub-collection slug'], 'data' => ['type' => 'object', 'description' => 'Sub-entry field values']], 'required' => ['collection', 'entry_id', 'sub_collection']]],
        'update_sub_entry' => ['name' => 'update_sub_entry', 'description' => 'Update an existing sub-entry (approval-gated)', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'entry_id' => ['type' => 'string'], 'sub_collection' => ['type' => 'string'], 'sub_entry_id' => ['type' => 'string'], 'data' => ['type' => 'object']], 'required' => ['collection', 'entry_id', 'sub_collection', 'sub_entry_id']]],
        'delete_sub_entry' => ['name' => 'delete_sub_entry', 'description' => 'Delete a sub-entry (approval-gated)', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'entry_id' => ['type' => 'string'], 'sub_collection' => ['type' => 'string'], 'sub_entry_id' => ['type' => 'string']], 'required' => ['collection', 'entry_id', 'sub_collection', 'sub_entry_id']]],
        'create_collection' => ['name' => 'create_collection', 'description' => 'Create a content collection. Optional: entry_method public_api for contact forms; sub_collections for nested content (comments); multilanguage { enabled, languages } for per-language entry blocks. API key auto-generated when public_api is set.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => array_merge(['name' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'fields' => ['type' => 'array', 'description' => 'Field schema', 'items' => birkly_ai_tool_schema_field_definition_item()]], birkly_ai_tool_schema_collection_config_properties()), 'required' => ['name', 'slug']]],
        'update_collection' => ['name' => 'update_collection', 'description' => 'Update collection metadata, field schema, entry_method (public_api for forms), api_settings, allow_public_read, multilanguage, or sub_collections. REQUIRED: slug. WARNING: passing fields REPLACES the entire schema (not a merge) — include every field you want to keep. multilanguage shape: { enabled: bool, languages: ["en","de",...] } — same as admin collection editor. api_settings can be updated alone (e.g. rate_limit) without resending entry_method. Public read access uses allow_public_read on the collection (not api_settings). Unknown keys are rejected with an error.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => array_merge(['slug' => ['type' => 'string', 'description' => 'Collection slug'], 'collection' => ['type' => 'string', 'description' => 'Alias for slug'], 'name' => ['type' => 'string'], 'fields' => ['type' => 'array', 'description' => 'Field schema (replaces entire schema)', 'items' => birkly_ai_tool_schema_field_definition_item()]], birkly_ai_tool_schema_collection_config_properties()), 'required' => ['slug']]],
        'save_collection_groups' => ['name' => 'save_collection_groups', 'description' => 'Save collection sidebar groups (create groups, assign collections, reorder). Replaces entire groups tree.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['groups' => ['type' => 'array', 'description' => 'Group tree: [{id, name, collections: [slug...], icon?, children?: [...]}]', 'items' => ['type' => 'object', 'additionalProperties' => true]]], 'required' => ['groups']]],
        'delete_collection' => ['name' => 'delete_collection', 'description' => 'Delete a collection. REQUIRED: slug.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string'], 'collection' => ['type' => 'string']], 'required' => ['slug']]],
        'create_entry' => ['name' => 'create_entry', 'description' => 'Create an entry. REQUIRED: collection (slug string). Slug is auto-generated from title when omitted — use {slug} in post links (post.html?collection=blog&slug={slug}). Set status to "published" for live site content.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string', 'description' => 'Collection slug'], 'title' => ['type' => 'string'], 'slug' => ['type' => 'string', 'description' => 'Optional URL slug; auto-generated from title if omitted'], 'status' => ['type' => 'string', 'description' => 'published or draft — use published for live site content'], 'data' => ['type' => 'object']], 'required' => ['collection']]],
        'update_entry' => ['name' => 'update_entry', 'description' => 'Update an existing entry. REQUIRED: collection and id (or slug) at the top level. Field values may be at the top level OR inside data — collection and id are always read from the top level when present. On multilanguage collections, pass language blocks as top-level keys matching configured languages (e.g. de: { title, body }) — only fields in the block are merged; other languages are preserved.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'id' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'data' => ['type' => 'object']], 'required' => ['collection', 'id']]],
        'delete_entry' => ['name' => 'delete_entry', 'description' => 'Delete an entry', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'id' => ['type' => 'string']], 'required' => ['collection', 'id']]],
        'upload_media' => ['name' => 'upload_media', 'description' => 'Upload a media file', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'delete_media' => ['name' => 'delete_media', 'description' => 'Delete a media file', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],
        'update_media' => ['name' => 'update_media', 'description' => 'Update media metadata', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'data' => ['type' => 'object']], 'required' => ['id']]],
        'create_media_folder' => ['name' => 'create_media_folder', 'description' => 'Create a media folder', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]],
        'delete_media_folder' => ['name' => 'delete_media_folder', 'description' => 'Delete a media folder', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]],
        'move_media' => ['name' => 'move_media', 'description' => 'Move a media file', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'folder' => ['type' => 'string']], 'required' => ['id']]],
        'update_settings' => ['name' => 'update_settings', 'description' => 'Update system settings', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['category' => ['type' => 'string'], 'data' => ['type' => 'object']]]],
        'create_page' => ['name' => 'create_page', 'description' => 'Create a site page with Birkly templating in content. REQUIRED: title and slug.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'content' => ['type' => 'string']], 'required' => ['title', 'slug']]],
        'update_page' => ['name' => 'update_page', 'description' => 'Update an existing page', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string'], 'data' => ['type' => 'object']]]],
        'update_user' => ['name' => 'update_user', 'description' => 'Update a user account', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'data' => ['type' => 'object']]]],
        'delete_user' => ['name' => 'delete_user', 'description' => 'Delete a user account', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]],
        'create_role' => ['name' => 'create_role', 'description' => 'Create a new role', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'update_role' => ['name' => 'update_role', 'description' => 'Update a role', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'delete_role' => ['name' => 'delete_role', 'description' => 'Delete a role', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]],
        'delete_page' => ['name' => 'delete_page', 'description' => 'Delete a page', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string']]]],
        'activate_plugin' => ['name' => 'activate_plugin', 'description' => 'Activate a plugin', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]],
        'deactivate_plugin' => ['name' => 'deactivate_plugin', 'description' => 'Deactivate a plugin', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]],
        'list_project_files' => ['name' => 'list_project_files', 'description' => 'List files and folders in the public site (project/). Optional path relative to project root.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string', 'description' => 'Subdirectory under project/, e.g. css or pages']]]],
        'read_project_file' => ['name' => 'read_project_file', 'description' => 'Read a file from the public site (project/). Allowed: html, css, js, json, md, svg, webp, txt', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string', 'description' => 'Relative path, e.g. index.html or css/site.css']], 'required' => ['path']]],
        'get_project_info' => ['name' => 'get_project_info', 'description' => 'Get public site paths, collections, and templating cheat sheet for building CMS-integrated HTML', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'get_site_delivery_status' => ['name' => 'get_site_delivery_status', 'description' => 'Get site delivery health probes, hosting config summary, and domain routing roles', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'run_site_delivery_probe' => ['name' => 'run_site_delivery_probe', 'description' => 'Run delivery health probes (optional deep HTTP checks when deep=true)', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['deep' => ['type' => 'boolean', 'description' => 'Run deeper checks when supported']]]],
        'write_project_file' => ['name' => 'write_project_file', 'description' => 'Create or overwrite a file in project/ without warning. Paths: index.html, css/style.css, js/site.js (no project/ prefix). For large HTML pages, write in smaller steps — some AI clients block very large tool payloads before they reach Birkly.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string']], 'required' => ['path', 'content']]],
        'create_project_file' => ['name' => 'create_project_file', 'description' => 'Create a new file in project/ (fails if path exists). PHP and dotfiles are blocked.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string']], 'required' => ['path']]],
        'delete_project_file' => ['name' => 'delete_project_file', 'description' => 'Delete a file from project/ (admin role by default). Directories are not deleted.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']]],
        'get_agent_guide_context' => ['name' => 'get_agent_guide_context', 'description' => 'Get published Agent Guide context (view: summary|full|products|claims). Summary lists supported claims with evidence_ids; use get_agent_guide_claim for resolved evidence URLs.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['view' => ['type' => 'string', 'description' => 'summary, full, products, or claims (claims includes evidence[] for supported claims)']]]],
        'list_agent_guide_products' => ['name' => 'list_agent_guide_products', 'description' => 'List published products and services from Agent Guide', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'get_agent_guide_claim' => ['name' => 'get_agent_guide_claim', 'description' => 'Get a supported claim with resolved evidence[] (URLs, verification status) and sources — same as public API claim graph', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],
        'get_agent_guide_product' => ['name' => 'get_agent_guide_product', 'description' => 'Get a published product with resolved source links, linked claims, and evidence graph', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],
        'get_brand_context' => ['name' => 'get_brand_context', 'description' => 'Legacy alias for get_agent_guide_context', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['view' => ['type' => 'string', 'description' => 'summary, full, products, or claims']]]],
        'list_brand_products' => ['name' => 'list_brand_products', 'description' => 'Legacy alias for list_agent_guide_products', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'get_brand_claim' => ['name' => 'get_brand_claim', 'description' => 'Legacy alias for get_agent_guide_claim', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],
        'get_brand_product' => ['name' => 'get_brand_product', 'description' => 'Legacy alias for get_agent_guide_product', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],

        // Phase 65 Connections tools (approval-gated; no secret echo)
        'connections_health' => ['name' => 'connections_health', 'description' => 'Summarize Connections health: counts by type/provider, default connections, mail queue depth, and recent failures. Does not expose secrets.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'description' => 'Optional filter: email, http_credential, or channel']]]],
        'connections_configure' => ['name' => 'connections_configure', 'description' => 'Create, update, or delete a Connection. Secrets are stored encrypted and are never returned.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['operation' => ['type' => 'string', 'enum' => ['create', 'update', 'delete']], 'connection' => ['type' => 'object', 'description' => 'Connection fields: type, provider, alias, name, config, secrets, from_email, etc.']], 'required' => ['operation', 'connection']]],
        'connections_test' => ['name' => 'connections_test', 'description' => 'Test a Connection by alias. For email provide test_email; for Twilio SMS provide to_number. Does not echo secrets.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['alias' => ['type' => 'string'], 'test_email' => ['type' => 'string'], 'to_number' => ['type' => 'string']], 'required' => ['alias']]],

        // CMS admin parity (full core admin API via dispatch)
        'cms_list_admin_actions' => ['name' => 'cms_list_admin_actions', 'description' => 'List all core CMS admin API actions grouped by area (entries, agent_guide, connections, marketplace, git, webhooks, etc.). Use cms_admin_action to dispatch one.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'cms_admin_action' => ['name' => 'cms_admin_action', 'description' => 'Dispatch any core CMS admin action by name (domain.subaction, e.g. entries.bulk, agent_guide.publish, marketplace.install). Prefer named cms_* tools for common tasks.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['action' => ['type' => 'string', 'description' => 'Action id from cms_list_admin_actions'], 'payload' => ['type' => 'object', 'description' => 'Action input', 'additionalProperties' => true]], 'required' => ['action']]],
        'cms_bulk_entry_action' => ['name' => 'cms_bulk_entry_action', 'description' => 'Bulk entry action: publish, unpublish, delete (trash), or restore. Shortcut for entries.bulk.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['collection' => ['type' => 'string'], 'action' => ['type' => 'string', 'description' => 'publish, unpublish, delete, or restore'], 'entry_ids' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['collection', 'action', 'entry_ids']]],
        'cms_list_connections' => ['name' => 'cms_list_connections', 'description' => 'List Connections (read-only, secrets masked). Shortcut for connections.list.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => []]],
        'cms_read_connection' => ['name' => 'cms_read_connection', 'description' => 'Get one Connection by id or alias. Shortcut for connections.get.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'alias' => ['type' => 'string']]]],
        'cms_create_user' => ['name' => 'cms_create_user', 'description' => 'Create a human user account. Shortcut for users.create.', 'category' => 'write', 'inputSchema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string'], 'password' => ['type' => 'string'], 'name' => ['type' => 'string'], 'role' => ['type' => 'string']], 'required' => ['email', 'password']]],
        'cms_admin_search' => ['name' => 'cms_admin_search', 'description' => 'Admin global search across collections, entries, media, users. Shortcut for search.admin_search.', 'category' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string'], 'limit' => ['type' => 'number']], 'required' => ['query']]],
    ];
}

/** @return array<string, array{name: string, description: string, category: string, inputSchema: array}> */
function birkly_ai_tool_definitions(): array
{
    return array_merge(birkly_ai_core_tool_definitions(), birkly_plugin_mcp_tool_definitions());
}

/** @return array<string, array> */
function birkly_ai_tools_for_user(array $aiUser): array
{
    if (birkly_ai_tools_site_restricted()) {
        return [];
    }
    $definitions = birkly_ai_tool_definitions();
    $allowed = [];
    $role = (string) ($aiUser['role'] ?? '');
    foreach ($definitions as $actionType => $def) {
        if (!birkly_ai_user_can_action($aiUser, $actionType)) {
            continue;
        }
        $pluginId = birkly_ai_tool_plugin_id($actionType);
        if ($pluginId !== '' && $role !== '' && !birkly_role_plugin_allowed($role, $pluginId)) {
            continue;
        }
        $allowed[$actionType] = $def;
    }
    return $allowed;
}

/**
 * Infer owning plugin id from MCP tool name (commerce_*, automation_*, etc.).
 */
function birkly_ai_tool_plugin_id(string $actionType): string
{
    if (preg_match('/^(commerce|automation|events|user_management|payments)_/i', $actionType, $m)) {
        return strtolower($m[1]);
    }
    return '';
}

function birkly_ai_is_read_action(string $actionType): bool
{
    $definitions = birkly_ai_tool_definitions();
    return isset($definitions[$actionType]) && ($definitions[$actionType]['category'] ?? '') === 'read';
}

function birkly_ai_normalize_tool_input_schema(array $schema): array
{
    if (($schema['type'] ?? '') === 'array' && !isset($schema['items'])) {
        $schema['items'] = birkly_ai_tool_schema_field_definition_item();
    }

    if (isset($schema['properties']) && is_array($schema['properties']) && $schema['properties'] === []) {
        $schema['properties'] = (object) [];
    } elseif (isset($schema['properties']) && is_array($schema['properties'])) {
        foreach ($schema['properties'] as $key => $prop) {
            if (is_array($prop)) {
                $schema['properties'][$key] = birkly_ai_normalize_tool_input_schema($prop);
            }
        }
    }

    if (isset($schema['items']) && is_array($schema['items'])) {
        $schema['items'] = birkly_ai_normalize_tool_input_schema($schema['items']);
    }

    return $schema;
}

/** JSON Schema fragment for one collection field definition in create/update_collection. */
function birkly_ai_tool_schema_field_definition_item(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Field machine name'],
            'type' => ['type' => 'string', 'description' => 'Field type, e.g. text, textarea, image, number'],
            'label' => ['type' => 'string', 'description' => 'Human-readable label'],
            'required' => ['type' => 'boolean', 'description' => 'Whether the field is required'],
        ],
        'required' => ['name', 'type'],
        'additionalProperties' => true,
    ];
}

/** JSON Schema for sub-collection definition on create/update_collection. */
function birkly_ai_tool_schema_sub_collection_item(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'entry_method' => ['type' => 'string', 'description' => 'admin_only or public_api (for visitor comments/reviews)'],
            'auto_publish' => ['type' => 'boolean'],
            'fields' => ['type' => 'array', 'items' => birkly_ai_tool_schema_field_definition_item()],
        ],
        'required' => ['slug', 'name'],
        'additionalProperties' => true,
    ];
}

/** Extra collection properties (entry_method, sub_collections, public API). */
function birkly_ai_tool_schema_collection_config_properties(): array
{
    require_once __DIR__ . '/ai_collection_config.php';
    $apiSettingsKeys = implode(', ', birkly_collection_api_settings_keys());

    return [
        'description' => ['type' => 'string'],
        'entry_method' => ['type' => 'string', 'description' => 'admin_only (default) or public_api for contact forms and public submission'],
        'allow_public_read' => ['type' => 'boolean', 'description' => 'When true, collection entries are readable via the public API without authentication. Required for {for each entry in "slug"} loops on the live site. Separate from entry_method (writes). Not stored in api_settings.'],
        'api_settings' => [
            'type' => 'object',
            'description' => 'When entry_method is public_api: ' . $apiSettingsKeys,
            'additionalProperties' => true,
        ],
        'sub_collections' => [
            'type' => 'array',
            'description' => 'Nested sub-collections (e.g. comments on posts)',
            'items' => birkly_ai_tool_schema_sub_collection_item(),
        ],
        'multilanguage' => [
            'type' => 'object',
            'description' => 'Per-language entry blocks. Same shape as admin collection editor: { enabled: boolean, languages: ["en","de",...] }',
            'properties' => [
                'enabled' => ['type' => 'boolean'],
                'languages' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ],
    ];
}

function birkly_ai_tools_openai_format(array $aiUser): array
{
    $tools = [];
    foreach (birkly_ai_tools_for_user($aiUser) as $def) {
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => $def['name'],
                'description' => $def['description'],
                'parameters' => birkly_ai_normalize_tool_input_schema($def['inputSchema']),
            ],
        ];
    }
    return $tools;
}

function birkly_ai_tools_mcp_format(array $aiUser): array
{
    $tools = [];
    foreach (birkly_ai_tools_for_user($aiUser) as $def) {
        $tools[] = [
            'name' => $def['name'],
            'description' => $def['description'],
            'inputSchema' => birkly_ai_normalize_tool_input_schema($def['inputSchema']),
        ];
    }
    return $tools;
}

function birkly_ai_tools_prompt_section(array $aiUser): string
{
    $lines = ["AVAILABLE TOOLS (use JSON actions or native tool calls):"];
    foreach (birkly_ai_tools_for_user($aiUser) as $def) {
        $lines[] = '- ' . $def['name'] . ' [' . $def['category'] . ']: ' . $def['description'];
    }
    $lines[] = '';
    $lines[] = birkly_ai_action_workflow_prompt_section();
    return implode("\n", $lines) . "\n";
}

/**
 * Step-by-step guidance for multi-action CMS workflows (site builds, schema updates, etc.).
 */
function birkly_ai_action_workflow_prompt_section(): string
{
    return implode("\n", [
        'ACTION PAYLOAD RULES (critical — wrong shape causes "Collection slug is required" errors):',
        '1. Put slug/collection/fields on the SAME LEVEL as "type", not only inside "data". Both work:',
        '   {"type":"update_collection","slug":"gallery","fields":[...]}',
        '   {"type":"update_collection","data":{"slug":"gallery","fields":[...]}}',
        '2. update_collection: ALWAYS include "slug". Fields in "fields". Set entry_method: "public_api" for contact/inquiry collections and public sub-collections (comments). Set allow_public_read: true when the public site must list/read entries without auth ({for each entry in ...} loops). sub_collections[] for nested schemas.',
        '3. create_entry: ALWAYS include "collection" as a slug string. Field values (title, text, image, …) at top level.',
        '4. save_collection_groups: pass full groups tree from list_collection_groups when reorganizing admin sidebar.',
        '5. create_page: include "title", "slug", and "content" (HTML with {for each entry in \'slug\'} loops).',
        '6. Do NOT pass a nested collection object instead of a slug string.',
        '7. When user says "proceed" / "yes": execute the planned steps — do not re-ask. Use slugs from AVAILABLE COLLECTIONS below.',
        '8. delete_project_file: use the exact filename. To remove typo duplicates delete ndex.html / bout.html / ontact.html / rtworks.html — never delete index.html, about.html, contact.html, or artworks.html for typo cleanup.',
        '',
        'RECOMMENDED SITE-BUILD ORDER (custom HTML/CSS in project/ — playbooks auto-load; else read_birkly_docs "site-building"):',
        'Step 0 — ai-quality-guide + web-project-patterns (often pre-loaded); get_project_info + list_project_files once (do not repeat)',
        'Step A — define schema (fieldtypes): update_collection / create_collection with fields[]',
        'Step B — create_entry seed data (status published)',
        'Step C — write_project_file: index.html, detail pages, css/style.css; birkly-client.js on every templated page',
        'Step D — public forms: entry_method public_api + read_collection before form HTML (public-forms playbook)',
        'Step E — share public URL from get_project_info',
        '',
        'Use create_page only when the user wants admin-managed CMS pages instead of project/ files.',
        '',
        'EXAMPLE — add fields to two collections:',
        '```json',
        '{"actions":[',
        '  {"type":"update_collection","slug":"gallery","fields":[{"name":"title","type":"text","label":"Title","required":true},{"name":"image","type":"image","label":"Image"}]},',
        '  {"type":"update_collection","slug":"contact","fields":[{"name":"email","type":"text","label":"Email"}]}',
        ']}',
        '```',
    ]);
}

function birkly_ai_tool_calls_to_actions(array $toolCalls): array
{
    $actions = [];
    foreach ($toolCalls as $call) {
        $name = $call['function']['name'] ?? $call['name'] ?? '';
        if ($name === '') {
            continue;
        }
        $rawArgs = $call['function']['arguments'] ?? $call['arguments'] ?? '{}';
        $args = is_string($rawArgs) ? (json_decode($rawArgs, true) ?: []) : (is_array($rawArgs) ? $rawArgs : []);
        if (in_array($name, ['create_entry', 'update_entry', 'create_collection', 'update_collection', 'delete_collection', 'read_collection'], true)
            && isset($args['data']) && is_array($args['data'])) {
            $inner = $args['data'];
            unset($args['data']);
            $args = array_merge($args, $inner);
        }
        $type = $name === 'get_collection' ? 'read_collection' : $name;
        if ($type === 'read_collection' && isset($args['slug']) && !isset($args['collection'])) {
            $args = ['slug' => $args['slug']];
        }
        $actions[] = ['type' => $type, 'data' => birkly_ai_extract_action_payload(['type' => $type] + $args), 'description' => $name];
    }
    return $actions;
}

function birkly_ai_dispatch_tool_call(string $toolName, array $arguments, array $aiUser, string $source = 'mcp'): array
{
    require_once __DIR__ . '/../api/storage.php';
    require_once __DIR__ . '/../api/ai_helpers.php';

    $actionType = $toolName === 'get_collection' ? 'read_collection' : $toolName;
    if ($toolName === 'get_collection' && isset($arguments['slug'])) {
        $arguments = ['slug' => $arguments['slug']];
    }

    $denied = birkly_ai_enforce_action_permission($aiUser, $actionType);
    if ($denied !== null) {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => $denied['message']]]];
    }

    if (birkly_plugin_mcp_tool_is_registered($actionType)) {
        $pluginResult = birkly_plugin_mcp_dispatch($actionType, $arguments, $aiUser);
        if ($pluginResult !== null) {
            return $pluginResult;
        }
    }

    if (in_array($actionType, [
        'cms_list_admin_actions',
        'cms_admin_action',
        'cms_bulk_entry_action',
        'cms_list_connections',
        'cms_read_connection',
        'cms_create_user',
        'cms_admin_search',
    ], true)) {
        require_once __DIR__ . '/cms_mcp_admin.php';
        $result = match ($actionType) {
            'cms_list_admin_actions' => ['status' => 'ok', 'data' => ['catalog' => cms_mcp_admin_action_catalog()]],
            'cms_admin_action' => cms_mcp_dispatch_admin(
                trim((string) ($arguments['action'] ?? '')),
                is_array($arguments['payload'] ?? null) ? $arguments['payload'] : [],
                $aiUser
            ),
            'cms_bulk_entry_action' => cms_mcp_dispatch_admin('entries.bulk', [
                'collection' => $arguments['collection'] ?? '',
                'action' => $arguments['action'] ?? '',
                'entry_ids' => $arguments['entry_ids'] ?? [],
            ], $aiUser),
            'cms_list_connections' => cms_mcp_dispatch_admin('connections.list', [], $aiUser),
            'cms_read_connection' => cms_mcp_dispatch_admin('connections.get', [
                'id' => $arguments['id'] ?? $arguments['alias'] ?? '',
            ], $aiUser),
            'cms_create_user' => cms_mcp_dispatch_admin('users.create', $arguments, $aiUser),
            'cms_admin_search' => cms_mcp_dispatch_admin('search.admin_search', $arguments, $aiUser),
            default => ['status' => 'error', 'message' => 'Unknown CMS tool'],
        };
        return cms_mcp_admin_result_to_mcp_content($result);
    }

    if ($actionType === 'get_system_context') {
        require_once __DIR__ . '/build_info.php';
        $context = generateSystemContext($aiUser);
        $mcpBuild = birkly_mcp_system_build_info();
        unset($context['system_info']['git_commit']);
        $context['system_info']['deployed_at'] = $mcpBuild['deployed_at'];
        $context['system_info']['mcp_server_version'] = BIRKLY_MCP_SERVER_VERSION;
        $context['system_info']['mcp_tool_count'] = count(birkly_ai_tools_for_user($aiUser));
        return ['content' => [['type' => 'text', 'text' => json_encode($context, JSON_PRETTY_PRINT)]]];
    }

    if ($actionType === 'read_birkly_docs') {
        $topic = readBirklyDocsTopic((string) ($arguments['topic'] ?? ''));
        if ($topic === null) {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Unknown or missing docs topic. Use: templating, admin, plugins, ai, mcp, or an active plugin slug (e.g. commerce)']]];
        }
        return ['content' => [['type' => 'text', 'text' => "# {$topic['title']}\n\n{$topic['text']}"]]];
    }

    if (in_array($actionType, ['get_agent_guide_context', 'get_brand_context', 'list_agent_guide_products', 'list_brand_products', 'get_agent_guide_claim', 'get_brand_claim', 'get_agent_guide_product', 'get_brand_product'], true)) {
        require_once __DIR__ . '/ai_presence_pro.php';
        require_once __DIR__ . '/ai_presence_links.php';
        if (in_array($actionType, ['get_agent_guide_context', 'get_brand_context'], true)) {
            $view = (string) ($arguments['view'] ?? 'summary');
            $data = birkly_ai_presence_public_agent_guide_for_tools($view);
            if ($data === null) {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => birkly_ai_presence_not_published_reason()]]];
            }
            return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
        }
        if (in_array($actionType, ['list_agent_guide_products', 'list_brand_products'], true)) {
            $data = birkly_ai_presence_public_agent_guide_for_tools('products');
            if ($data === null) {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => birkly_ai_presence_not_published_reason()]]];
            }
            return ['content' => [['type' => 'text', 'text' => json_encode($data['products_services'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
        }
        if (in_array($actionType, ['get_agent_guide_product', 'get_brand_product'], true)) {
            $id = (string) ($arguments['id'] ?? '');
            $full = birkly_ai_presence_public_agent_guide_for_tools('full');
            if ($full === null || $id === '') {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Product not found']]];
            }
            $graph = birkly_ai_presence_public_product_graph($full, $id);
            if ($graph === null) {
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Product not found']]];
            }
            return ['content' => [['type' => 'text', 'text' => json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
        }
        $id = (string) ($arguments['id'] ?? '');
        $full = birkly_ai_presence_public_agent_guide_for_tools('full');
        if ($full === null || $id === '') {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Claim not found']]];
        }
        require_once __DIR__ . '/ai_presence_links.php';
        $graph = birkly_ai_presence_public_claim_graph($full, $id);
        if ($graph === null) {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Claim not found']]];
        }
        return ['content' => [['type' => 'text', 'text' => json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
    }

    if (birkly_ai_is_read_action($actionType)) {
        require_once __DIR__ . '/../api/ai_read_actions.php';
        require_once __DIR__ . '/ai_mcp_sanitize.php';
        if ($source === 'mcp') {
            birkly_ai_mcp_cleanup_stray_settings();
        }
        if ($source === 'mcp' && in_array($actionType, ['list_entries'], true)) {
            $arguments['summary'] = true;
        }
        $arguments = birkly_ai_normalize_action_data($actionType, is_array($arguments) ? $arguments : []);
        $result = executeReadAction(['type' => $actionType, 'data' => $arguments], $aiUser);
        if (!($result['success'] ?? false)) {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => $result['message'] ?? 'Action failed']]];
        }
        $payload = $result['data'] ?? $result;
        if ($source === 'mcp' && $actionType === 'read_settings' && is_array($payload)) {
            $payload = birkly_ai_mcp_redact_settings_response($payload);
        }
        if ($source === 'mcp' && $actionType === 'read_collection' && is_array($payload)) {
            $payload = birkly_ai_mcp_enrich_read_collection_payload($payload);
        }
        if ($source === 'mcp' && $actionType === 'list_collections' && is_array($payload)) {
            $payload = birkly_ai_mcp_enrich_list_collections_payload($payload);
        }
        return ['content' => [['type' => 'text', 'text' => json_encode($payload, JSON_PRETTY_PRINT)]]];
    }

    // Inbound MCP: the client app (Claude, ChatGPT, Cursor) handles approval UX.
    // Birkly only enforces role permissions. Outbound admin chat keeps require_approval.
    $requireApproval = false;
    if ($source !== 'mcp') {
        $requireApproval = $aiUser['require_approval'] ?? true;
        if (is_string($requireApproval)) {
            $requireApproval = in_array(strtolower($requireApproval), ['1', 'true', 'yes', 'on'], true);
        }
    }

    if ($requireApproval) {
        $approvalRoot = dirname(__DIR__) . '/data/ai_approvals';
        $approvalStorage = new Storage($approvalRoot);
        $approvalId = 'approval_' . bin2hex(random_bytes(8));
        $approvalStorage->write($approvalId . '.json', [
            'id' => $approvalId,
            'ai_user_id' => $aiUser['id'],
            'action_type' => $actionType,
            'action_data' => $arguments,
            'description' => ucfirst(str_replace('_', ' ', $actionType)),
            'status' => 'pending',
            'created_at' => date('c'),
            'source' => $source,
            'authorized_by' => (string) ($aiUser['authorized_by'] ?? ''),
            'agent_type' => (string) ($aiUser['agent_type'] ?? ''),
        ]);
        $message = 'Write action queued in Birkly (approval ID: ' . $approvalId . '). '
            . 'Open your Birkly admin panel → floating AI button → Approvals tab to approve. '
            . 'This is separate from Claude\'s "always allow" tool setting. '
            . 'To auto-run writes from Claude, disable "Ask before changes" on the personal connector in Settings → AI.';
        return ['content' => [['type' => 'text', 'text' => $message]]];
    }

    // Phase 65 Connections tools (handled directly; approval gate above already queued if required).
    if (in_array($actionType, ['connections_health', 'connections_configure', 'connections_test'], true)) {
        $result = match ($actionType) {
            'connections_health' => birkly_connections_mcp_health($arguments),
            'connections_configure' => birkly_connections_mcp_configure($arguments),
            'connections_test' => birkly_connections_mcp_test($arguments),
        };
        if (($result['status'] ?? '') !== 'ok') {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
        }
        return birkly_connections_mcp_result_to_content($result);
    }

    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once __DIR__ . '/../api/ai_approvals.php';
    require_once __DIR__ . '/ai_mcp_sanitize.php';

    $arguments = is_array($arguments) ? $arguments : [];
    if ($source === 'mcp' && $actionType === 'update_settings') {
        birkly_ai_mcp_cleanup_stray_settings();
        $settingsError = birkly_ai_validate_mcp_update_settings($arguments);
        if ($settingsError !== null) {
            unset($GLOBALS['__HARU_INTERNAL_CALL']);
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => $settingsError]]];
        }
    }
    if ($source === 'mcp' && $actionType === 'create_entry' && !isset($arguments['status'])) {
        $arguments['status'] = 'published';
    }
    $arguments = birkly_ai_normalize_action_data($actionType, $arguments);

    $result = executeAiAction([
        'action_type' => $actionType,
        'action_data' => $arguments,
        'ai_user_id' => $aiUser['id'],
        'ai_user' => $aiUser,
    ]);
    unset($GLOBALS['__HARU_INTERNAL_CALL']);
    if (!($result['success'] ?? false)) {
        return ['isError' => true, 'content' => [['type' => 'text', 'text' => $result['message'] ?? 'Execution failed']]];
    }

    if ($source === 'mcp' && in_array($actionType, ['update_collection', 'create_collection'], true)) {
        $result = birkly_ai_mcp_enrich_write_collection_result($result);
    }

    $summary = birkly_ai_mcp_tool_success_summary($actionType, $arguments, $result);
    if ($summary !== '') {
        return ['content' => [['type' => 'text', 'text' => $summary . "\n\n" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
    }

    return ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
}

/**
 * Plain-language confirmation for MCP clients (reduces false "done" claims when JSON is ignored).
 *
 * @param array<string,mixed> $arguments
 * @param array<string,mixed> $result
 */
function birkly_ai_mcp_tool_success_summary(string $actionType, array $arguments, array $result): string
{
    $data = is_array($result['data'] ?? null) ? $result['data'] : [];

    if (in_array($actionType, ['write_project_file', 'create_project_file'], true)) {
        $path = (string) ($data['path'] ?? $arguments['path'] ?? '');
        return $path !== '' ? 'SUCCESS: wrote project/' . $path : '';
    }
    if ($actionType === 'delete_project_file') {
        $path = (string) ($data['path'] ?? $arguments['path'] ?? '');
        return $path !== '' ? 'SUCCESS: deleted project/' . $path : '';
    }
    if ($actionType === 'create_collection') {
        $slug = (string) ($data['slug'] ?? $arguments['slug'] ?? '');
        return $slug !== '' ? 'SUCCESS: created collection "' . $slug . '"' : '';
    }
    if ($actionType === 'create_entry') {
        $collection = (string) ($arguments['collection'] ?? '');
        $entryId = (string) ($data['entry_id'] ?? '');
        $entrySlug = (string) ($data['entry']['slug'] ?? $arguments['slug'] ?? '');
        $status = (string) ($data['entry']['status'] ?? $arguments['status'] ?? '');
        if ($collection === '' || $entryId === '') {
            return '';
        }
        $slugPart = $entrySlug !== '' ? ', slug: ' . $entrySlug : '';
        return 'SUCCESS: created entry ' . $entryId . ' in "' . $collection . '"' . $slugPart . ' (status: ' . ($status !== '' ? $status : 'unknown') . ')';
    }

    return '';
}

function birkly_ai_mcp_scheme(): string
{
    $scheme = 'http';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        || (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https')
    ) {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        if ($proto === 'https' || $proto === 'http') {
            $scheme = $proto;
        }
    }
    return $scheme;
}

function birkly_ai_mcp_server_url(): string
{
    require_once __DIR__ . '/birkly_paths.php';
    require_once __DIR__ . '/mcp_protocol.php';
    return birkly_ai_mcp_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . birkly_url(birkly_mcp_connector_path());
}

function birkly_ai_mcp_legacy_server_url(): string
{
    require_once __DIR__ . '/birkly_paths.php';
    require_once __DIR__ . '/mcp_protocol.php';
    return birkly_ai_mcp_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . birkly_url(birkly_mcp_legacy_path());
}
