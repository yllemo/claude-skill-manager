<?php
declare(strict_types=1);
require_once __DIR__ . '/protocol.php';
?>
<!doctype html>
<html lang="sv">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>MCP Test</title>
  <style>
    body{font-family:Arial,sans-serif;margin:20px;color:#222;max-width:1100px}
    .card{border:1px solid #ddd;border-radius:8px;padding:14px;margin-bottom:14px}
    .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    input{padding:8px;min-width:220px;max-width:100%;box-sizing:border-box}
    button{padding:8px 12px;cursor:pointer}button:disabled{cursor:wait;opacity:.6}
    pre{background:#f7f7f7;border:1px solid #eee;border-radius:6px;padding:12px;overflow:auto;max-height:450px}
    .muted{color:#666;font-size:.9rem}
  </style>
</head>
<body>
  <h1>MCP Testpanel</h1>
  <p class="muted">Endpoint: <code id="endpoint"></code> · MCP <?= MCP_PROTOCOL_VERSION ?></p>
  <p>Varje anrop skickar protokollversion, klientmetadata och MCP-headers. Äldre versioner använder initialize. Din aktuella inloggning används.</p>
  <label for="protocol">Protokollversion</label>
  <select id="protocol"><?php foreach (MCP_SUPPORTED_VERSIONS as $version): ?>
    <option value="<?= $version ?>"><?= $version ?></option>
  <?php endforeach; ?></select>
  <div class="card row">
    <button type="button" data-method="initialize">initialize (äldre klient)</button>
    <button type="button" data-method="server/discover">server/discover</button>
    <button type="button" data-method="tools/list">tools/list</button>
    <button type="button" data-method="ping">ping</button>
    <button type="button" data-tool="list_skills">tools/call: list_skills</button>
    <button type="button" id="run-tests">Kör protokolltester</button>
  </div>
  <form class="card row" id="read-form">
    <label for="read-file">Skillfil</label><input id="read-file" placeholder="my-skill.skill" required>
    <button>tools/call: read_skill</button>
  </form>
  <form class="card row" id="search-form">
    <label for="search-query">Sökterm</label><input id="search-query" placeholder="sökterm…" required>
    <button>tools/call: search_skills</button>
  </form>
  <div class="card" aria-live="polite">
    <p id="status">Välj ett anrop eller kör protokolltesterna.</p>
    <h3>Request</h3><pre id="request"></pre>
    <h3>Response</h3><pre id="response"></pre>
    <h3>Testresultat</h3><pre id="test-results"></pre>
  </div>
<script>
const endpoint = new URL('index.php', location.href).href;
const modernVersion = <?= json_encode(MCP_PROTOCOL_VERSION) ?>;
let version = modernVersion;
document.getElementById('protocol').addEventListener('change', e => {version = e.target.value;});
let nextId = 1;
document.getElementById('endpoint').textContent = endpoint;
function request(method, params = {}) {
  if (method === 'initialize') return {jsonrpc:'2.0',id:nextId++,method,params:{
    protocolVersion:version,capabilities:{},clientInfo:{name:'skill-manager-test',version:'2.1.0'}
  }};
  if (version !== modernVersion) return {jsonrpc:'2.0',id:nextId++,method,params};
  return {jsonrpc:'2.0', id:nextId++, method, params:{...params, _meta:{
    'io.modelcontextprotocol/protocolVersion':version,
    'io.modelcontextprotocol/clientCapabilities':{},
    'io.modelcontextprotocol/clientInfo':{name:'skill-manager-test', version:'2.0.0'}
  }}};
}
async function send(payload, overrides = {}, httpMethod = 'POST') {
  const headers = {'Content-Type':'application/json', Accept:'application/json, text/event-stream',
    'MCP-Protocol-Version':payload.params?._meta?.['io.modelcontextprotocol/protocolVersion'] || version,
    'Mcp-Method':payload.method, ...overrides};
  if (version !== modernVersion || payload.method === 'initialize') delete headers['Mcp-Method'];
  if (payload.params?.name && version === modernVersion) headers['Mcp-Name'] = payload.params.name;
  document.getElementById('request').textContent = JSON.stringify({httpMethod, headers, body:payload},null,2);
  const res = await fetch(endpoint, {method:httpMethod, headers, credentials:'same-origin',
    body:httpMethod === 'POST' ? JSON.stringify(payload) : undefined, signal:AbortSignal.timeout(20000)});
  const raw = await res.text();
  let body;
  try {body = raw ? JSON.parse(raw) : null;} catch {body = raw;}
  const result = {status:res.status, contentType:res.headers.get('content-type'), body};
  document.getElementById('response').textContent = JSON.stringify(result,null,2);
  document.getElementById('status').textContent = 'HTTP ' + res.status;
  return result;
}
async function busy(work) {
  document.querySelectorAll('button').forEach(b => b.disabled = true);
  try {await work();} catch(e) {document.getElementById('status').textContent = 'Fel: ' + e.message;}
  finally {document.querySelectorAll('button').forEach(b => b.disabled = false);}
}
document.querySelectorAll('[data-method], [data-tool]').forEach(button => {
  button.addEventListener('click', () => busy(() => send(button.dataset.method
    ? request(button.dataset.method) : request('tools/call',{name:button.dataset.tool,arguments:{}}))));
});
document.getElementById('read-form').addEventListener('submit', e => {
  e.preventDefault();busy(() => send(request('tools/call',{name:'read_skill',arguments:{file:document.getElementById('read-file').value.trim()}})));
});
document.getElementById('search-form').addEventListener('submit', e => {
  e.preventDefault();busy(() => send(request('tools/call',{name:'search_skills',arguments:{query:document.getElementById('search-query').value.trim()}})));
});
document.getElementById('run-tests').addEventListener('click', () => busy(async () => {
  if (version !== modernVersion) {
    const lines=[];
    const init=await send(request('initialize'));
    lines.push(init.body?.result?.protocolVersion === version ? 'OK — initialize' : 'FEL — initialize');
    const ready=await send({jsonrpc:'2.0',method:'notifications/initialized'});
    lines.push(ready.status===202 && ready.body===null ? 'OK — initialized' : 'FEL — initialized');
    const tools=await send(request('tools/list'));
    lines.push(tools.body?.result?.tools?.length===3 ? 'OK — tools/list' : 'FEL — tools/list');
    const ping=await send(request('ping'));
    lines.push(ping.status===200 && JSON.stringify(ping.body?.result)==='{}' ? 'OK — ping' : 'FEL — ping');
    document.getElementById('test-results').textContent=lines.join('\n');
    document.getElementById('status').textContent=lines.filter(l=>l.startsWith('OK')).length + '/4 tester godkända';
    return;
  }
  const checks = [
    ['Discovery',request('server/discover'),{},'POST',r => r.status===200 && r.body?.result?.supportedVersions?.includes(version)],
    ['Verktyg och JSON-schema',request('tools/list'),{},'POST',r => r.status===200 && r.body?.result?.tools?.length===3 && !Array.isArray(r.body.result.tools[0].inputSchema.properties)],
    ['Ping',request('ping'),{},'POST',r => r.status===200 && r.body?.result?.resultType==='complete'],
    ['Fel header',request('ping'),{'Mcp-Method':'tools/list'},'POST',r => r.status===400 && r.body?.error?.code===-32020],
    ['Okänd version',request('ping'),{},'POST',r => r.status===400 && r.body?.error?.code===-32022 && r.body.error.data.supported.includes(version)],
    ['Okänd metod',request('unknown/method'),{},'POST',r => r.status===404 && r.body?.error?.code===-32601],
    ['Fel argumenttyp',request('tools/call',{name:'search_skills',arguments:{query:42}}),{},'POST',r => r.status===200 && r.body?.result?.isError===true],
    ['Batch avvisas',[request('ping')],{},'POST',r => r.status===400 && r.body?.error?.code===-32600],
    ['GET avvisas',request('ping'),{},'GET',r => r.status===405],
    ['Notification utan svar',{jsonrpc:'2.0',method:'notifications/test'}, {},'POST',r => r.status===202 && r.body===null]
  ];
  checks[4][1].params._meta['io.modelcontextprotocol/protocolVersion']='1900-01-01';
  const lines=[];
  for(const [label,payload,headers,method,check] of checks) {
    const r = await send(payload,headers,method);
    lines.push((check(r) ? 'OK' : 'FEL') + ' — ' + label + ' (HTTP ' + r.status + ')');
    document.getElementById('test-results').textContent=lines.join('\n');
  }
  document.getElementById('status').textContent=lines.filter(l=>l.startsWith('OK')).length + '/' + checks.length + ' tester godkända';
}));
</script>
</body>
</html>
