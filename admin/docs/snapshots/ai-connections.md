# AI connections (outbound)

Configure **outbound** providers for in-admin chat under **Settings → AI Connections**.

## Providers

OpenAI, Anthropic, Google, OpenRouter, Ollama (local), or custom HTTP URL.

## API key required

- Paste your **provider API key** from the developer console.
- **ChatGPT Plus / Claude Pro subscriptions are not API keys** — they do not work here.
- Ollama: leave key blank; default URL `http://localhost:11434/api/chat`.

## Wizard

Add connection → choose provider → enter key → test call → set role and approval.

## Each connection

- **Role** — limits what the AI can read/write
- **Require approval** — writes queue for review (default on)
- **Sharing** — private, all users, or specific users

## Inbound MCP (external clients)

For Claude Desktop and other MCP apps, use **Settings → MCP Connections** — not AI Connections. See Help topic **MCP connections (inbound)**.

## Related

- AI chat (floating button + connection picker)
- MCP connections (separate tab)
