#!/usr/bin/env python3
"""Wire settings UI sweep + collection remove-field."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SETTINGS = ROOT / "admin/templates/settings.html"
COL = ROOT / "admin/templates/collection-editor.html"
CHUNK = ROOT / "scripts/p85-chunk-settings-ui-sweep-i18n.py"


def merge():
    subprocess.run([sys.executable, str(CHUNK)], check=True, cwd=ROOT)


def apply(path: Path, pairs: list[tuple[str, str]]) -> list[str]:
    text = path.read_text(encoding="utf-8")
    failed = []
    for old, new in pairs:
        if old not in text:
            failed.append(old[:100])
            continue
        text = text.replace(old, new)
    path.write_text(text, encoding="utf-8")
    return failed


def main():
    merge()
    col = [
        ('title="Remove field"', 'title="<?= htmlspecialchars(__(\'admin.col_editor_remove_field\')) ?>"'),
        ('aria-label="Remove field"', 'aria-label="<?= htmlspecialchars(__(\'admin.col_editor_remove_field\')) ?>"'),
    ]
    settings = [
        ('<label for="site-language">Site language</label>', '<label for="site-language"><?= __(\'admin.settings_site_language_label\') ?></label>'),
        ('<option value="auto">Auto (recommended)</option>', '<option value="auto"><?= __(\'admin.settings_project_render_auto\') ?></option>'),
        ('<option value="directory">Site root (no index.html)</option>', '<option value="directory"><?= __(\'admin.settings_project_visit_directory\') ?></option>'),
        ('<option value="index">Include index.html</option>', '<option value="index"><?= __(\'admin.settings_project_visit_index\') ?></option>'),
        ('class="btn btn-secondary btn-sm">Hosting URLs</a>', 'class="btn btn-secondary btn-sm"><?= __(\'admin.settings_project_hosting_urls_btn\') ?></a>'),
        ('class="btn btn-secondary btn-sm">AI connections</a>', 'class="btn btn-secondary btn-sm"><?= __(\'admin.settings_project_ai_connections_btn\') ?></a>'),
        ('<h3 class="card-title">Browse &amp; edit files</h3>', '<h3 class="card-title"><?= __(\'admin.settings_project_browse_files_title\') ?></h3>'),
        ('<p class="project-file-placeholder u-text-sm u-fg-tertiary u-p-4">Select a file to preview or edit</p>', '<p class="project-file-placeholder u-text-sm u-fg-tertiary u-p-4"><?= __(\'admin.settings_project_file_placeholder\') ?></p>'),
        ('id="project-download-btn">Download project</a>', 'id="project-download-btn"><?= __(\'admin.settings_project_download_btn\') ?></a>'),
        ('<label for="project-hosting-type">Hosting (from Site delivery)</label>', '<label for="project-hosting-type"><?= __(\'admin.settings_project_hosting_from_delivery\') ?></label>'),
        ('<option value="birkly">Birkly hosted (project/)</option>', '<option value="birkly"><?= __(\'admin.settings_project_hosting_birkly\') ?></option>'),
        ('<option value="external">External (hosted elsewhere)</option>', '<option value="external"><?= __(\'admin.settings_project_hosting_external\') ?></option>'),
        ('<label for="project-render-mode">Render mode</label>', '<label for="project-render-mode"><?= __(\'admin.settings_project_render_mode_label\') ?></label>'),
        ('<option value="server">Server-side (SSR)</option>', '<option value="server"><?= __(\'admin.settings_project_render_server\') ?></option>'),
        ('<option value="client">Client-side</option>', '<option value="client"><?= __(\'admin.settings_project_render_client\') ?></option>'),
        ('<label for="project-ssr-cache-ttl">Cache TTL (seconds)</label>', '<label for="project-ssr-cache-ttl"><?= __(\'admin.settings_project_cache_ttl_label\') ?></label>'),
        ('id="project-invalidate-ssr-btn">Invalidate SSR cache</button>', 'id="project-invalidate-ssr-btn"><?= __(\'admin.settings_project_invalidate_ssr\') ?></button>'),
        ('id="project-git-refresh-btn">Refresh status</button>', 'id="project-git-refresh-btn"><?= __(\'admin.settings_project_git_refresh_btn\') ?></button>'),
        ('<h3 class="card-title">Recent activity</h3>', '<h3 class="card-title"><?= __(\'admin.settings_project_activity_card_title\') ?></h3>'),
        ('<div class="card-header"><h3 class="card-title">Danger zone</h3></div>', '<div class="card-header"><h3 class="card-title"><?= __(\'admin.settings_danger_zone_title\') ?></h3></div>'),
        (
            '<p class="section-description">Permanently delete all files in <code>project/</code>. This cannot be undone. Download a ZIP first if you need a backup.</p>',
            '<p class="section-description"><?= __(\'admin.settings_danger_zone_desc\') ?></p>',
        ),
        ('id="project-delete-all-btn">Delete project</button>', 'id="project-delete-all-btn"><?= __(\'admin.settings_delete_project_btn\') ?></button>'),
        ('<label for="security-lockout-time">Lockout Time (minutes)</label>', '<label for="security-lockout-time"><?= __(\'admin.settings_security_lockout_label\') ?></label>'),
        ('<h3 class="u-label">API endpoint</h3>', '<h3 class="u-label"><?= __(\'admin.settings_api_endpoint_label\') ?></h3>'),
        ('<p class="u-text-sm">The API is available at:', '<p class="u-text-sm"><?= __(\'admin.settings_api_endpoint_text\') ?>'),
        ('<h3 class="u-label">Authentication</h3>', '<h3 class="u-label"><?= __(\'admin.settings_api_auth_label\') ?></h3>'),
        (
            '<p class="u-text-sm">Admin API requests use session cookies after login. Public content API access requires registered domains<?= $domainCount > 0 ? \' (\' . (int) $domainCount . \' registered)\' : \'\' ?>. <a href="<?= htmlspecialchars($domainsTabUrl) ?>">Manage domains</a></p>',
            '<p class="u-text-sm"><?= __(\'admin.settings_api_auth_text_before\') ?><?= $domainCount > 0 ? \' (\' . (int) $domainCount . __(\'admin.settings_api_auth_text_after\')\' : \'\' ?><a href="<?= htmlspecialchars($domainsTabUrl) ?>"><?= __(\'admin.settings_api_auth_text_link\') ?></a></p>',
        ),
        ('<h3 class="u-label">Rate limiting</h3>', '<h3 class="u-label"><?= __(\'admin.settings_api_rate_limit_label\') ?></h3>'),
        (
            '<p class="u-text-sm">Per-domain rate limits apply to registered API domains. Configure limits in the <a href="<?= htmlspecialchars($domainsTabUrl) ?>">Domains</a> tab.</p>',
            '<p class="u-text-sm"><?= __(\'admin.settings_api_rate_limit_text_before\') ?><a href="<?= htmlspecialchars($domainsTabUrl) ?>"><?= __(\'admin.settings_api_rate_limit_text_link\') ?></a><?= __(\'admin.settings_api_rate_limit_text_after\') ?></p>',
        ),
        ('<h2 class="section-title">Analytics</h2>', '<h2 class="section-title"><?= __(\'admin.settings_analytics_section_title\') ?></h2>'),
        ('id="analytics-csv-export" hidden>Export CSV</button>', 'id="analytics-csv-export" hidden><?= __(\'admin.settings_analytics_export_csv\') ?></button>'),
        ('<label for="analytics-consent-text">Consent banner text (Standard+)</label>', '<label for="analytics-consent-text"><?= __(\'admin.settings_analytics_consent_label\') ?></label>'),
        ('<label for="analytics-retention-days">Retention days (optional override)</label>', '<label for="analytics-retention-days"><?= __(\'admin.settings_analytics_retention_label\') ?></label>'),
        ('placeholder="Preset default"', 'placeholder="<?= htmlspecialchars(__(\'admin.settings_analytics_retention_ph\')) ?>"'),
        ('<h2 class="section-title">Report</h2>', '<h2 class="section-title"><?= __(\'admin.settings_analytics_report_title\') ?></h2>'),
        ('<label class="u-sr-only" for="analytics-domain-scope">Report scope</label>', '<label class="u-sr-only" for="analytics-domain-scope"><?= __(\'admin.settings_analytics_report_scope\') ?></label>'),
        ('aria-label="Report scope"', 'aria-label="<?= htmlspecialchars(__(\'admin.settings_analytics_report_scope\')) ?>"'),
        ('<option value="all">All enabled domains</option>', '<option value="all"><?= __(\'admin.settings_analytics_scope_all_domains\') ?></option>'),
        ('<h2 class="section-title">Developer mode</h2>', '<h2 class="section-title"><?= __(\'admin.settings_developer_mode_title\') ?></h2>'),
        (
            '<p class="settings-help">Off by default. Unlocks export and diagnostic tools for advanced installs.</p>',
            '<p class="settings-help"><?= __(\'admin.settings_developer_mode_help\') ?></p>',
        ),
        (
            '<span>Include content collections in template export (never users or secrets)</span>',
            '<span><?= __(\'admin.settings_developer_export_include\') ?></span>',
        ),
        ('id="developer-export-btn">Export template pack</button>', 'id="developer-export-btn"><?= __(\'admin.settings_developer_export_btn\') ?></button>'),
        ('id="developer-smoke-btn">API / MCP smoke</button>', 'id="developer-smoke-btn"><?= __(\'admin.settings_developer_smoke_btn\') ?></button>'),
        ('<label for="domain-delivery-role">Delivery role</label>', '<label for="domain-delivery-role"><?= __(\'admin.settings_domain_delivery_role_label\') ?></label>'),
        (
            '<option value="public">Public alias — visitors see the site on this hostname</option>',
            '<option value="public"><?= __(\'admin.settings_domain_public_alias_option\') ?></option>',
        ),
        (
            '<option value="cms">CMS alias — admin, API, and birkly-client.js on this hostname</option>',
            '<option value="cms"><?= __(\'admin.settings_domain_cms_alias_option\') ?></option>',
        ),
        (
            '<small class="data-list-meta">Track visitor statistics for this domain. Zero-dependency, self-hosted analytics with complete privacy.</small>',
            '<small class="data-list-meta"><?= __(\'admin.settings_domain_analytics_track_long\') ?></small>',
        ),
        ('<option value="read_write">Read & Write</option>', '<option value="read_write"><?= __(\'admin.settings_domain_read_write\') ?></option>'),
        (
            '<small class="data-list-meta">Track visitor statistics for this domain.</small>',
            '<small class="data-list-meta"><?= __(\'admin.settings_domain_analytics_track\') ?></small>',
        ),
        ('data-admin-action="createRole">Create Role</button>', 'data-admin-action="createRole"><?= __(\'admin.settings_create_role_btn\') ?></button>'),
        ('<label for="invite-expires">Invitation Expires In</label>', '<label for="invite-expires"><?= __(\'admin.settings_invite_expires_label\') ?></label>'),
        ('data-admin-action="createUser">Create User</button>', 'data-admin-action="createUser"><?= __(\'admin.settings_create_user_btn\') ?></button>'),
        ('<label for="edit-user-email">Email Address</label>', '<label for="edit-user-email"><?= __(\'admin.settings_edit_user_email\') ?></label>'),
        ('data-admin-action="updateUser">Update User</button>', 'data-admin-action="updateUser"><?= __(\'admin.settings_update_user_btn\') ?></button>'),
        ('data-admin-action="deleteUserFromModal">Delete User</button>', 'data-admin-action="deleteUserFromModal"><?= __(\'admin.settings_delete_user_btn\') ?></button>'),
    ]
    cf = apply(COL, col)
    sf = apply(SETTINGS, settings)
    print(f"collection-editor failed: {len(cf)}")
    print(f"settings failed: {len(sf)}")
    if cf:
        print(cf)
    if sf:
        print(sf)


if __name__ == "__main__":
    main()
