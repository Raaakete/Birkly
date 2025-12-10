# Quick Start: Connect Ollama to HaruCMS

## Step-by-Step Instructions

### Step 1: Verify Ollama is Running

1. Open a terminal/command prompt
2. Run this command:
   ```bash
   curl http://localhost:11434/api/tags
   ```
   
   **What you should see**: A list of models (or an empty list `{"models":[]}` if you haven't pulled any models yet)
   
   **If you get an error**: Ollama is not running. Start it by:
   - Opening the Ollama app, OR
   - Running `ollama serve` in terminal

### Step 2: Pull a Model (If You Haven't Already)

1. In terminal, run:
   ```bash
   ollama pull llama3
   ```
   
   This downloads the Llama 3 model. Wait for it to finish (may take a few minutes).
   
   **Alternative models you can use**:
   - `ollama pull mistral` (smaller, faster)
   - `ollama pull codellama` (good for code)
   - `ollama pull phi` (very small, very fast)

### Step 3: Open HaruCMS Admin Panel

1. Open your browser
2. Go to your HaruCMS admin panel (usually `http://localhost:8080/admin`)
3. Log in if needed

### Step 4: Go to AI Connections Settings

1. In the left sidebar, click **"Settings"**
2. At the top, you'll see tabs. Click the **"AI Connections"** tab
3. You should see a section called "Connected AI Services"

### Step 5: Add Ollama Connection

1. Click the **"+ Add Connection"** button
2. A modal window will open

### Step 6: Fill in the Connection Form

**Field by field instructions:**

1. **AI Provider** (dropdown):
   - Click the dropdown
   - Select **"Ollama (Local)"**

2. **Connection Name** (text field):
   - Type any name you want, e.g.: `My Local LLM` or `Ollama`
   - This is just for your reference

3. **API URL** (text field):
   - **You DO need this!**
   - **Default value**: `http://localhost:11434/api/chat`
   - **Leave it as is** if Ollama runs on your computer
   - **Only change it if**:
     - Ollama runs on a different computer → Use that computer's IP: `http://192.168.1.100:11434/api/chat`
     - Ollama runs on a different port → Change the port number
     - Ollama runs in Docker → Use `http://host.docker.internal:11434/api/chat`

4. **API Key** (password field):
   - **You DON'T need this!**
   - **Leave it empty** - Ollama doesn't use API keys

5. **Model** (dropdown):
   - Click the dropdown
   - Select the model you pulled (e.g., `llama3`, `mistral`, etc.)
   - **OR** if your model isn't listed, type the exact model name in the text field below

6. **Role** (dropdown):
   - Select **"Editor (Create & Edit)"** (recommended)
   - This controls what the AI can do

7. **Who can use this connection?** (dropdown):
   - Select **"Only me (Private)"** (recommended for personal use)
   - Or choose "All users" if you want others to use it

8. **Require approval for AI actions** (checkbox):
   - ✅ **Check it** if you want to review AI actions before they execute
   - ☐ **Uncheck it** if you want AI actions to execute immediately

### Step 7: Save the Connection

1. Click the **"Connect"** button (yellow button at bottom)
2. You should see a success message: "AI connection created successfully"
3. The modal will close
4. You should now see your Ollama connection in the list

### Step 8: Test It!

1. Look for the **yellow circular button** in the **bottom-right corner** of any admin page
2. Click it
3. A chat window should open
4. Type a message like: "Hello, can you help me?"
5. The AI should respond!

## Troubleshooting

### "Error: Connection failed" or "API error"

**Check 1**: Is Ollama running?
```bash
curl http://localhost:11434/api/tags
```
If this fails, start Ollama.

**Check 2**: Is the API URL correct?
- Default should be: `http://localhost:11434/api/chat`
- Make sure there are no typos
- Make sure you're using `http://` not `https://`

**Check 3**: Did you pull the model?
```bash
ollama list
```
This shows all models you have. Make sure the model name matches what you selected in HaruCMS.

### "Model not found"

1. Check what models you have:
   ```bash
   ollama list
   ```

2. In HaruCMS, make sure the model name matches exactly (case-sensitive)

3. If your model isn't in the dropdown, type it manually in the custom model field

### Chat window doesn't open

1. Make sure you're logged into the admin panel
2. Refresh the page (F5)
3. Check browser console for errors (F12 → Console tab)

### AI doesn't respond

1. Check if Ollama is processing:
   ```bash
   # In another terminal, watch Ollama logs
   ollama logs
   ```

2. Make sure the model is loaded (first request may take time)

3. Try a simpler model like `phi` or `gemma` if `llama3` is too slow

## Quick Reference

**Ollama Default URL**: `http://localhost:11434/api/chat`  
**No API Key Needed**: Leave it empty  
**Test Ollama**: `curl http://localhost:11434/api/tags`  
**List Models**: `ollama list`  
**Pull Model**: `ollama pull llama3`

## That's It!

You're done! The AI button in the bottom-right corner is now connected to your local Ollama instance. You can chat with it from any page in the admin panel.

