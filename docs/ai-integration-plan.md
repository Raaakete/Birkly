# AI Integration Plan - Simple Explanation

## The Big Picture

Think of it like this: **Users connect their own AI accounts (like connecting a Google account), and the CMS acts as a translator between the AI and your content.**

## How It Works

### 1. User Connects Their AI Account
- User goes to Settings → AI Connections
- They choose an AI provider (OpenAI, Anthropic, etc.)
- They paste their API key (from their account at that provider)
- The CMS saves this connection, but **never pays for anything** - the user's account gets charged

### 2. AI Gets a "User Account" in Your System
- Each AI connection becomes a "user" with a role (like "editor" or "author")
- The AI can only do what that role allows
- Example: If role is "author", AI can create content but not delete other people's work

### 3. Two Ways to Use AI

#### Option A: Chat in Admin Panel
- User opens a chat window in the admin panel
- They ask: "Create a blog post about cats"
- AI creates the content
- **BUT** - it goes to an "approval queue" first
- Human reviews it, then approves or rejects

#### Option B: MCP Connection (External AI)
- User connects Claude Desktop or ChatGPT to your CMS via MCP
- They chat with AI in their favorite AI app
- AI can see your content, create/edit things
- **Same approval system** - nothing happens automatically

### 4. What AI Can See
- Everything the assigned role can see (collections, entries, etc.)
- Full documentation about how the system works
- Examples of how to create/edit content
- Current permissions and limits

### 5. Approval System
- AI suggests changes → Goes to approval queue
- Human reviews → Approves or rejects
- Only approved changes actually happen

## Technical Details

### User Storage
Each AI connection is stored like a regular user, but with extra fields:
```json
{
  "id": "ai_123",
  "email": "claude@anthropic.ai",
  "type": "ai",
  "role": "editor",
  "provider": "anthropic",
  "api_key_encrypted": "...",
  "created_at": "2024-01-01"
}
```

### Approval Queue
All AI actions go here first:
```json
{
  "id": "approval_123",
  "ai_user_id": "ai_123",
  "action": "create_entry",
  "data": {...},
  "status": "pending",
  "created_at": "2024-01-01"
}
```

### MCP Server
A special API endpoint that speaks the MCP protocol, so external AI tools can connect.

## Implementation Steps

1. **Extend User System** - Add "ai" type users
2. **AI Connection Settings** - UI to connect AI accounts
3. **Approval Queue** - System to review AI actions
4. **Admin Chat Interface** - Chat UI in admin panel
5. **MCP Server** - External connection endpoint
6. **Context System** - Give AI full system knowledge

