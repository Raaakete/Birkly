# User Management playbook

**Requires:** User Management plugin.

Use for: site registration, login, member profiles, tiers, member-submitted content.

## Capabilities

- Public registration / login (not admin users)
- User tiers / roles on the **site**
- Profile collections linked to users
- Template auth helpers
- Sub-collection `entry_method: user_submit` — logged-in members add content

## Template functions

| Function | Purpose |
|----------|---------|
| `{user_logged_in}` | Conditional logged-in state |
| `{user_current}` | Current site user data |
| `{user_profile}` | Profile fields |
| `{user_can 'permission'}` | Permission check |

## Typical setup

1. Install + enable User Management plugin
2. Admin: configure registration, verification, default tier
3. Collection for profiles (optional) — link to user records
4. Member content: sub-collection with `user_submit` on parent (e.g. group posts)
5. Templates: login/register pages in `project/`
6. Automation: `user_registered`, `user_login` triggers

## Admin users vs site users

| Type | Where managed | AI create? |
|------|---------------|------------|
| Admin users | Settings → Users | No — no `create_user` tool |
| Site users | User Management plugin | Admin UI / public registration |

## AI workflow: "Members can post in forums"

1. Parent collection `forums`, entries = topics
2. Sub-collection `posts` with `entry_method: user_submit`
3. User Management enabled + auth templates
4. `read_birkly_docs` `sub-collections` + this playbook

## Related

- `public-forms` — anonymous submission (`public_api`)
- `sub-collections` — `user_submit` vs `public_api`
- `automation` — registration triggers

## Reference

`read_birkly_docs` `user_management` when plugin active.
