#!/usr/bin/env python3
"""P85: wire final tier-1 admin i18n into templates.

Run after: python3 scripts/p85-chunk-final-admin-i18n.py
"""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CHUNK = ROOT / "scripts/p85-chunk-final-admin-i18n.py"

COL = ROOT / "admin/templates/collection-editor.html"
SETTINGS = ROOT / "admin/templates/settings.html"
ENTRIES = ROOT / "admin/templates/entries.html"
ENTRY_DETAIL = ROOT / "admin/templates/entry-detail.html"
HELP = ROOT / "admin/templates/help.html"
ACCOUNT = ROOT / "admin/templates/account.html"


def merge_lang() -> None:
    subprocess.run([sys.executable, str(CHUNK)], check=True, cwd=ROOT)


def apply_pairs(path: Path, pairs: list[tuple[str, str]], label: str) -> list[str]:
    text = path.read_text(encoding="utf-8")
    failed: list[str] = []
    for old, new in pairs:
        if old not in text:
            snippet = old[:100] + "…" if len(old) > 100 else old
            failed.append(f"[{label}] not found: {snippet!r}")
            continue
        text = text.replace(old, new)
    path.write_text(text, encoding="utf-8")
    return failed


def main() -> None:
    merge_lang()
    failed: list[str] = []

    remove_sub_btn = (
        'class="btn-icon remove-sub-collection-btn" title="Remove sub-collection">',
        'class="btn-icon remove-sub-collection-btn" title="<?= htmlspecialchars(__(\'admin.col_editor_remove_sub_collection\')) ?>" '
        'aria-label="<?= htmlspecialchars(__(\'admin.col_editor_remove_sub_collection\')) ?>">',
    )
    move_up = (
        'class="btn-icon field-move-up-btn" title="Move field up" aria-label="Move field up">',
        'class="btn-icon field-move-up-btn" title="<?= htmlspecialchars(__(\'admin.col_editor_move_field_up\')) ?>" '
        'aria-label="<?= htmlspecialchars(__(\'admin.col_editor_move_field_up\')) ?>">',
    )
    move_down = (
        'class="btn-icon field-move-down-btn" title="Move field down" aria-label="Move field down">',
        'class="btn-icon field-move-down-btn" title="<?= htmlspecialchars(__(\'admin.col_editor_move_field_down\')) ?>" '
        'aria-label="<?= htmlspecialchars(__(\'admin.col_editor_move_field_down\')) ?>">',
    )
    gen_api = (
        'title="Generate new API key">',
        'title="<?= htmlspecialchars(__(\'admin.col_editor_generate_api_key_title\')) ?>">',
    )
    copy_api = (
        'title="Copy API key"',
        'title="<?= htmlspecialchars(__(\'admin.col_editor_copy_api_key_title\')) ?>"',
    )

    col_pairs: list[tuple[str, str]] = [
        remove_sub_btn,
        move_up,
        move_down,
        gen_api,
        copy_api,
        ("<label>Name <span class=\"required\">*</span></label>", "<label><?= __('admin.name') ?> <span class=\"required\">*</span></label>"),
        ("<label>Slug <span class=\"required\">*</span></label>", "<label><?= __('admin.col_editor_slug_label') ?> <span class=\"required\">*</span></label>"),
        ("<label>Description</label>", "<label><?= __('admin.description') ?></label>"),
        ("<label>Entry Method</label>", "<label><?= __('admin.col_editor_entry_method_label') ?></label>"),
        ('<label for="sub-collection-slug-sidebar">Slug</label>', '<label for="sub-collection-slug-sidebar"><?= __(\'admin.col_editor_slug_label\') ?></label>'),
        ('<label for="slug">Slug</label>', '<label for="slug"><?= __(\'admin.col_editor_slug_label\') ?></label>'),
        ('<label for="icon_key">Icon key</label>', '<label for="icon_key"><?= __(\'admin.col_editor_icon_key_label\') ?></label>'),
        ("<label>API Key</label>", "<label><?= __('admin.api_key') ?></label>"),
        ("<label>Created</label>", "<label><?= __('admin.created') ?></label>"),
        (
            "For a detailed overview about templating, please visit \n                                    <a href=\"<?= admin_url('help') ?>#templating\" target=\"_blank\">the documentation site</a>.",
            "<?= __('admin.col_editor_templating_intro_before') ?>\n                                    <a href=\"<?= admin_url('help') ?>#templating\" target=\"_blank\"><?= __('admin.col_editor_templating_doc_link') ?></a>.",
        ),
        ('<span class="fieldtype-name">New Field</span>', '<span class="fieldtype-name"><?= __(\'admin.col_editor_new_field_default\') ?></span>'),
    ]
    failed += apply_pairs(COL, col_pairs, "collection-editor.html")

    settings_pairs: list[tuple[str, str]] = [
        ('<h3 class="card-title">Overview</h3>', '<h3 class="card-title"><?= __(\'admin.settings_project_overview_card\') ?></h3>'),
        (
            'class="btn btn-secondary btn-sm">Domains</a>',
            'class="btn btn-secondary btn-sm"><?= __(\'admin.domains\') ?></a>',
        ),
        ('id="project-new-folder-btn">New folder</button>', 'id="project-new-folder-btn"><?= __(\'admin.new_folder\') ?></button>'),
        ('id="project-upload-file-btn">Upload</button>', 'id="project-upload-file-btn"><?= __(\'admin.upload\') ?></button>'),
        ('id="project-add-file-btn">New file</button>', 'id="project-add-file-btn"><?= __(\'admin.settings_project_new_file_btn\') ?></button>'),
        ('<h3 id="project-preview-title">Preview</h3>', '<h3 id="project-preview-title"><?= __(\'admin.preview\') ?></h3>'),
        ('<h3 class="card-title">Repository</h3>', '<h3 class="card-title"><?= __(\'admin.settings_repository_title\') ?></h3>'),
        (
            'data-project-activity-filter="all">All</button>',
            'data-project-activity-filter="all"><?= __(\'admin.settings_project_activity_filter_all\') ?></button>',
        ),
        (
            'data-project-activity-filter="files">Files</button>',
            'data-project-activity-filter="files"><?= __(\'admin.settings_project_activity_filter_files\') ?></button>',
        ),
        (
            'data-project-activity-filter="git">Git</button>',
            'data-project-activity-filter="git"><?= __(\'admin.settings_project_activity_filter_git\') ?></button>',
        ),
        (
            'data-project-activity-filter="settings">Settings</button>',
            'data-project-activity-filter="settings"><?= __(\'admin.settings_project_activity_filter_settings\') ?></button>',
        ),
        ('<p class="u-label">AI connections</p>', '<p class="u-label"><?= __(\'admin.settings_team_ai_connections_label\') ?></p>'),
        ('<p class="u-text-sm u-fg-tertiary">None active</p>', '<p class="u-text-sm u-fg-tertiary"><?= __(\'admin.settings_team_ai_connections_none\') ?></p>'),
        (
            '<span class="data-list-meta">outbound · since <?= htmlspecialchars($connectedLabel) ?></span>',
            '<span class="data-list-meta"><?= __(\'admin.settings_connection_meta_outbound\', [\'connected\' => $connectedLabel]) ?></span>',
        ),
        (
            '<span class="data-list-meta">MCP · since <?= htmlspecialchars($connectedLabel) ?></span>',
            '<span class="data-list-meta"><?= __(\'admin.settings_connection_meta_mcp\', [\'connected\' => $connectedLabel]) ?></span>',
        ),
        ('<h2>Role Management</h2>', '<h2><?= __(\'admin.role_management\') ?></h2>'),
        ('<label for="db-user">Database User</label>', '<label for="db-user"><?= __(\'admin.settings_db_user_label\') ?></label>'),
        ('<label for="db-prefix">Table Prefix</label>', '<label for="db-prefix"><?= __(\'admin.settings_db_table_prefix_label\') ?></label>'),
        ('<option value="read_only">Read Only</option>', '<option value="read_only"><?= __(\'admin.settings_storage_read_only\') ?></option>'),
        ('<option value="viewer">Viewer</option>', '<option value="viewer"><?= __(\'admin.settings_role_option_viewer\') ?></option>'),
        ('<option value="editor">Editor</option>', '<option value="editor"><?= __(\'admin.settings_role_option_editor\') ?></option>'),
        ('<option value="admin">Admin</option>', '<option value="admin"><?= __(\'admin.settings_role_option_admin\') ?></option>'),
    ]
    failed += apply_pairs(SETTINGS, settings_pairs, "settings.html")

    entries_pairs: list[tuple[str, str]] = [
        ('<span class="dropdown-item-label">Edit this collection</span>', '<span class="dropdown-item-label"><?= __(\'admin.entries_edit_this_collection\') ?></span>'),
        (
            '<span class="dropdown-item-label">Create sub-collection</span>',
            '<span class="dropdown-item-label"><?= __(\'admin.entries_create_sub_collection\') ?></span>',
        ),
        (
            '<span class="dropdown-item-label">Back to active entries</span>',
            '<span class="dropdown-item-label"><?= __(\'admin.entries_back_to_active\') ?></span>',
        ),
    ]
    failed += apply_pairs(ENTRIES, entries_pairs, "entries.html")

    entry_detail_pairs: list[tuple[str, str]] = [
        (
            '<span class="dropdown-item-label">Edit this sub-collection (<?= htmlspecialchars($subCollectionSlug) ?>)</span>',
            '<span class="dropdown-item-label"><?= __(\'admin.entry_detail_edit_sub_collection\') ?> (<?= htmlspecialchars($subCollectionSlug) ?>)</span>',
        ),
        (
            '<span class="dropdown-item-label">Add a new sub-collection under (<?= htmlspecialchars($subCollectionSlug) ?>)</span>',
            '<span class="dropdown-item-label"><?= __(\'admin.entry_detail_add_sub_collection_under\') ?> (<?= htmlspecialchars($subCollectionSlug) ?>)</span>',
        ),
    ]
    failed += apply_pairs(ENTRY_DETAIL, entry_detail_pairs, "entry-detail.html")

    help_pairs: list[tuple[str, str]] = [
        ('<p class="loading-state">Loading documentation…</p>', '<p class="loading-state"><?= __(\'admin.help_loading_documentation\') ?></p>'),
    ]
    failed += apply_pairs(HELP, help_pairs, "help.html")

    account_pairs: list[tuple[str, str]] = [
        ('<h2 class="card-title">Display &amp; spacing</h2>', '<h2 class="card-title">{translate \'admin.account_display_spacing_title\'}</h2>'),
        (
            '<p class="card-description">Adjust text size and spacing across the admin interface.</p>',
            '<p class="card-description">{translate \'admin.account_display_spacing_description\'}</p>',
        ),
    ]
    failed += apply_pairs(ACCOUNT, account_pairs, "account.html")

    if failed:
        print("FAILED replacements:")
        for line in failed:
            print(f"  {line}")
        sys.exit(1)
    print("Wire final admin: OK")


if __name__ == "__main__":
    main()
