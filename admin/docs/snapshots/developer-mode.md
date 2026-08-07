# Developer mode

## Summary

**Developer mode** is an admin setting that is **off by default**. When you turn it on, Birkly unlocks advanced DX tools — including **export as template / site pack**, system info, and debug helpers. Only high-privilege users (`manage_settings` / Admin) should enable it. Template export always includes `project/`; content is optional; users and secrets are never included.

## Beginner

### Turn developer mode on

1. Sign in as an Admin (or another role with **manage settings**).
2. Open **Settings → System** (or the **Developer** subsection on System, depending on your build).
3. Enable **Developer mode**.
4. Click **Save settings**.

When it is on, the admin chrome shows a subtle **Developer mode** cue so you know advanced tools are unlocked. Turn it off again when you finish so day-to-day editors do not see DX surfaces by accident.

### Export as template / site pack

Use this when you want a reusable zip shaped like a Hub site/template pack.

| Included | Rule |
|----------|------|
| **`project/`** | **Always** — HTML, CSS, JS, and other allowlisted site files |
| **Content** (`content/` collections / schemas; demo entries as offered) | **Optional** — checkbox at export time |
| **Users, secrets, API keys, credentials** | **Never** |
| Media library | Not part of the default export unless the UI offers an explicit opt-in (do not assume media is included) |

Typical uses: hand a starter layout to another install, publish a pack via Hub workflows, or archive a site shell without copying accounts.

> **Note:** This is different from [Project Git sync](project-git-sync.md) (push/pull only `project/` to a git remote) and from downloading the project ZIP in **Settings → Project → Files**. Dev-mode export is pack-shaped and can optionally include content schemas/entries.

### Other DX tools (when unlocked)

Exact labels vary by build; v1 tools include:

| Tool | Purpose |
|------|---------|
| **System info** | PHP version, whether `git` is available, paths, `BIRKLY_DATA`, render/hosting mode, active plugins |
| **Debug helpers** | Client `?__dbg=1`, SSR cache header hints, copy MCP URL |
| **Raw settings viewer** | Read-only view of selected **safe** settings files |
| **Log viewer** | Tail application/security logs with scrubbing |
| **API / MCP smoke** | One-click health: `/mcp` initialize and public API ping |
| **Seed / reset demo content** | Optional destructive action with confirm |
| **Feature flags** | Show experimental toggles already present in the install |

## Advanced Users

**Default:** Off. No DX panels until enabled and saved.

**Permission:** Mutating developer-mode settings and running export/smoke tools requires **manage settings** (or equivalent super-admin). Read-only system info may still be gated the same way — treat the whole surface as admin-only.

**Export scrub:** Pack builders must strip `users/`, credential stores under `settings/` (tokens, git credentials, AI keys), `config.php`, and other secrets. Prefer the same `project/` allowlist used by Project Files / Git sync for the site shell.

**vs Hub packs:** Installing packs happens under **Plugins → Hub**. Developer-mode export is the reverse direction — produce a pack from this install. See [Hub, themes, and site packs](../06-plugin-economy/theme-and-template-packs.md).

**vs Analytics CSV:** Visitor analytics export is separate; see [Analytics presets](analytics-presets.md).

## Further reading

- [Settings](settings.md)
- [Project Git sync](project-git-sync.md)
- [Hub, themes, and site packs](../06-plugin-economy/theme-and-template-packs.md)
- [AI on localhost](../04-integration/ai-on-localhost.md)
- [Connect your AI app](mcp-connections.md)
- [Analytics presets](analytics-presets.md)
