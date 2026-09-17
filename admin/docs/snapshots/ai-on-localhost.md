# AI on a local address

## Summary

**Two different ways to use AI with Birkly** — pick the one that matches what you are trying to do.

## Chat inside Birkly (works on a local address)

Use the **built-in assistant** on **Settings → AI**. Birkly talks to your AI provider from the server. You do not need a Connector URL for this.

## Connect an external AI app

Claude, ChatGPT, Cursor, and similar apps connect **into** Birkly using a **Connector URL** from **Settings → AI** (personal connector or service agent).

Those apps must be able to **reach your site on the web**. When your site uses a normal **HTTPS** address, copy the Connector URL, paste it in your AI app, and sign in.

## While your site is only on a local or private address

A Connector URL that points at your computer (for example `http://localhost`) usually **will not work** in external AI apps — their services cannot open pages that only exist on your machine.

**What to do:**

1. **Use the built-in assistant** in the admin while you build content locally.
2. **After your site has HTTPS**, use the Connector URL from this page — same flow as production.

Do not expose the whole admin panel to the internet as a shortcut.

## Advanced (developers)

**Settings → AI → Developer / debug** may list extra tools for special local setups. Use only if you know you need them.

**Related:** [Connect your AI app](mcp-connections.md) · [Built-in assistant](ai-connections.md)
