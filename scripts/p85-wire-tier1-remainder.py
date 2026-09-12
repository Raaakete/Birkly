#!/usr/bin/env python3
"""P85: wire tier-1 remainder i18n keys into collection-editor.html and settings.html.

Run after: python3 scripts/p85-chunk-tier1-remainder-i18n.py
"""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
COL = ROOT / "admin/templates/collection-editor.html"
SETTINGS = ROOT / "admin/templates/settings.html"
CHUNK = ROOT / "scripts/p85-chunk-tier1-remainder-i18n.py"


def merge_lang():
    subprocess.run([sys.executable, str(CHUNK)], check=True, cwd=ROOT)


def apply_pairs(path: Path, pairs: list[tuple[str, str]], label: str) -> list[str]:
    text = path.read_text(encoding="utf-8")
    failed: list[str] = []
    for old, new in pairs:
        if old not in text:
            failed.append(f"[{label}] not found: {old[:120]!r}…" if len(old) > 120 else f"[{label}] not found: {old!r}")
            continue
        text = text.replace(old, new)
    path.write_text(text, encoding="utf-8")
    return failed


def main() -> None:
    merge_lang()

    col_pairs: list[tuple[str, str]] = [
        (
            "?? $collection['slug'] ?? 'Collection')",
            "?? $collection['slug'] ?? __('admin.col_editor_breadcrumb_collection_fallback'))",
        ),
        (
            "<p><strong>Parent Collection:</strong>",
            "<p><strong><?= __('admin.col_editor_parent_collection_label') ?></strong>",
        ),
        (
            "<p>This sub-collection belongs to the parent collection above and will be attached to entries in that collection.</p>",
            "<p><?= __('admin.col_editor_sub_belongs_help') ?></p>",
        ),
        (
            '<p class="section-description">Define the fields that sub-entries in this sub-collection will have.</p>',
            '<p class="section-description"><?= __(\'admin.col_editor_section_desc_sub_fields\') ?></p>',
        ),
        (
            '<h2 class="section-title">Collection Fields</h2>',
            '<h2 class="section-title"><?= __(\'admin.col_editor_collection_fields_title\') ?></h2>',
        ),
        (
            '<p class="section-description">Define the fields that entries in this collection will have.</p>',
            '<p class="section-description"><?= __(\'admin.col_editor_section_desc_collection_fields\') ?></p>',
        ),
        (
            '<p class="section-description">Configure nested sub-collections that can be attached to entries in this sub-collection. For example, a "Comments" sub-collection might have "Replies" as a nested sub-collection.</p>',
            '<p class="section-description"><?= __(\'admin.col_editor_section_desc_nested_sub_collections\') ?></p>',
        ),
        (
            '<p class="section-description">Configure sub-collections that can be attached to entries in this collection. For example, a blog post collection might have "Comments" and "Ratings" as sub-collections.</p>',
            '<p class="section-description"><?= __(\'admin.col_editor_section_desc_sub_collections\') ?></p>',
        ),
        (
            '<label>Display Name <span class="required">*</span></label>',
            '<label><?= __(\'admin.col_editor_display_name\') ?> <span class="required">*</span></label>',
        ),
        (
            "<label>API Name</label>",
            "<label><?= __('admin.col_editor_api_name') ?></label>",
        ),
        (
            "<label>Field Type</label>",
            "<label><?= __('admin.col_editor_field_type') ?></label>",
        ),
        (
            '<option value="">Select type...</option>',
            '<option value=""><?= __(\'admin.col_editor_select_type_ph\') ?></option>',
        ),
        (
            "<label>Mandatory Field</label>",
            "<label><?= __('admin.col_editor_mandatory_field') ?></label>",
        ),
        (
            "<label>Options (one per line, format: label|value)</label>",
            "<label><?= __('admin.col_editor_field_options_label') ?></label>",
        ),
        (
            'placeholder="auto-generated"',
            'placeholder="<?= htmlspecialchars(__(\'admin.col_editor_slug_auto_ph\')) ?>"',
        ),
        (
            'placeholder="Describe what this sub-collection is for"',
            'placeholder="<?= htmlspecialchars(__(\'admin.col_editor_describe_sub_ph\')) ?>"',
        ),
        (
            'placeholder="Describe what this collection is for"',
            'placeholder="<?= htmlspecialchars(__(\'admin.col_editor_describe_collection_ph\')) ?>"',
        ),
        (
            "Auto-generate slug from title",
            "<?= __('admin.col_editor_auto_generate_slug') ?>",
        ),
        (
            "<label>Default Sort Order</label>",
            "<label><?= __('admin.col_editor_default_sort_order') ?></label>",
        ),
        (
            "? 'selected' : '' ?>>Newest First</option>",
            "? 'selected' : '' ?>><?= __('admin.col_editor_sort_newest') ?></option>",
        ),
        (
            "? 'selected' : '' ?>>Oldest First</option>",
            "? 'selected' : '' ?>><?= __('admin.col_editor_sort_oldest') ?></option>",
        ),
        (
            "? 'selected' : '' ?>>Recently Updated</option>",
            "? 'selected' : '' ?>><?= __('admin.col_editor_sort_recently_updated') ?></option>",
        ),
        (
            '<label for="icon">Icon (emoji)</label>',
            '<label for="icon"><?= __(\'admin.col_editor_icon_emoji_label\') ?></label>',
        ),
        (
            "<span>Allow public read access</span>",
            "<span><?= __('admin.col_editor_allow_public_read') ?></span>",
        ),
        (
            "When off, this collection can only be read by pages rendered by Birkly itself. Turn on only if an external site needs to fetch it. Anything readable this way should be treated as public.",
            "<?= __('admin.col_editor_allow_public_read_help') ?>",
        ),
        (
            '<span class="u-weight-medium">Enable multi-language support for this <?= $isSubCollection ? \'sub-collection\' : \'collection\' ?></span>',
            '<span class="u-weight-medium"><?= $isSubCollection ? __(\'admin.col_editor_multilang_enable_sub\') : __(\'admin.col_editor_multilang_enable_collection\') ?></span>',
        ),
        (
            "When enabled, entries can have content in multiple languages",
            "<?= __('admin.col_editor_multilang_when_enabled_help') ?>",
        ),
        (
            "<span>Allow anonymous submissions</span>",
            "<span><?= __('admin.col_editor_allow_anonymous_submissions') ?></span>",
        ),
        (
            "Off by default. When enabled, anyone can submit to this collection without a domain license or API key.",
            "<?= __('admin.col_editor_allow_anonymous_help') ?>",
        ),
        (
            "<label>Rate Limit (per hour)</label>",
            "<label><?= __('admin.col_editor_rate_limit_per_hour') ?></label>",
        ),
        (
            "Maximum number of submissions allowed per hour per API key",
            "<?= __('admin.col_editor_rate_limit_help') ?>",
        ),
        (
            "<label>Notification Email</label>",
            "<label><?= __('admin.col_editor_notification_email') ?></label>",
        ),
        (
            'placeholder="email@example.com"',
            'placeholder="<?= htmlspecialchars(__(\'admin.col_editor_notification_email_ph\')) ?>"',
        ),
        (
            "Who should receive an email when someone submits this form?",
            "<?= __('admin.col_editor_notification_email_help') ?>",
        ),
        (
            "If enabled, entries will be published automatically. Otherwise, they'll be saved as drafts for review.",
            "<?= __('admin.col_editor_auto_publish_draft_help') ?>",
        ),
        (
            'data-picker-help="Which email connection should send these notifications?"',
            'data-picker-help="<?= htmlspecialchars(__(\'admin.col_editor_email_connection_picker_help\'), ENT_QUOTES, \'UTF-8\') ?>"',
        ),
        (
            "Choose how entries can be created for this sub-collection",
            "<?= __('admin.col_editor_entry_method_help_sub') ?>",
        ),
        (
            "Choose how entries can be created for this collection",
            "<?= __('admin.col_editor_entry_method_help_collection') ?>",
        ),
        (
            "<label>Last Update</label>",
            "<label><?= __('admin.col_editor_last_update') ?></label>",
        ),
        (
            "<h4>Field slugs</h4>",
            "<h4><?= __('admin.col_editor_field_slugs_title') ?></h4>",
        ),
        (
            'title="Copy to clipboard"',
            'title="<?= htmlspecialchars(__(\'admin.col_editor_copy_to_clipboard\'), ENT_QUOTES, \'UTF-8\') ?>"',
        ),
        (
            "<h4>Sub-Entry Level Example</h4>",
            "<h4><?= __('admin.col_editor_example_sub_entry_level') ?></h4>",
        ),
        (
            "<h4>Entry Level Example</h4>",
            "<h4><?= __('admin.col_editor_example_entry_level') ?></h4>",
        ),
        (
            "<h4>Collection Level Example</h4>",
            "<h4><?= __('admin.col_editor_example_collection_level') ?></h4>",
        ),
        (
            "<h4>Embeddable Form Code</h4>",
            "<h4><?= __('admin.col_editor_embeddable_form_code') ?></h4>",
        ),
        (
            "Copy and paste this code into your website to create a form that submits to this collection.",
            "<?= __('admin.col_editor_form_code_help') ?>",
        ),
        (
            "<h2>Select Field Type</h2>",
            "<h2><?= __('admin.col_editor_select_field_type_modal') ?></h2>",
        ),
    ]

    settings_pairs: list[tuple[str, str]] = [
        (
            "<h3 class=\"card-title\">Domains &amp; delivery</h3>",
            "<h3 class=\"card-title\"><?= __('admin.settings_delivery_domains_title') ?></h3>",
        ),
        (
            '<p class="section-description">Every hostname registered for CORS and how it is routed for visitors and admin.</p>',
            '<p class="section-description"><?= __(\'admin.settings_delivery_domains_desc\') ?></p>',
        ),
        (
            "                  Redirect alias hostnames to primary URL (301)\n                </label>",
            "                  <?= __('admin.settings_delivery_alias_redirect_label') ?>\n                </label>",
        ),
        (
            '<p class="form-help">When enabled, visitors on aliases such as <code>birkly.io</code> are sent to the primary public URL (e.g. <code>birkly.cloud</code>). Uncheck to keep each hostname in the browser address bar.</p>',
            '<p class="form-help"><?= __(\'admin.settings_delivery_alias_redirect_help\') ?></p>',
        ),
        (
            '<p class="settings-help">Where the CMS and public site are served from.</p>',
            '<p class="settings-help"><?= __(\'admin.settings_hosting_where_served\') ?></p>',
        ),
        (
            '<p data-hosting-project-link class="form-help" hidden>Manage files in <a href="<?= htmlspecialchars(admin_url(\'settings\', [\'tab\' => \'project\'])) ?>">Settings → Project</a>.</p>',
            '<p data-hosting-project-link class="form-help" hidden><?= __(\'admin.settings_project_manage_files_before\') ?><a href="<?= htmlspecialchars(admin_url(\'settings\', [\'tab\' => \'project\'])) ?>"><?= __(\'admin.settings_project_manage_files_link\') ?></a><?= __(\'admin.settings_project_manage_files_after\') ?></p>',
        ),
        (
            '<h2 class="section-title">Project overview</h2>',
            '<h2 class="section-title"><?= __(\'admin.settings_project_overview_title\') ?></h2>',
        ),
        (
            '<p class="settings-help">The bundled website workspace on this installation. CMS content and media stay in Birkly and are read at runtime.</p>',
            '<p class="settings-help"><?= __(\'admin.settings_project_workspace_help\') ?></p>',
        ),
        (
            '                Show “Visit project” in admin header\n              </label>',
            '                <?= __(\'admin.settings_project_visit_header_label\') ?>\n              </label>',
        ),
        (
            '<small class="form-help">Opens the project site in a new tab. Choose the URL style below if one works better on your host.</small>',
            '<small class="form-help"><?= __(\'admin.settings_project_visit_header_help\') ?></small>',
        ),
        (
            '<label for="project-visit-url-format">Visit project URL</label>',
            '<label for="project-visit-url-format"><?= __(\'admin.settings_project_visit_url_label\') ?></label>',
        ),
        (
            '<small class="form-help" id="project-visit-url-format-help">Auto uses site root for SSR, subdirectory installs, and clean public URLs; otherwise index.html when present. Marsk and similar templates usually need site root.</small>',
            '<small class="form-help" id="project-visit-url-format-help"><?= __(\'admin.settings_project_visit_url_help\') ?></small>',
        ),
        (
            '<p class="form-help">AI connections need the <code>manage_project_site</code> permission (Admin role by default) to read or write project files.</p>',
            '<p class="form-help"><?= __(\'admin.settings_project_ai_permission_help\') ?></p>',
        ),
        (
            '<h2 class="section-title">Files</h2>',
            '<h2 class="section-title"><?= __(\'admin.settings_project_files_title\') ?></h2>',
        ),
        (
            '<p class="settings-help">Browse, edit, and upload the HTML, CSS, JS, and assets served from <code>project/</code>.</p>',
            '<p class="settings-help"><?= __(\'admin.settings_project_files_help\') ?></p>',
        ),
        (
            '<h2 class="section-title">Rendering</h2>',
            '<h2 class="section-title"><?= __(\'admin.settings_project_rendering_title\') ?></h2>',
        ),
        (
            '<p class="settings-help">How project pages are rendered and cached.</p>',
            '<p class="settings-help"><?= __(\'admin.settings_rendering_section_help\') ?></p>',
        ),
        (
            '<h3 class="card-title">Server rendering</h3>',
            '<h3 class="card-title"><?= __(\'admin.settings_project_server_rendering_title\') ?></h3>',
        ),
        (
            '<p class="section-description">Pre-render bundled project pages on the server for faster first paint. Server-side rendering is the default for Birkly-hosted project sites and runs on the built-in PHP engine — no Node.js required. External sites always render on the client.</p>',
            '<p class="section-description"><?= __(\'admin.settings_rendering_server_desc\') ?></p>',
        ),
        (
            '<small id="project-hosting-type-help" class="form-help">Controlled by Settings → Site delivery. SSR is only available when mode is “In Birkly (project/)”. <a href="<?= htmlspecialchars(admin_url(\'settings\', [\'tab\' => \'site-delivery\']) . \'#hosting\', ENT_QUOTES, \'UTF-8\') ?>">Open Site delivery</a></small>',
            '<small id="project-hosting-type-help" class="form-help"><?= __(\'admin.settings_hosting_type_help_before\') ?><a href="<?= htmlspecialchars(admin_url(\'settings\', [\'tab\' => \'site-delivery\']) . \'#hosting\', ENT_QUOTES, \'UTF-8\') ?>"><?= __(\'admin.settings_hosting_type_help_link\') ?></a><?= __(\'admin.settings_hosting_type_help_after\') ?></small>',
        ),
        (
            '<small class="form-help">Server pre-renders HTML with the built-in PHP engine — recommended and default for Birkly-hosted projects. Auto uses the server when possible and falls back to the client. Client renders in the browser only.</small>',
            '<small class="form-help"><?= __(\'admin.settings_render_mode_help\') ?></small>',
        ),
        (
            '<small class="form-help">How long pre-rendered pages are cached. <code>0</code> disables caching (every request re-renders). Max 86400 (24h).</small>',
            '<small class="form-help"><?= __(\'admin.settings_cache_ttl_help\') ?></small>',
        ),
        (
            '<p class="form-help" id="project-rendering-external-note" role="status" aria-live="polite" hidden>Server rendering is disabled when Site delivery is not “In Birkly (project/)”. Change the mode under Settings → Site delivery to enable SSR.</p>',
            '<p class="form-help" id="project-rendering-external-note" role="status" aria-live="polite" hidden><?= __(\'admin.settings_rendering_external_note\') ?></p>',
        ),
        (
            '<p class="form-help">Changes here save automatically — no separate save button needed.</p>',
            '<p class="form-help"><?= __(\'admin.settings_rendering_autosave_help\') ?></p>',
        ),
        (
            '<h2 class="section-title">Git sync</h2>',
            '<h2 class="section-title"><?= __(\'admin.settings_project_git_sync_title\') ?></h2>',
        ),
        (
            '<p class="settings-help">Sync the files under <code>project/</code> with a GitHub, GitLab, or generic git remote. CMS content, media, users, and plugins are never included.</p>',
            '<p class="settings-help"><?= __(\'admin.settings_git_sync_help\') ?></p>',
        ),
        (
            '<h2 class="section-title">Activity</h2>',
            '<h2 class="section-title"><?= __(\'admin.settings_project_activity_title\') ?></h2>',
        ),
        (
            '<p class="settings-help">Recent project file changes, settings updates, and git events, with who made each change.</p>',
            '<p class="settings-help"><?= __(\'admin.settings_project_activity_help\') ?></p>',
        ),
        (
            '<p class="settings-help">For detailed API documentation, including available endpoints, request/response formats, and authentication methods, please refer to the Birkly documentation or contact your system administrator.</p>',
            '<p class="settings-help"><?= __(\'admin.settings_integrations_api_doc_help\') ?></p>',
        ),
        (
            '<p class="settings-help">Choose how much visitor data Birkly collects. Use Save Settings in the top bar to apply. Minimal+ enables tracking for your site (including localhost preview).</p>',
            '<p class="settings-help"><?= __(\'admin.settings_analytics_preset_help\') ?></p>',
        ),
        (
            '<p class="form-help">Not legal advice — you remain responsible for privacy law in your region.</p>',
            '<p class="form-help"><?= __(\'admin.settings_analytics_legal_disclaimer\') ?></p>',
        ),
        (
            '<p class="settings-help" id="analytics-report-help">Combined snapshot across all enabled domains. Depth follows your preset.</p>',
            '<p class="settings-help" id="analytics-report-help"><?= __(\'admin.settings_analytics_report_help\') ?></p>',
        ),
        (
            '<small class="form-help">Enter the domain name without http:// or https://</small>',
            '<small class="form-help"><?= __(\'admin.settings_domain_name_help\') ?></small>',
        ),
        (
            '<small id="domain-delivery-role-help" class="form-help">Choose how this hostname is used for site delivery. Registering <code>birkly.io</code> also enables <code>www.birkly.io</code> for CORS and routing.</small>',
            '<small id="domain-delivery-role-help" class="form-help"><?= __(\'admin.settings_domain_delivery_role_help\') ?></small>',
        ),
        (
            '<small class="form-help">Read Only: Can fetch content. Read & Write: Can fetch and submit content.</small>',
            '<small class="form-help"><?= __(\'admin.settings_domain_api_read_write_help\') ?></small>',
        ),
    ]

    failed: list[str] = []
    failed.extend(apply_pairs(COL, col_pairs, "collection-editor.html"))
    failed.extend(apply_pairs(SETTINGS, settings_pairs, "settings.html"))

    print(f"Wired collection-editor: {len(col_pairs)} replacement rules")
    print(f"Wired settings: {len(settings_pairs)} replacement rules")
    if failed:
        print(f"\nFailed replacements ({len(failed)}):")
        for line in failed:
            print(f"  - {line}")
    else:
        print("\nAll replacements applied.")


if __name__ == "__main__":
    main()
