# Plugin scaffolding CLI

Generate a minimal activatable plugin tree from the command line (Phase 15).

## Summary

Run `php scripts/scaffold-plugin.php` in the Birkly repo to create a new plugin folder under `plugins/` with `plugin.php`, `plugin.json`, and starter hooks. Use `--privileged` to print a capability and denylist checklist for extensions that need elevated permissions.

## Beginner

**When to use the scaffold**

You want a custom Birkly plugin without copying an existing plugin by hand. The CLI writes the files Birkly expects so **Settings → Plugins** can discover and activate your extension.

**Create a basic plugin**

From the Birkly project root:

```bash
php scripts/scaffold-plugin.php --id=my-plugin --title="My Plugin"
```

This creates:

```
plugins/my-plugin/
  plugin.php      # bootstrap, hooks
  plugin.json     # name, version, description
  README.md       # short maintainer notes
```

**Next steps**

1. Open `plugins/my-plugin/plugin.php` and add your admin menu or API routes.
2. Declare **capabilities** in `plugin.json` (least privilege).
3. In admin, open **Settings → Plugins** and **Activate** My Plugin.
4. Run `php tests/run.php` if you add tests alongside the scaffold.

**Privileged plugins**

If your plugin reads entries, calls outbound HTTP, or injects admin UI:

```bash
php scripts/scaffold-plugin.php --id=my-integration --title="My Integration" --privileged
```

The `--privileged` flag prints a checklist:

- Required capabilities to declare
- Denylist and `deny_spaces` considerations
- Link to plugin security docs

Review [Plugins](plugins.md) and [Plugin security denylist](plugin-security-denylist.md) before requesting broad permissions.

## Advanced Users

**Script:** `scripts/scaffold-plugin.php`

| Flag | Purpose |
|------|---------|
| `--id=` | Plugin folder id (lowercase, hyphens) — required |
| `--title=` | Human title for `plugin.json` — required |
| `--privileged` | Echo capability/denylist checklist to stdout |
| `--force` | Overwrite existing folder (use with care) |

**Output conforms to** `docs/plugin-system-architecture-plan.md` scaffold section and `plugin-package.schema.json` (marketplace publishing).

**Discovery:** Bootstrap scans `plugins/{id}/plugin.php`; `register_plugin()` or equivalent init must run on activate.

**Tests:** `scaffold_plugin_test.php` — verifies generated tree activates without fatal errors.

**Not included:** Marketplace manifest, zip build, or Composer dependencies — add those separately for distribution.

## Related

- [Plugins](plugins.md)
- [Marketplace — provider publishing guide](marketplace-provider-guide.md)
- [Plugin security denylist](plugin-security-denylist.md)
- [User plugin](user-plugin.md) — reference implementation
