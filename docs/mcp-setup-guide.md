# MCP Setup Guide for Birkly

## What is MCP?

MCP (Model Context Protocol) is a protocol that allows AI assistants to connect to external tools and data sources. Currently, **Claude Desktop** (Anthropic's desktop app) is the primary client that supports MCP.

## Important Note

- ✅ **Claude Desktop** supports MCP
- ❌ **ChatGPT Desktop** does NOT support MCP
- ✅ **Birkly** has an MCP server implementation

If you want to use ChatGPT, you should use the **OpenAI** provider connection in Birkly instead of MCP.

## Setting Up Claude Desktop with Birkly

### Step 1: Install Claude Desktop

1. Download Claude Desktop from: https://claude.ai/download
2. Install it on your computer
3. Sign in with your Anthropic account

### Step 2: Create MCP Connection in Birkly

1. Go to **Settings > AI Connections** in your Birkly admin panel
2. Click **"+ Add Connection"**
3. Select **"MCP (Model Context Protocol)"** as the provider
4. Fill in the connection details:
   - **Connection Name**: e.g., "My Claude Desktop"
   - **Role**: Choose the permissions (Editor recommended)
   - **Who can use**: Choose access level
   - **Require approval**: Enable if you want to review AI actions
5. Click **"Connect"**
6. **Copy the MCP Server URL and API Key** that are displayed

### Step 3: Configure Claude Desktop

Claude Desktop uses a configuration file to connect to MCP servers.

#### On macOS:
The config file is located at:
```
~/Library/Application Support/Claude/claude_desktop_config.json
```

#### On Windows:
The config file is located at:
```
%APPDATA%\Claude\claude_desktop_config.json
```

#### Configuration Format:

Edit the config file and add your Birkly MCP server:

```json
{
  "mcpServers": {
    "birkly": {
      "command": "node",
      "args": [
        "-e",
        "const http = require('http'); const url = require('url'); const querystring = require('querystring'); const server = http.createServer((req, res) => { const parsed = url.parse(req.url, true); const apiKey = parsed.query.api_key || req.headers['x-mcp-api-key']; if (!apiKey) { res.writeHead(401); res.end(JSON.stringify({jsonrpc: '2.0', error: {code: -32000, message: 'API key required'}})); return; } let body = ''; req.on('data', chunk => { body += chunk; }); req.on('end', () => { const input = JSON.parse(body || '{}'); const method = input.method || parsed.query.method || ''; const params = input.params || {}; fetch('YOUR_HARUCMS_URL/api/mcp_server.php?method=' + method + '&api_key=' + apiKey, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-MCP-API-Key': apiKey}, body: JSON.stringify({method, params, id: input.id})}).then(r => r.json()).then(data => { res.writeHead(200, {'Content-Type': 'application/json'}); res.end(JSON.stringify(data)); }).catch(err => { res.writeHead(500); res.end(JSON.stringify({jsonrpc: '2.0', error: {code: -32603, message: err.message}})); }); }); }); server.listen(0, () => { process.stdout.write(JSON.stringify({mcpServer: {name: 'birkly', version: '1.0.0'}}) + '\\n'); });"
      ],
      "env": {
        "HARUCMS_URL": "http://localhost:8080",
        "HARUCMS_API_KEY": "YOUR_MCP_API_KEY_HERE"
      }
    }
  }
}
```

**However**, the above is complex. A simpler approach is to use an HTTP-based MCP bridge.

### Step 4: Using HTTP MCP Bridge (Recommended)

Since Birkly uses HTTP for MCP (not stdio), you'll need a bridge. Here's a simpler approach:

#### Option A: Use a Simple HTTP-to-MCP Bridge Script

Create a file `mcp-bridge.js`:

```javascript
#!/usr/bin/env node

const http = require('http');
const https = require('https');
const url = require('url');

const HARUCMS_URL = process.env.HARUCMS_URL || 'http://localhost:8080';
const API_KEY = process.env.HARUCMS_API_KEY || '';

// MCP stdio server
process.stdin.setEncoding('utf8');

let requestId = 0;
const pendingRequests = {};

process.stdin.on('data', async (chunk) => {
  const lines = chunk.toString().split('\n').filter(l => l.trim());
  
  for (const line of lines) {
    try {
      const message = JSON.parse(line);
      
      if (message.method === 'initialize') {
        // Send initialize response
        const response = {
          jsonrpc: '2.0',
          id: message.id,
          result: {
            protocolVersion: '2024-11-05',
            capabilities: {
              tools: {},
              resources: {}
            },
            serverInfo: {
              name: 'Birkly MCP Server',
              version: '1.0.0'
            }
          }
        };
        process.stdout.write(JSON.stringify(response) + '\n');
        continue;
      }
      
      // Forward other requests to Birkly
      const birklyUrl = new URL('/api/mcp_server.php', HARUCMS_URL);
      birklyUrl.searchParams.set('method', message.method);
      birklyUrl.searchParams.set('api_key', API_KEY);
      
      const options = {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-MCP-API-Key': API_KEY
        }
      };
      
      const protocol = birklyUrl.protocol === 'https:' ? https : http;
      
      const req = protocol.request(birklyUrl, options, (res) => {
        let data = '';
        res.on('data', (chunk) => { data += chunk; });
        res.on('end', () => {
          try {
            const response = JSON.parse(data);
            response.id = message.id;
            process.stdout.write(JSON.stringify(response) + '\n');
          } catch (e) {
            process.stdout.write(JSON.stringify({
              jsonrpc: '2.0',
              id: message.id,
              error: { code: -32700, message: 'Parse error' }
            }) + '\n');
          }
        });
      });
      
      req.on('error', (err) => {
        process.stdout.write(JSON.stringify({
          jsonrpc: '2.0',
          id: message.id,
          error: { code: -32603, message: err.message }
        }) + '\n');
      });
      
      req.write(JSON.stringify({
        method: message.method,
        params: message.params,
        id: message.id
      }));
      req.end();
      
    } catch (e) {
      // Ignore parse errors
    }
  }
});
```

Then configure Claude Desktop:

```json
{
  "mcpServers": {
    "birkly": {
      "command": "node",
      "args": ["/path/to/mcp-bridge.js"],
      "env": {
        "HARUCMS_URL": "http://localhost:8080",
        "HARUCMS_API_KEY": "YOUR_MCP_API_KEY_FROM_HARUCMS"
      }
    }
  }
}
```

### Step 5: Restart Claude Desktop

After editing the config file, restart Claude Desktop completely. The MCP server should connect automatically.

### Step 6: Verify Connection

1. Open Claude Desktop
2. Start a new conversation
3. Ask Claude: "What tools do you have access to?"
4. Claude should list the Birkly tools:
   - `list_collections`
   - `get_collection`
   - `list_entries`
   - `create_entry`
   - `update_entry`
   - `get_system_context`

## Using ChatGPT Instead

If you want to use ChatGPT (not Claude), you have two options:

### Option 1: Use OpenAI Provider (Recommended)

1. Go to **Settings > AI Connections** in Birkly
2. Add a new connection
3. Select **"OpenAI"** as provider
4. Enter your OpenAI API key
5. Use the floating AI button in the admin panel to chat

### Option 2: Use ChatGPT with Custom Integration

ChatGPT doesn't support MCP, but you could:
- Use the OpenAI API directly
- Create a custom integration using webhooks
- Use the internal chat interface in Birkly

## Troubleshooting

### MCP Server Not Connecting

1. **Check the API Key**: Make sure you copied the correct MCP API key from Birkly
2. **Check the URL**: Ensure `HARUCMS_URL` points to your Birkly installation
3. **Check Network**: Make sure Claude Desktop can reach your Birkly server
4. **Check Logs**: Look at `logs/php_errors.log` in Birkly for errors

### Claude Can't See Tools

1. Restart Claude Desktop completely
2. Check the config file syntax (must be valid JSON)
3. Verify the MCP bridge script is executable
4. Check that the API key is correct

### Permission Errors

1. Make sure the MCP connection in Birkly has the right role/permissions
2. Check that the AI user has access to the collections you're trying to use

## Available MCP Tools

Once connected, Claude can use these tools:

- **list_collections**: List all content collections
- **get_collection**: Get details of a specific collection
- **list_entries**: List entries in a collection
- **create_entry**: Create a new entry (requires approval if enabled)
- **update_entry**: Update an existing entry (requires approval if enabled)
- **get_system_context**: Get full system context including schemas and documentation

## Example Usage

Once connected, you can ask Claude:

- "List all my content collections"
- "Show me the entries in the 'blog' collection"
- "Create a new blog post about AI integration"
- "Update the entry with ID '123' in the 'pages' collection"

All create/update actions will go through the approval queue if you enabled "Require approval" when creating the MCP connection.

