# Security and review — consent, QC, and supply chain

Marketplace installs require explicit **permission consent**. Automated scans and manual review protect the catalog. Site owners can further restrict plugins with the security denylist.

## Summary

Before a plugin installs, the CMS shows a **consent screen** listing declared permissions, hooks, and data access from `plugin.json`. Consent is recorded per offer version. The marketplace runs **automated preflight** (zip structure, dangerous code patterns, capability checks) and **manual review** for verified-tier offers. Site admins can add collections and admin spaces to the **plugin security denylist** to hard-block access.

## Beginner

**Permission consent at install**

When you click **Install** on a marketplace offer, the CMS reads the plugin's declared access:

| Declaration | What it means |
|-------------|---------------|
| **Permissions** | Admin actions the plugin can perform |
| **Hooks** | CMS events the plugin listens to |
| **Data access** | Whether the plugin can read or write entries, media, users, or settings |

You must **Accept** before the download starts. The consent is saved with your admin email and timestamp. If you install a **new version** that changes declarations, you see the consent screen again.

**Review before you accept**

- Compare declared access to what the plugin claims to do on its marketplace listing.
- Prefer **verified** or **official** offers for sensitive sites.
- Decline install if the permissions seem excessive — contact the provider or choose an alternative.

**After install — limiting access**

Even after consent, you can tighten boundaries:

1. Open **Settings → Security → Plugin security denylist**.
2. Add **protected collections** (for example `payroll`, `legal/*`).
3. Add **denied admin spaces** (for example block a plugin from the Commerce admin area).
4. Save — denylist rules override granted capabilities.

See [Plugin security denylist](../06-extensibility/plugin-security-denylist.md) for step-by-step instructions.

**Marketplace quality checks**

Before an offer reaches the catalog:

| Check | What it catches |
|-------|-----------------|
| Zip structure | Missing files, path traversal (`..`) |
| PHP scan | Dangerous functions (`eval`, `shell_exec`, etc.) |
| Manifest validation | Invalid semver, missing required fields |
| Capability match | Declarations vs actual plugin behavior (verified tier) |

**Trust levels**

| Level | Review depth |
|-------|--------------|
| Community | Automated preflight only |
| Trusted | Automated plus possible spot-check |
| Verified | Manual technical review for first release and major updates |

**Supply chain hygiene**

- Install only from [marketplace.birkly.cloud](https://marketplace.birkly.cloud) or sources you trust.
- Verify SHA-256 checksums match the offer page (the CMS does this automatically).
- Keep Birkly core and plugins updated — see [Updates](../06-extensibility/updates.md).
- Revoke or uninstall plugins you no longer use.

## Advanced Users

**Consent storage**

`settings/marketplace_consent.json`:

```json
{
  "my-plugin-slug": {
    "1.2.0": {
      "accepted_at": "2026-07-24T10:00:00+00:00",
      "accepted_by": "admin@example.com",
      "permissions_manifest": {
        "permissions": ["read_entries"],
        "hooks": ["entry.saved"],
        "data_access": {
          "entries": "read",
          "media": "none",
          "users": "none",
          "settings": "none"
        }
      }
    }
  }
}
```

Install API returns `consent_required: true` when no record exists for `{slug, version}`. Error code `consent_required` blocks install until the client sends `consent_accepted: true`.

**Denylist-first enforcement**

`has_plugin_capability()` calls `is_resource_protected($scope)` before permission lookup. `is_space_denied($space_id)` blocks space-scoped hooks and admin injections. Denylist does not affect core CMS or human admin users — only plugin code paths.

**QC pipeline (providers)**

Preflight runs on every release upload. Verified-tier offers enqueue manual review before catalog visibility. Providers can connect GitHub for tag-triggered builds that enter the same QC queue — offers are not auto-published without passing checks.

**Security scan tiers**

| Tier | Scope |
|------|-------|
| Structural | Zip layout, `plugin.json` schema |
| Pattern | PHP dangerous-function regex scan |
| Manual | Code review for verified first releases |
| Runtime | Capability and denylist enforcement in CMS |

**Incident response**

Report suspicious offers via marketplace support. Platform operators can delist offers, hold or revoke entitlements, and add slug patterns to blocklists. Site owners should disable affected plugins immediately and review audit logs — see [Audit log and activity](../06-extensibility/audit-log-and-activity.md).

## Related

- [For site owners](for-site-owners.md)
- [Plugin development](plugin-development.md)
- [For providers](for-providers.md)
- [Plugin security denylist](../06-extensibility/plugin-security-denylist.md)
- [Security](../07-operations/security.md)
