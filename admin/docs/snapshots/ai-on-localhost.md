# AI on a local address

## Summary

**Cloud connector URLs** (Claude, ChatGPT, Cursor over the internet) need a **public HTTPS** site. When Birkly runs on **localhost** or a private address, use **Connect desktop app** on **Settings → AI** instead.

## Connect desktop app (recommended on localhost)

For **Claude Desktop** or **Cursor** on the **same computer** as Birkly:

1. Open **Settings → AI**.
2. In **Connect desktop app**, click **Pair this computer**.
3. Choose **Claude Desktop** or **Cursor**, then **Download for Mac** or **Download for Windows**.
4. Open the downloaded helper once (double-click; on Mac, right-click → **Open** if needed).
5. Fully quit the desktop app, then open it again.

Birkly uses a small **PHP bridge** (`scripts/birkly-mcp-desktop.php`) — no Node, `mcp-remote`, or API keys. Tokens are stored in `data/mcp_desktop/` on the server (not in the config snippet).

## Built-in assistant (optional)

You can also use the **built-in assistant** on **Settings → AI** for chat inside the admin. That uses your provider API key and does not need a Connector URL.

## After your site has public HTTPS

Use the **Connector URL** from the personal connector card — same OAuth flow as production. You do not need the desktop bridge on a public HTTPS site unless you prefer stdio MCP locally.

**Related:** [Connect your AI app](mcp-connections.md) · [Built-in assistant](ai-connections.md)
