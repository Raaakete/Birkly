# Using Ollama with HaruCMS

## Quick Setup

Ollama is already fully supported in HaruCMS! You don't need MCP to use Ollama.

### Step 1: Install and Run Ollama

1. **Install Ollama**: Download from https://ollama.ai
2. **Start Ollama**: The service runs on `http://localhost:11434` by default
3. **Pull a model**: 
   ```bash
   ollama pull llama3
   # or
   ollama pull mistral
   # or
   ollama pull codellama
   ```

### Step 2: Add Ollama Connection in HaruCMS

1. Go to **Settings > AI Connections** in your HaruCMS admin panel
2. Click **"+ Add Connection"**
3. Select **"Ollama (Local)"** as the provider
4. Configure:
   - **Connection Name**: e.g., "My Local LLM"
   - **Model**: Select from dropdown (llama3, mistral, codellama, phi, gemma) or enter a custom model name
   - **API URL**: Leave as default (`http://localhost:11434/api/chat`) or change if Ollama runs on a different port
   - **Role**: Choose permissions (Editor recommended)
   - **Who can use**: Choose access level
   - **Require approval**: Enable if you want to review AI actions
5. Click **"Connect"**

**Note**: No API key is required for Ollama since it runs locally!

### Step 3: Use Ollama

1. Click the **yellow AI button** in the bottom-right corner of any admin page
2. Select your Ollama connection
3. Start chatting! The AI will have full access to your HaruCMS system context

## Available Models

You can use any Ollama model. Common ones include:

- **llama3** - Meta's Llama 3 (recommended)
- **mistral** - Mistral AI model
- **codellama** - Code-focused Llama variant
- **phi** - Microsoft's Phi model
- **gemma** - Google's Gemma model
- **qwen** - Alibaba's Qwen model
- **neural-chat** - Intel's Neural Chat

To use a custom model, just type its name in the model field when creating the connection.

## Why Not MCP?

**Ollama doesn't support MCP** because:
- Ollama is a local LLM server, not an MCP client
- MCP is a protocol for connecting AI assistants (like Claude Desktop) to external tools
- HaruCMS already provides all the functionality you need through direct integration

## What You Get

When using Ollama directly in HaruCMS, you get:

✅ **Full system context** - The AI knows about your collections, schemas, and documentation  
✅ **Content creation** - Can create and edit entries  
✅ **Approval workflow** - Optional approval system for AI actions  
✅ **Role-based permissions** - Control what the AI can do  
✅ **No external dependencies** - Everything runs locally  

This is actually **better** than MCP because:
- No need for a bridge/wrapper
- Direct integration is faster
- Full control over the AI's capabilities
- Works with any Ollama model

## Troubleshooting

### Ollama Not Responding

1. **Check if Ollama is running**:
   ```bash
   curl http://localhost:11434/api/tags
   ```
   Should return a list of available models.

2. **Check the API URL**: Make sure it matches your Ollama installation
   - Default: `http://localhost:11434/api/chat`
   - If using Docker: `http://host.docker.internal:11434/api/chat`

3. **Check firewall**: Make sure port 11434 is accessible

### Model Not Found

1. **Pull the model first**:
   ```bash
   ollama pull <model-name>
   ```

2. **List available models**:
   ```bash
   ollama list
   ```

3. **Use exact model name**: Make sure the model name in HaruCMS matches exactly what Ollama shows

### Slow Responses

- Use smaller models (phi, gemma) for faster responses
- Use larger models (llama3, mistral) for better quality
- Consider running Ollama on a machine with more RAM/GPU

## Advanced: Custom Ollama Setup

If Ollama runs on a different machine or port:

1. **Remote Ollama**: Change API URL to `http://your-server-ip:11434/api/chat`
2. **Custom Port**: Change API URL to `http://localhost:YOUR_PORT/api/chat`
3. **Docker**: Use `http://host.docker.internal:11434/api/chat` if HaruCMS is in Docker

## Comparison: Ollama Direct vs MCP

| Feature | Ollama Direct | MCP with Ollama |
|---------|---------------|-----------------|
| Setup Complexity | ⭐ Simple | ⭐⭐⭐ Complex (needs bridge) |
| Performance | ⭐⭐⭐ Fast | ⭐⭐ Slower (extra layer) |
| Features | ⭐⭐⭐ Full | ⭐⭐⭐ Full |
| Maintenance | ⭐⭐⭐ Easy | ⭐⭐ Needs bridge updates |
| **Recommendation** | ✅ **Use this!** | ❌ Not needed |

**Conclusion**: Just use Ollama directly in HaruCMS. It's simpler, faster, and gives you everything you need!

