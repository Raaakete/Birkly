# Connections MCP rules

This document defines how MCP servers, agents, and AI tools may interact with Birkly Connections. The rules are part of the P65 security model: agents can diagnose problems and propose changes, but they cannot read secrets or reconfigure email without explicit human approval.

## Summary

AI and MCP tools follow three rules for Connections:

1. **Health read is always allowed.** Agents can list Connections, read metadata, and check health/status to answer “why is email not working?”
2. **Configure and test require approval.** Any action that creates, edits, deletes, or tests a Connection, or changes aliases and routes, must go through the AI approval queue with the default approval setting turned on.
3. **Never echo secrets.** Agents must not return passwords, API keys, signing secrets, or decrypted credentials in chat, tool results, logs, or external calls.

These rules apply to all Connections interactions regardless of which plugin or agent initiated them.

## Beginner

### What agents can see

When you ask an AI assistant about Connections, it can tell you:

- Which Connections exist and what type they are.
- Whether each Connection is healthy, unhealthy, or not configured.
- The last error message (for example “authentication failed” or “connection timed out”).
- Which alias is mapped to which Connection.
- Recent delivery log statuses.

It cannot tell you:

- The SMTP password.
- The Resend or Mailgun API key.
- The webhook signing secret.
- Any other credential value.

### What agents can change

Agents can propose changes such as:

- Create a new Email Connection.
- Update an alias mapping.
- Add a notification route.
- Run a test send.
- Delete an unused Connection.

Each proposal appears in the **AI approvals** queue. The default setting is that approval is required, so a human must confirm before the change happens. You can disable the approval requirement only by changing the AI approval settings yourself; agents cannot turn it off.

### Example safe questions

- “Why are Commerce order emails failing?” — Safe. The agent reads health and the delivery log.
- “Which provider is mapped to transactional_email?” — Safe. The agent reads alias metadata.
- “Send a test email through the primary Connection.” — Requires approval.
- “Add a Resend Connection with this API key.” — Requires approval; the key is never echoed back.
- “What is my SMTP password?” — Refused. The agent cannot read secrets.

### Why this matters

Connections holds the keys to your email, chat notifications, and external APIs. If an agent could read or change those without oversight, a mistaken prompt could expose credentials or break delivery. The rules keep diagnosis fast while keeping configuration changes human-gated.

## Advanced Users

### Tool exposure

The MCP server exposes read tools and write tools separately:

| Tool | Category | Approval required | Returns secrets |
|------|----------|-------------------|-----------------|
| `connection_list` | Read | No | No |
| `connection_get` | Read | No | No |
| `connection_health` | Read | No | No |
| `delivery_log_query` | Read | No | No |
| `connection_propose_create` | Write | Yes | No input secret returned |
| `connection_propose_update` | Write | Yes | No input secret returned |
| `connection_propose_delete` | Write | Yes | No |
| `connection_test` | Write | Yes | No |
| `alias_update` | Write | Yes | No |
| `route_update` | Write | Yes | No |

Read tools return metadata only. Write tools are implemented as proposals that enter the AI approval queue. The approval system is the same one used for other AI write actions; see [AI approvals](ai-approvals.md).

### Secret redaction

The MCP layer redacts credential fields before any response leaves core:

- Password fields are replaced with a masked placeholder such as `••••••••`.
- API keys are returned as a short prefix plus ellipsis, for example `re_••••abcd`.
- If a caller attempts to request `with_secrets`, the capability check fails unless the caller holds a privileged server role; MCP agents never hold that role.

Even when an admin approves a proposed update, the agent only submits the new secret value; it does not receive the old one.

### Approval default

P65-D5 locks the default approval setting to **on** for all Connection write and test tools. Admins may opt out through the AI approvals settings, but the agent cannot request or perform that opt-out.

### Audit trail

Every agent-driven proposal is logged:

- Agent id and session.
- Proposed fields (secret values excluded).
- Human approver identity.
- Timestamp and result.

Delivery failures triggered by agent tests are also recorded as system events.

### Error responses

When a rule is violated, the MCP tool returns a clear error:

| Violation | Error |
|-----------|-------|
| Attempt to read secrets | `secrets_not_readable` |
| Write without approval | `approval_required` |
| Test without approval | `approval_required` |
| Secret echoed in prompt or result | `secret_redaction_refused` |

### Implementation note for core developers

The MCP handler should call the same core functions as the admin REST API, but with an explicit `context = 'mcp'` flag. Core enforces the redaction and approval rules; the MCP layer should not rely on the agent to withhold secrets.

## Related

- [Connections](connections.md)
- [Connections provider recipes](connections-provider-recipes.md)
- [Migrate from Automation Setup email](connections-migration.md)
- [Connections API reference](../04-integration/connections-api-reference.md)
- [AI approvals](ai-approvals.md)
- [MCP connections](mcp-connections.md)
