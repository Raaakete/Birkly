# Dashboard

## Summary

The Dashboard is the first screen you see after logging into the Birkly admin. It gives a quick overview of your content (e.g. how many collections and entries you have), recent activity, and quick links to Content, Media, and other areas. You don’t configure it—use it as your starting point to see what’s in the CMS and jump to the right section.

## Beginner

The **Dashboard** is the admin “home page.” Right after you log in, you land here. It’s designed to answer: *What’s in my CMS right now?* and *Where do I go next?*

**What you’ll typically see**

- **Summary numbers** — For example: how many collections exist, how many entries in total, or how many entries per collection (e.g. “Blog: 12 entries,” “Products: 8 entries”). That helps you see at a glance how much content you have.
- **Recent entries** — A short list of the latest content that was created or updated (often with the entry title, collection name, and date). Clicking an item usually opens that entry so you can edit it.
- **Quick actions** — Buttons or links such as “Create entry,” “Upload media,” or “Open Blog.” These shortcuts save you from opening the sidebar and drilling down every time.

**Example**

After logging in you might see:

- **Collections:** 3 (Blog, Products, Pages)
- **Total entries:** 24
- **Recent:** “Welcome post” (Blog, edited 2 hours ago), “Blue widget” (Products, created yesterday)

You can click “Welcome post” to edit it, or click “Blog” to see all blog entries. Nothing on the Dashboard is “editable” itself—it’s a read-only overview. To change content, you go to **Content** and open a collection or an entry; to upload files, you go to **Media**; to change the project name or users, you go to **Settings**.

**Where to go from here**

- **Content** — Create or edit collections and entries (your main workspace).
- **Media** — Upload and manage images and other files.
- **Settings** — Change the project name, logo, users, roles, or plugins (usually only for administrators).
- **Automation** — Set up workflows or webhooks (if your installation has this).
- **Help** — Open the in-admin documentation.

If you’re an administrator, the Dashboard also helps you spot recent changes so you can review or fix something quickly.

## Advanced Users

The Dashboard is a read-only overview screen assembled from the current content and (optionally) activity data. Typical data sources:

- **Counts:** Number of collections; number of entries per collection or total. Often derived from filesystem (content directory) or DB depending on storage.
- **Recent entries:** Last N entries (by modified or created time), with link to entry editor (e.g. `/admin?page=entry-editor&collection=blog&id=...`).
- **Quick actions:** Static or dynamic links (e.g. “New entry” for a default or first collection).

No configuration; layout and widgets depend on the Birkly version and any plugin that injects dashboard blocks. For custom metrics or links, use plugin hooks (if available) or extend the dashboard template. Navigation from Dashboard is via the sidebar (Content, Media, Settings, Automation, Help).

## Related

- [Content overview](content-overview.md)
- [Getting started](../00-overview/getting-started.md)
