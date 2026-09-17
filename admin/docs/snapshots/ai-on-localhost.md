# AI on a local address

## Summary

**Cloud connector URLs** (Claude, ChatGPT, Cursor over the internet) need a **public HTTPS** site. When Birkly runs on **localhost** or a private address, use **Connect desktop app** on **Settings → AI** instead.

## Connect desktop app (recommended on localhost)

For **Claude Desktop** or **Cursor** on the **same computer** as Birkly:

1. Open **Settings → AI**.
2. In **Connect desktop app**, click **Pair this computer**.
3. Choose **Claude Desktop** or **Cursor** (the badge shows which is selected).
4. **On Mac (recommended):** copy the **Terminal command**, open Terminal, paste, press Enter.  
   **Alternative:** download the helper — if macOS blocks it, use the Terminal command or **System Settings → Privacy & Security → Open Anyway**.
5. Fully quit the desktop app, then open it again. In Claude: **Settings → Developer** → MCP servers → **birkly**.

Birkly uses a small **PHP bridge** (`scripts/birkly-mcp-desktop.php`) — no Node, `mcp-remote`, or API keys. Tokens are stored in `data/mcp_desktop/` on the server (not in the config snippet). The bridge talks to your Birkly **directly on disk** (same PHP install); you do **not** need `php -S` or Herd running for tool calls after pairing.

**Tool count:** Claude may list fewer tools for the local **birkly** connector than for a hosted site (e.g. birkly.io). That is normal — the list includes only **core tools plus MCP tools from plugins that are installed and active** on *this* instance. Install or activate Commerce, Automation, User, etc. from the Hub to match a production tool set.

After updating Birkly, run the **Terminal install** command again (or edit Claude **Developer → Local MCP** so `args` use `--token-file` and the credentials path as **two** separate entries, not `--token-file=/path with spaces`).

## Disconnect or remove

| Goal | What to do |
|------|------------|
| Stop AI using Birkly temporarily | Turn off **birkly** in Claude **Konnektoren** or Developer → MCP |
| Revoke tokens on the server | **Settings → AI** → **Revoke** (desktop card) |
| Remove **birkly** from Claude completely | **Settings → AI** → expand **Remove from Claude or Cursor** → run the Terminal uninstall command, then fully quit Claude |

Claude does not offer “delete” for local stdio MCP servers in the Connectors UI — only disable. Uninstall removes the `birkly` entry from `~/Library/Application Support/Claude/claude_desktop_config.json`.

If the server shows **Server disconnected** after it worked once: fully quit Claude, run the **install** Terminal command again (fixes token path and config), reopen Claude, and check **Protokolle anzeigen** in Developer → MCP or `logs/php_errors.log` in your Birkly folder.

## Built-in assistant (optional)

You can also use the **built-in assistant** on **Settings → AI** for chat inside the admin. That uses your provider API key and does not need a Connector URL.

## After your site has public HTTPS

Use the **Connector URL** from the personal connector card — same OAuth flow as production. You do not need the desktop bridge on a public HTTPS site unless you prefer stdio MCP locally.

**Related:** [Connect your AI app](mcp-connections.md) · [Built-in assistant](ai-connections.md)
