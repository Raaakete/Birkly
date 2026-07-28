# User Management playbook

**Requires:** User Management plugin.

Use for: public registration, login, member profiles, tiers, permission-gated content, member-submitted entries.

**Admin doc (human UI):** `admin/docs/snapshots/user-plugin.md`  
**Help topic id:** `user-plugin` → AI playbook `user-management`

---

## Admin users vs site users (critical distinction)

| Type | Where | Purpose | AI can create? |
|------|-------|---------|----------------|
| **Admin users** | Settings → Users | CMS back-office access | **No** — no `create_user` tool; direct user to Settings → Users → Add User |
| **Site users** | User Management plugin | Front-end accounts, members | Public registration or admin **Users** plugin page |

Never confuse `{user_logged_in}` (site) with admin session login.

---

## Capabilities overview

- Public **registration** and **login** on the website (not admin panel)
- **Groups** and **tiers** for shared permissions or limits
- **Profile collections** linked to user records
- Template auth helpers: `{user_logged_in}`, `{user_current}`, `{user_profile}`, `{user_can 'permission'}`
- Sub-collection **`entry_method: user_submit`** — logged-in members add content to a parent entry
- Automation: **`user_registered`**, **`user_login`** (when Automation plugin active)

---

## Setup order

1. **`plugins-overview`** → Marketplace install User Management → Enable
2. Admin: **Users**, **Groups**, **Tiers** appear in sidebar
3. Configure registration (email verification, default tier) in plugin settings
4. Optional: profile collection + User fieldtype on entries
5. **`write_project_file`** — login/register pages in `project/`
6. Member content: sub-collection with `user_submit` on parent collection
7. Templates: conditionals for logged-in state

---

## Template functions

| Function | Purpose | Example |
|----------|---------|---------|
| `{user_logged_in}` | Boolean logged-in state | `{if user_logged_in}Welcome back{else}Please log in{endif}` |
| `{user_current}` | Current site user (id, name, email, …) | `{user_current.name}` |
| `{user_profile "id"}` | Public profile entry | Profile pages |
| `{user_can 'permission'}` | Permission check | Gated content sections |

Exact property names — check plugin ai-context when installed.

### Login-aware nav snippet

```html
<nav>
  {if user_logged_in}
    <span>Hello, {user_current.name}</span>
    <a href="/project/account.html">My account</a>
  {else}
    <a href="/project/login.html">Log in</a>
    <a href="/project/register.html">Register</a>
  {endif}
</nav>
<script src="/birkly-client.js"></script>
```

---

## Member-submitted content (`user_submit`)

For forums, group posts, reviews by members — use **sub-collections**, not `public_api`.

| Surface | entry_method | Who can submit |
|---------|--------------|----------------|
| Anonymous visitors | `public_api` | Anyone | See **`public-forms`** |
| Logged-in members | `user_submit` | Authenticated site users | This playbook |

### Worked example: “Members can post in forums”

1. Parent collection `forums` — each entry = topic
2. Sub-collection `posts` on `forums`:
   - `entry_method: user_submit`
   - Fields: `title`, `body` (match form `name=` attributes)
3. User Management enabled; registration open
4. Detail page `forum.html?collection=forums&slug={slug}`:
   - Loop `{for each entry in 'posts' of 'forums'.current …}`
   - Form for new post (plugin may provide helper — check ai-context)
5. **`read_birkly_docs`** `sub-collections` for CRUD tools

```json
{
  "sub_collections": [
    {
      "slug": "posts",
      "label": "Posts",
      "entry_method": "user_submit",
      "fields": [
        { "name": "title", "type": "text", "required": true },
        { "name": "body", "type": "textarea", "required": true }
      ]
    }
  ]
}
```

---

## Collection permissions (admin)

Let members read/write specific collections:

1. **Collection settings** → permissions section (plugin UI)
2. Tie to **groups** or **tiers**
3. AI: use `read_collection` to inspect; full permission matrix may require admin UI

---

## AI workflow summary

| User request | Steps |
|--------------|-------|
| “Add member login” | `list_plugins` → enable User Management → login/register HTML |
| “Members post comments” | Prefer `user_submit` sub-collection if login required; else `public_api` |
| “User profiles” | Profile collection + User fieldtype + template `{user_profile}` |
| “Notify on signup” | Automation `user_registered` — guide to Automation admin |

---

## Common failure modes

| Symptom | Cause | Fix |
|---------|-------|-----|
| `{user_logged_in}` always false | Plugin off or wrong template page | Enable plugin; include `birkly-client.js` |
| Member post rejected | `public_api` instead of `user_submit` | Update sub-collection entry_method |
| Confused with admin users | Wrong user type | Site users ≠ Settings → Users |
| Registration 403 | CSRF / rate limit / HTTPS | Plugin settings; production HTTPS |

---

## AI tools

| Task | Tool |
|------|------|
| Plugin status | `list_plugins`, `activate_plugin` |
| Schema + sub-collections | `create_collection`, `update_collection`, `read_collection` |
| Templates | `write_project_file` |
| Create site user accounts | **No tool** — registration flow or admin Users page |
| Create admin users | **No tool** — Settings → Users |

---

## Related playbooks

- **`public-forms`** — anonymous `public_api` submission
- **`sub-collections`** — nested entries, `user_submit` vs `public_api`
- **`automation`** — `user_registered`, `user_login` triggers
- **`settings-roles`** — admin CMS roles (different from site tiers)
- **`plugins-overview`** — install from Marketplace

## Admin cross-reference

| Topic | Location |
|-------|----------|
| Enable plugin | Settings → Plugins |
| Manage members | Users, Groups, Tiers (sidebar) |
| Collection permissions | Collection settings (plugin section) |
| Human docs | `admin/docs/snapshots/user-plugin.md` |
