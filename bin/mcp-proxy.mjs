#!/usr/bin/env node
import { createInterface } from 'node:readline';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { appendFileSync } from 'node:fs';

const WP_URL = process.env.WP_URL?.replace(/\/+$/, '') || '';
const WP_USERNAME = process.env.WP_USERNAME || '';
const WP_APP_PASSWORD = process.env.WP_APP_PASSWORD || '';
const MCP_LOG_FILE = process.env.MCP_LOG_FILE || '';
const MCP_PROTOCOL_VERSION = process.env.MCP_PROTOCOL_VERSION || '';
const MCP_REST_PATH = '/mcp/wpbakery-uncode-mcp-server';

if (!WP_URL || !WP_USERNAME || !WP_APP_PASSWORD) {
  process.stderr.write('WP_URL, WP_USERNAME, and WP_APP_PASSWORD are required.\n');
  process.exit(1);
}

const parsedWpUrl = new URL(WP_URL);
// .dev is a real public TLD, so it is not treated as local. Self-signed
// certificates are only accepted for reserved local names, or when
// WP_INSECURE_TLS=1 is set explicitly.
const isLocalDev = /\.(test|local|localhost|invalid)$/.test(parsedWpUrl.hostname)
  || parsedWpUrl.hostname === 'localhost'
  || parsedWpUrl.hostname === '127.0.0.1';
const allowInsecureTls = isLocalDev || process.env.WP_INSECURE_TLS === '1';

if (parsedWpUrl.protocol === 'http:' && !isLocalDev) {
  process.stderr.write('Refusing to send credentials over plain http to a non-local host. Use https.\n');
  process.exit(1);
}

let sessionId = null;
let usePrettyPermalinks = null;

function log(message) {
  const line = `[${new Date().toISOString()}] ${message}`;
  process.stderr.write(`${line}\n`);
  if (MCP_LOG_FILE) {
    try {
      appendFileSync(MCP_LOG_FILE, `${line}\n`);
    } catch {}
  }
}

function doHttpRequest(options, payload) {
  return new Promise((resolve, reject) => {
    const isHttps = parsedWpUrl.protocol === 'https:';
    const doRequest = isHttps ? httpsRequest : httpRequest;

    if (isHttps && allowInsecureTls) {
      options.rejectUnauthorized = false;
    }

    const req = doRequest(options, (res) => {
      let body = '';
      res.on('data', (chunk) => { body += chunk; });
      res.on('end', () => resolve({ body, headers: res.headers, statusCode: res.statusCode }));
    });

    req.on('error', reject);
    req.setTimeout(30000, () => req.destroy(new Error('Request timeout')));
    req.write(payload);
    req.end();
  });
}

async function detectPermalinks() {
  try {
    const isHttps = parsedWpUrl.protocol === 'https:';
    const options = {
      hostname: parsedWpUrl.hostname,
      port: parsedWpUrl.port || (isHttps ? 443 : 80),
      path: '/wp-json/',
      method: 'HEAD',
      headers: { Accept: 'application/json' },
    };

    if (isHttps && allowInsecureTls) {
      options.rejectUnauthorized = false;
    }

    const { statusCode } = await doHttpRequest(options, '');
    return statusCode !== 404;
  } catch {
    return false;
  }
}

function getMcpPath() {
  if (usePrettyPermalinks) {
    return `/wp-json${MCP_REST_PATH}`;
  }

  return `/?rest_route=${encodeURIComponent(MCP_REST_PATH)}`;
}

async function sendToWordPress(message) {
  if (usePrettyPermalinks === null) {
    usePrettyPermalinks = await detectPermalinks();
    log(`Permalinks: ${usePrettyPermalinks ? 'pretty' : 'plain'}`);
  }

  const auth = Buffer.from(`${WP_USERNAME}:${WP_APP_PASSWORD}`).toString('base64');
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Authorization: `Basic ${auth}`,
  };

  if (sessionId) {
    headers['Mcp-Session-Id'] = sessionId;
  }

  const payload = JSON.stringify(message);
  const isHttps = parsedWpUrl.protocol === 'https:';
  const options = {
    hostname: parsedWpUrl.hostname,
    port: parsedWpUrl.port || (isHttps ? 443 : 80),
    path: getMcpPath(),
    method: 'POST',
    headers: {
      ...headers,
      'Content-Length': Buffer.byteLength(payload),
    },
  };

  return doHttpRequest(options, payload);
}

async function handleMessage(line) {
  let message;
  try {
    message = JSON.parse(line);
  } catch {
    process.stdout.write(`${JSON.stringify({ jsonrpc: '2.0', error: { code: -32700, message: 'Parse error' }, id: null })}\n`);
    return;
  }

  const method = message.method || '';

  try {
    const { body, headers, statusCode } = await sendToWordPress(message);

    if (method === 'initialize' && headers['mcp-session-id']) {
      sessionId = headers['mcp-session-id'];
    }

    let response;
    try {
      response = JSON.parse(body);
    } catch {
      response = {
        jsonrpc: '2.0',
        id: message.id ?? null,
        error: {
          code: -32000,
          message: `Invalid WordPress response (${statusCode})`,
        },
      };
    }

    if (method === 'initialize' && MCP_PROTOCOL_VERSION && response?.result) {
      response.result.protocolVersion = MCP_PROTOCOL_VERSION;
    }

    if (message.id !== undefined && message.id !== null) {
      process.stdout.write(`${JSON.stringify(response)}\n`);
    }
  } catch (error) {
    process.stdout.write(`${JSON.stringify({
      jsonrpc: '2.0',
      id: message.id ?? null,
      error: { code: -32000, message: String(error?.message || error) },
    })}\n`);
  }
}

const rl = createInterface({ input: process.stdin, crlfDelay: Infinity });
rl.on('line', (line) => {
  if (line && line.trim()) {
    handleMessage(line.trim());
  }
});

rl.on('close', () => {
  log('MCP proxy closed.');
});
