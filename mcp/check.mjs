// Run: node mcp/check.mjs http://127.0.0.1:8000/mcp/index.php
import assert from 'node:assert/strict';

const endpoint = process.argv[2];
if (!endpoint) throw new Error('Usage: node mcp/check.mjs <MCP endpoint URL>');
const version = '2026-07-28';
let id = 0;
let passed = 0;
function request(method, params = {}) {
  return {jsonrpc: '2.0', id: ++id, method, params: {...params, _meta: {
    'io.modelcontextprotocol/protocolVersion': version,
    'io.modelcontextprotocol/clientCapabilities': {},
    'io.modelcontextprotocol/clientInfo': {name: 'protocol-check', version: '1.0.0'},
  }}};
}
async function send(payload, overrides = {}, method = 'POST', raw) {
  const headers = {'Content-Type': 'application/json', Accept: 'application/json, text/event-stream',
    'MCP-Protocol-Version': payload?.params?._meta?.['io.modelcontextprotocol/protocolVersion'] || version,
    'Mcp-Method': payload?.method || 'ping',
    ...(payload?.params?.name ? {'Mcp-Name': payload.params.name} : {}), ...overrides};
  const res = await fetch(endpoint, {method, headers,
    body: method === 'POST' ? (raw ?? JSON.stringify(payload)) : undefined,
    signal: AbortSignal.timeout(10000)});
  const text = await res.text();
  return {status: res.status, body: text ? JSON.parse(text) : null, headers: res.headers};
}
async function check(label, run) {
  await run();
  passed++;
  console.log('OK ' + label);
}
await check('discovery and result metadata', async () => {
  const p = request('server/discover');
  const r = await send(p);
  assert.equal(r.status, 200);
  assert.equal(r.body.id, p.id);
  assert.deepEqual(r.body.result.supportedVersions, [version]);
  assert.equal(r.body.result.resultType, 'complete');
  assert.equal(r.body.result.cacheScope, 'private');
  assert.equal(r.body.result._meta['io.modelcontextprotocol/serverInfo'].name, 'skill-manager-mcp');
});
await check('tools have valid object schemas', async () => {
  const r = await send(request('tools/list'));
  assert.equal(r.status, 200);
  assert.equal(r.body.result.tools.length, 3);
  assert.deepEqual(r.body.result.tools[0].inputSchema.properties, {});
  assert.equal(r.body.result.ttlMs, 0);
});
await check('ping', async () => {
  const r = await send(request('ping'));
  assert.equal(r.status, 200);
  assert.equal(r.body.result.resultType, 'complete');
  assert.equal(r.body.result.pong, undefined);
});
for (const [label, headers] of [
  ['missing version', {'MCP-Protocol-Version': ''}],
  ['missing method', {'Mcp-Method': ''}],
  ['mismatched method', {'Mcp-Method': 'tools/list'}],
]) await check(label, async () => {
  const r = await send(request('ping'), headers);
  assert.equal(r.status, 400);
  assert.equal(r.body.error.code, -32020);
});
await check('unsupported version includes retry information', async () => {
  const p = request('ping');
  p.params._meta['io.modelcontextprotocol/protocolVersion'] = '1900-01-01';
  const r = await send(p);
  assert.equal(r.status, 400);
  assert.equal(r.body.error.code, -32022);
  assert.deepEqual(r.body.error.data.supported, [version]);
});
await check('missing client capabilities', async () => {
  const p = request('ping');
  delete p.params._meta['io.modelcontextprotocol/clientCapabilities'];
  const r = await send(p);
  assert.equal(r.body.error.code, -32602);
});
await check('unknown method', async () => {
  const r = await send(request('unknown/method'));
  assert.equal(r.status, 404);
  assert.equal(r.body.error.code, -32601);
});
await check('tool argument validation', async () => {
  for (const args of [{query: 42}, [], {query: 'hello', extra: true}, {query: ''}]) {
    const r = await send(request('tools/call', {name: 'search_skills', arguments: args}));
    assert.equal(r.status, 200);
    assert.equal(r.body.result.isError, true);
  }
});
await check('name mismatch and base64 decoding', async () => {
  const p = request('tools/call', {name: 'read_skill', arguments: {file: 'missing.skill'}});
  const invalid = await send(p, {'Mcp-Name': 'search_skills'});
  assert.equal(invalid.body.error.code, -32020);
  const valid = await send(p, {'Mcp-Name': '=?base64?' + Buffer.from('read_skill').toString('base64') + '?='});
  assert.equal(valid.status, 200);
  assert.equal(valid.body.result.isError, true);
});
await check('invalid envelopes and JSON', async () => {
  for (const p of [[request('ping')], null, {...request('ping'), id: null}, {...request('ping'), params: []}]) {
    const r = await send(p);
    assert.equal(r.status, 400);
    assert.equal(r.body.error.code, -32600);
  }
  assert.equal((await send(request('ping'), {}, 'POST', '{')).body.error.code, -32700);
});
await check('notification receives empty 202', async () => {
  const r = await send({jsonrpc: '2.0', method: 'notifications/test'});
  assert.equal(r.status, 202);
  assert.equal(r.body, null);
});
await check('transport and origin validation', async () => {
  assert.equal((await send(request('ping'), {}, 'GET')).status, 405);
  assert.equal((await send(request('ping'), {}, 'DELETE')).status, 405);
  assert.equal((await send(request('ping'), {Accept: 'application/json'})).status, 406);
  assert.equal((await send(request('ping'), {'Content-Type': 'text/plain'})).status, 415);
  assert.equal((await send(request('ping'), {Origin: 'https://untrusted.example'})).status, 403);
  assert.equal((await send(request('ping'), {Origin: new URL(endpoint).origin})).status, 200);
});
console.log(`${passed} protocol checks passed`);
