#!/usr/bin/env node
/**
 * MCP Bridge for Birkly
 * Connects Claude Desktop (stdio MCP) to Birkly (HTTP MCP)
 *
 * Environment variables (required unless noted):
 *   BIRKLY_URL          — Birkly base URL (e.g. http://localhost:8080)
 *   BIRKLY_MCP_API_KEY  — Per-connection MCP API key from Settings → MCP Connections
 *
 * Legacy aliases (still supported): HARUCMS_URL, HARUCMS_API_KEY
 *
 * Usage:
 *   1. Make this file executable: chmod +x mcp-bridge.js
 *   2. Set BIRKLY_URL and BIRKLY_MCP_API_KEY (see above)
 *   3. Configure in Claude Desktop claude_desktop_config.json (stdio command + env)
 */

const http = require('http');
const https = require('https');
const { URL } = require('url');

const BIRKLY_URL = process.env.BIRKLY_URL || process.env.HARUCMS_URL || 'http://localhost:8080';
const API_KEY = process.env.BIRKLY_MCP_API_KEY || process.env.HARUCMS_API_KEY || '';

if (!API_KEY) {
  console.error('Error: BIRKLY_MCP_API_KEY (or legacy HARUCMS_API_KEY) environment variable is required');
  process.exit(1);
}

process.stdin.setEncoding('utf8');
process.stdout.setEncoding('utf8');

function writeMessage(obj) {
  process.stdout.write(JSON.stringify(obj) + '\n');
}

function handleLocal(message) {
  const method = message.method;

  if (method === 'initialize') {
    writeMessage({
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
    });
    return true;
  }

  if (method === 'ping') {
    writeMessage({
      jsonrpc: '2.0',
      id: message.id,
      result: {}
    });
    return true;
  }

  // Client notification after initialize handshake — no response
  if (method === 'notifications/initialized') {
    return true;
  }

  // Other MCP notifications — accept silently
  if (typeof method === 'string' && method.startsWith('notifications/')) {
    return true;
  }

  return false;
}

function proxyToBirkly(message) {
  const birklyUrl = new URL('/api/mcp_server.php', BIRKLY_URL);
  birklyUrl.searchParams.set('method', message.method);

  const urlObj = new URL(birklyUrl.toString());
  const options = {
    hostname: urlObj.hostname,
    port: urlObj.port || (urlObj.protocol === 'https:' ? 443 : 80),
    path: urlObj.pathname + urlObj.search,
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-MCP-API-Key': API_KEY
    }
  };

  const protocol = urlObj.protocol === 'https:' ? https : http;

  const req = protocol.request(options, (res) => {
    let data = '';
    res.on('data', (chunk) => { data += chunk; });
    res.on('end', () => {
      try {
        const response = JSON.parse(data);
        if (message.id !== undefined) {
          response.id = message.id;
        }
        writeMessage(response);
      } catch (e) {
        writeMessage({
          jsonrpc: '2.0',
          id: message.id,
          error: { code: -32700, message: 'Parse error: ' + e.message }
        });
      }
    });
  });

  req.on('error', (err) => {
    writeMessage({
      jsonrpc: '2.0',
      id: message.id,
      error: { code: -32603, message: 'Connection error: ' + err.message }
    });
  });

  req.write(JSON.stringify({
    method: message.method,
    params: message.params || {},
    id: message.id
  }));
  req.end();
}

process.stdin.on('data', async (chunk) => {
  const lines = chunk.toString().split('\n').filter(l => l.trim());

  for (const line of lines) {
    try {
      const message = JSON.parse(line);

      if (handleLocal(message)) {
        continue;
      }

      proxyToBirkly(message);

    } catch (e) {
      if (line.trim() && !line.startsWith('{')) {
        continue;
      }
      console.error('Error processing message:', e.message);
    }
  }
});

process.stdin.on('error', (err) => {
  console.error('Stdin error:', err);
});

process.stdout.on('error', (err) => {
  console.error('Stdout error:', err);
});

process.stdin.resume();
