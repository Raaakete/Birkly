#!/usr/bin/env node
/**
 * MCP Bridge for Birkly
 * Connects Claude Desktop (stdio MCP) to Birkly (HTTP MCP)
 * 
 * Usage:
 *   1. Make this file executable: chmod +x mcp-bridge.js
 *   2. Set environment variables:
 *      - HARUCMS_URL: Your Birkly URL (e.g., http://localhost:8080)
 *      - HARUCMS_API_KEY: Your MCP API key from Birkly
 *   3. Configure in Claude Desktop config file
 */

const http = require('http');
const https = require('https');
const { URL } = require('url');

const HARUCMS_URL = process.env.HARUCMS_URL || 'http://localhost:8080';
const API_KEY = process.env.HARUCMS_API_KEY || '';

if (!API_KEY) {
  console.error('Error: HARUCMS_API_KEY environment variable is required');
  process.exit(1);
}

// Set up stdio for MCP protocol
process.stdin.setEncoding('utf8');
process.stdout.setEncoding('utf8');

// Handle incoming MCP messages from Claude Desktop
process.stdin.on('data', async (chunk) => {
  const lines = chunk.toString().split('\n').filter(l => l.trim());
  
  for (const line of lines) {
    try {
      const message = JSON.parse(line);
      
      // Handle initialize
      if (message.method === 'initialize') {
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
      
      // Forward other requests to Birkly HTTP endpoint
      const birklyUrl = new URL('/api/mcp_server.php', HARUCMS_URL);
      birklyUrl.searchParams.set('method', message.method);
      birklyUrl.searchParams.set('api_key', API_KEY);
      
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
            response.id = message.id;
            process.stdout.write(JSON.stringify(response) + '\n');
          } catch (e) {
            process.stdout.write(JSON.stringify({
              jsonrpc: '2.0',
              id: message.id,
              error: { code: -32700, message: 'Parse error: ' + e.message }
            }) + '\n');
          }
        });
      });
      
      req.on('error', (err) => {
        process.stdout.write(JSON.stringify({
          jsonrpc: '2.0',
          id: message.id,
          error: { code: -32603, message: 'Connection error: ' + err.message }
        }) + '\n');
      });
      
      req.write(JSON.stringify({
        method: message.method,
        params: message.params || {},
        id: message.id
      }));
      req.end();
      
    } catch (e) {
      // Ignore parse errors for non-JSON lines
      if (line.trim() && !line.startsWith('{')) {
        continue;
      }
      console.error('Error processing message:', e.message);
    }
  }
});

// Handle errors
process.stdin.on('error', (err) => {
  console.error('Stdin error:', err);
});

process.stdout.on('error', (err) => {
  console.error('Stdout error:', err);
});

// Keep process alive
process.stdin.resume();

