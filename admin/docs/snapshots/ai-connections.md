# Built-in assistant

The built-in assistant lets you chat with AI **inside** the Birkly admin using the floating chat button (bottom-right). You connect once with a provider API key under **Agent Guide → Connections → Built-in assistant**.

Connecting Claude, ChatGPT, or Cursor to your site content is separate — see [Connect your AI app](mcp-connections.md).

## Summary

Go to **Agent Guide → Connections → Built-in assistant** and click **Quick connect**. Choose a provider, enter your **API key**, test the connection, and save. Use the chat button in the admin to get help with content. Consumer subscriptions (ChatGPT Plus, Claude Pro) do **not** include API access — you need a developer API key.

## Quick connect

1. **Agent Guide → Connections** tab.
2. Under **Built-in assistant**, click **Quick connect**.
3. Select your **provider** (OpenAI, Anthropic, Google, OpenRouter, Ollama, or Custom).
4. Enter your **API key** (skip for Ollama).
5. Click **Test connection**, then **Connect**.

## Form fields

- **Provider** — OpenAI, Anthropic, Google, OpenRouter, Ollama, or Custom.
- **Connection name** — A label you'll recognize.
- **API key** — From your provider's developer console. Empty for Ollama.
- **API URL** — For Ollama or Custom only.
- **Model** — Optional; defaults to the provider default (**OpenAI: `gpt-4o-mini`**). Legacy `gpt-4` connections are remapped automatically.
- **Role** — Viewer, Author, Editor, or Admin.
- **Require approval** — Recommended for the built-in assistant. Pending write actions appear in the chat **Approvals** tab.

## Collection and entry slugs

When the built-in assistant creates or updates collections and entries, slugs follow Birkly’s URL rules:

- **Lowercase** letters and digits only
- **Hyphens** as word separators (`blog-posts`, not `blog_posts`)
- **Underscores are normalized** to hyphens at write time (e.g. `my_entry` → `my-entry`)
- Invalid characters are stripped before save

You do not need to fix slugs manually — the assistant and approval pipeline normalize them. If a create previously failed on underscores, update to a current Birkly build with slug normalization enabled.

## Approvals

**Built-in assistant only.** When the floating chat assistant wants to change content and **Require approval** is on, actions wait in the **Approvals** tab of the AI Assistant window.

**External AI apps (Claude, ChatGPT, Cursor)** connected via MCP handle write confirmation **inside the client app** — not in the Birkly Approvals tab. See [Connect your AI app](mcp-connections.md).

## Related

- [AI overview](ai-overview.md)
- [Connect your AI app](mcp-connections.md)
- [AI approvals](ai-approvals.md)
- [Settings](settings.md)
