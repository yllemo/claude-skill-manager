<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_common.php';

$isAuthed = skill_is_authed();

$filePath = validate_file_param((string)($_GET['file'] ?? ''));
if (!$filePath) {
    header('Location: ../');
    exit;
}

$filename = basename($filePath);
$entries  = read_zip_files($filePath);

if (empty($entries)) {
    header('Location: ../');
    exit;
}

// Find a good default file to show (prefer SKILL.md)
$defaultEntry = '';
foreach (array_keys($entries) as $n) {
    if (preg_match('/(?:^|\/)SKILL\.md$/i', $n)) { $defaultEntry = $n; break; }
}
if (!$defaultEntry) {
    foreach (array_keys($entries) as $n) {
        if ($entries[$n]['type'] === 'text') { $defaultEntry = $n; break; }
    }
}
if (!$defaultEntry) $defaultEntry = array_key_first($entries);

// Count files and total size
$numFiles  = count($entries);
$totalSize = array_sum(array_column($entries, 'size'));

// Skill metadata (from SKILL.md frontmatter)
$skillMeta = get_skill_meta($filePath);
$title     = $skillMeta['title'] ?: pathinfo($filename, PATHINFO_FILENAME);
$tags      = !empty($skillMeta['tags']) ? array_map('trim', explode(',', $skillMeta['tags'])) : [];
$loginBack = 'view/?file=' . rawurlencode($filename);
?>
<!DOCTYPE html>
<html lang="sv" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php favicon_link('../'); ?>
<title><?= h($title) ?> — <?= h(APP_NAME) ?></title>
<?php theme_script(); ?>
<?php common_css(); ?>
<style>
html,body{height:100%;overflow:hidden}
.workspace{flex:1;display:flex;overflow:hidden}

/* SIDEBAR */
.sidebar{width:var(--sidebar-w);flex-shrink:0;background:var(--bg-nav);border-right:1px solid var(--border-l);display:flex;flex-direction:column;overflow:hidden}
.sb-hdr{padding:12px 12px 10px;border-bottom:1px solid var(--border-l);flex-shrink:0}
.sb-title{font-size:.82rem;font-weight:700;color:var(--accent);margin-bottom:3px;word-break:break-word}
.sb-meta{font-size:.7rem;color:var(--text-2);margin-bottom:8px}
.sb-actions{display:flex;gap:4px;flex-wrap:wrap}
.tree-wrap{flex:1;overflow-y:auto;padding:5px 0}

/* TREE */
.tree-item{display:flex;align-items:center;gap:5px;padding:4px 10px;cursor:pointer;font-size:.79rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:background .12s;user-select:none}
.tree-item:hover{background:rgba(0,119,188,.07)}
.tree-item.active{background:rgba(0,119,188,.14);color:var(--accent);font-weight:600}
.tree-item.folder{color:var(--darkBlue);font-weight:600;cursor:default;font-size:.78rem}
[data-theme="dark"] .tree-item.folder{color:#7ec8f0}
.tree-item .ti{font-size:.8rem;flex-shrink:0}
.tree-indent{display:inline-block;flex-shrink:0}

/* CONTENT */
.content-main{flex:1;display:flex;flex-direction:column;overflow:hidden}
.ctoolbar{height:var(--toolbar-h);display:flex;align-items:center;padding:0 14px;gap:7px;border-bottom:1px solid var(--border-l);background:var(--bg);flex-shrink:0;overflow-x:auto}
.breadcrumb{flex:1;font-size:.76rem;color:var(--text-2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.breadcrumb strong{color:var(--text)}
.view-seg{display:flex;gap:2px;flex-shrink:0}
.vseg{padding:3px 10px;border-radius:var(--r);font:inherit;font-size:.74rem;cursor:pointer;border:1px solid var(--border-l);background:var(--bg);color:var(--text-2);transition:all .14s}
.vseg.on{background:var(--accent);color:#fff;border-color:var(--accent)}
.cbody{flex:1;overflow-y:auto;padding:22px 26px}

/* RAW / IMAGE / BINARY views */
.raw-view{font-family:'Consolas','Monaco',monospace;font-size:.82rem;white-space:pre-wrap;word-break:break-all;background:var(--bg-nav);border:1px solid var(--border-l);border-radius:var(--r-lg);padding:14px 16px;color:var(--text);line-height:1.6;max-width:880px}
.img-preview{max-width:100%;max-height:480px;border-radius:var(--r-lg);border:1px solid var(--border-l);box-shadow:var(--shadow)}
.file-info-box{display:inline-flex;flex-direction:column;gap:6px;background:var(--bg-nav);border:1px solid var(--border-l);border-radius:var(--r-lg);padding:16px 20px;font-size:.86rem}
.fib-icon{font-size:2.4rem;margin-bottom:3px}
.fib-row{display:flex;gap:9px}
.fib-k{font-weight:700;color:var(--accent);min-width:76px}
.fib-v{color:var(--text-2)}

/* SKILL META in sidebar */
.sb-section{padding:10px 12px;border-bottom:1px solid var(--border-l);font-size:.75rem}
.sb-section-lbl{font-size:.65rem;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
.sb-kv{display:flex;flex-direction:column;gap:1px;margin-bottom:5px}
.sb-k{font-size:.67rem;font-weight:700;color:var(--text-2)}
.sb-v{font-size:.76rem;color:var(--text);word-break:break-word}
.tag{display:inline-block;background:var(--bg-info);border:1px solid var(--border-l);border-radius:3px;padding:1px 5px;font-size:.66rem;color:var(--text-2);margin:1px}

/* En knapp med dropdown för nedladdning */
.hdr-split-dl{position:relative;display:inline-block}
.hdr-split-dl>summary{list-style:none;cursor:pointer}
.hdr-split-dl>summary::-webkit-details-marker{display:none}
.hdr-split-btn{margin:0!important}
.hdr-split-dl[open]>.hdr-split-btn{background:rgba(255,255,255,.26)!important}
.hdr-split-menu{
  position:absolute;right:0;top:calc(100% + 4px);z-index:200;
  min-width:10rem;background:var(--bg);border:1px solid var(--border-l);
  border-radius:var(--r);box-shadow:var(--shadow);padding:5px 0;
}
.hdr-split-opt{
  display:block;width:100%;text-align:left;padding:8px 14px;text-decoration:none;
  font:inherit;font-size:.82rem;color:var(--text);
}
.hdr-split-opt:hover{background:var(--bg-nav);color:var(--accent)}

@media(max-width:700px){.sidebar{display:none}}
</style>
</head>
<body>

<header class="header">
  <a href="../" style="display:flex;align-items:center;gap:10px;text-decoration:none">
    <div class="logo-mark">📘</div>
    <div class="logo-text"><?= h(APP_NAME) ?><span class="logo-sub">Visa skill</span></div>
  </a>
  <div class="hdr-sep"></div>
  <div class="hdr-title"><?= h($title) ?></div>
  <div class="hdr-actions">
    <a href="../" class="btn btn-white btn-sm">← Tillbaka</a>
    <?php if ($isAuthed): ?>
    <a href="../edit/?file=<?= urlencode($filename) ?>" class="btn btn-white btn-sm">✏️ Redigera</a>
    <?php else: ?>
    <a href="../login.php?back=<?= urlencode($loginBack) ?>" class="btn btn-white btn-sm">🔐 Logga in</a>
    <?php endif; ?>
    <details class="hdr-split-dl" id="hdr-split-dl">
      <summary class="btn btn-white btn-sm hdr-split-btn" aria-label="Ladda ner">⬇ Ladda ner ▾</summary>
      <div class="hdr-split-menu" role="menu">
        <a class="hdr-split-opt" href="../download.php?file=<?= urlencode($filename) ?>&amp;ext=skill" role="menuitem">Som .skill</a>
        <a class="hdr-split-opt" href="../download.php?file=<?= urlencode($filename) ?>&amp;ext=zip" role="menuitem">Som .zip</a>
      </div>
    </details>
    <button class="theme-btn" onclick="toggleTheme()" title="Växla tema">🌓</button>
  </div>
</header>

<div class="workspace">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sb-hdr">
      <div class="sb-title"><?= h($title) ?></div>
      <div class="sb-meta"><?= $numFiles ?> filer · <?= h(fmt_size($totalSize)) ?></div>
      <div class="sb-actions">
        <?php if ($isAuthed): ?>
        <a href="../edit/?file=<?= urlencode($filename) ?>" class="btn btn-xs btn-teal">✏️ Redigera</a>
        <?php else: ?>
        <a href="../login.php?back=<?= urlencode($loginBack) ?>" class="btn btn-xs btn-teal">🔐 Logga in</a>
        <?php endif; ?>
        <a href="../" class="btn btn-xs btn-secondary">← Start</a>
      </div>
    </div>

    <?php if (!empty($skillMeta['description'])): ?>
    <div class="sb-section">
      <div class="sb-section-lbl">Beskrivning</div>
      <div style="font-size:.76rem;color:var(--text-2);line-height:1.5"><?= h($skillMeta['description']) ?></div>
    </div>
    <?php endif; ?>

    <?php
    $metaDisplay = array_filter($skillMeta, fn($v, $k) => $v !== '' && !in_array($k, ['title','description','tags']), ARRAY_FILTER_USE_BOTH);
    if ($metaDisplay): ?>
    <div class="sb-section">
      <div class="sb-section-lbl">Metadata</div>
      <?php foreach ($metaDisplay as $k => $v): ?>
      <div class="sb-kv">
        <span class="sb-k"><?= h($k) ?></span>
        <span class="sb-v"><?= h($v) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($tags): ?>
    <div class="sb-section">
      <div class="sb-section-lbl">Taggar</div>
      <?php foreach ($tags as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- FILE TREE -->
    <div class="tree-wrap" id="tree-wrap"></div>
  </div>

  <!-- CONTENT -->
  <div class="content-main">
    <div class="ctoolbar">
      <div class="breadcrumb" id="breadcrumb">Välj en fil till vänster</div>
      <div class="view-seg">
        <button class="vseg on" id="vseg-r" onclick="setView('rendered')">Renderad</button>
        <button class="vseg" id="vseg-w" onclick="setView('raw')">Raw</button>
      </div>
      <button class="btn btn-xs btn-secondary" onclick="copyContent()">📋 Kopiera</button>
    </div>
    <div class="cbody" id="cbody">
      <p style="color:var(--text-2);font-size:.85rem">Välj en fil i trädet till vänster.</p>
    </div>
  </div>

</div>

<footer><?= h(APP_NAME) ?> · <?= h($filename) ?> · <?= h(fmt_size((int)filesize($filePath))) ?></footer>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script>
marked.setOptions({ breaks: true, gfm: true });
mermaid.initialize({ startOnLoad: false, theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default' });

var _origToggle = window.toggleTheme;
window.toggleTheme = function() {
  _origToggle();
  mermaid.initialize({ startOnLoad: false, theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default' });
  if (curPath) renderFile(curPath, curView);
};

var FILES = <?= json_encode($entries, JSON_UNESCAPED_UNICODE) ?>;
var curPath = '';
var curView = 'rendered';

// ── TREE ──────────────────────────────────────────────
function buildTree(active) {
  var wrap = document.getElementById('tree-wrap');
  wrap.innerHTML = '';
  var tree = {};
  for (var path in FILES) {
    var parts = path.split('/');
    var node = tree;
    for (var i = 0; i < parts.length - 1; i++) {
      if (!node[parts[i]]) node[parts[i]] = {};
      node = node[parts[i]];
    }
    node['__f__' + parts[parts.length - 1]] = path;
  }
  renderNode(tree, 0, wrap, active);
}

function renderNode(node, depth, wrap, active) {
  var keys = Object.keys(node).sort();
  var folders = keys.filter(function(k) { return !k.startsWith('__f__'); });
  var files   = keys.filter(function(k) { return  k.startsWith('__f__'); });
  folders.forEach(function(k) {
    var el = document.createElement('div');
    el.className = 'tree-item folder';
    el.innerHTML = '<span class="tree-indent" style="width:' + (depth*13) + 'px"></span><span class="ti">📁</span><span>' + esc(k) + '/</span>';
    wrap.appendChild(el);
    renderNode(node[k], depth + 1, wrap, active);
  });
  files.forEach(function(k) {
    var fp = node[k];
    var fname = k.replace('__f__', '');
    var el = document.createElement('div');
    el.className = 'tree-item' + (fp === active ? ' active' : '');
    el.innerHTML = '<span class="tree-indent" style="width:' + (depth*13) + 'px"></span><span class="ti">' + fIcon(fname) + '</span><span style="overflow:hidden;text-overflow:ellipsis">' + esc(fname) + '</span>';
    el.onclick = function() { selectFile(fp); };
    wrap.appendChild(el);
  });
}

function fIcon(name) {
  var ext = name.split('.').pop().toLowerCase();
  var m = {md:'📄',txt:'📝',json:'📋',yml:'⚙️',yaml:'⚙️',js:'📜',ts:'📜',jsx:'⚛️',tsx:'⚛️',py:'🐍',sh:'🖥️',bash:'🖥️',css:'🎨',html:'🌐',xml:'📰',csv:'📊',png:'🖼️',jpg:'🖼️',jpeg:'🖼️',gif:'🖼️',svg:'🖼️',webp:'🖼️',pdf:'📕'};
  return m[ext] || '📎';
}

// ── SELECT & RENDER ───────────────────────────────────
function selectFile(path) {
  curPath = path;
  var parts = path.split('/');
  document.getElementById('breadcrumb').innerHTML = parts.map(function(p, i) {
    return i === parts.length - 1 ? '<strong>' + esc(p) + '</strong>' : esc(p) + ' / ';
  }).join('');
  buildTree(path);
  renderFile(path, curView);
}

function renderFile(path, view) {
  var file = FILES[path];
  if (!file) return;
  var body = document.getElementById('cbody');

  if (file.type === 'image') {
    body.innerHTML = '<img src="' + file.content + '" class="img-preview" alt="' + esc(path.split('/').pop()) + '"><p style="margin-top:8px;font-size:.78rem;color:var(--text-2)">' + esc(path) + ' · ' + fmtSz(file.size) + '</p>';
    return;
  }

  if (file.type === 'binary') {
    body.innerHTML = '<div class="file-info-box"><div class="fib-icon">' + fIcon(path.split('/').pop()) + '</div><div class="fib-row"><span class="fib-k">Fil</span><span class="fib-v">' + esc(path.split('/').pop()) + '</span></div><div class="fib-row"><span class="fib-k">Sökväg</span><span class="fib-v">' + esc(path) + '</span></div><div class="fib-row"><span class="fib-k">Storlek</span><span class="fib-v">' + fmtSz(file.size) + '</span></div><div class="fib-row"><span class="fib-k">Typ</span><span class="fib-v">Binärfil</span></div></div>';
    return;
  }

  if (view === 'raw') {
    body.innerHTML = '<div class="raw-view">' + esc(file.content) + '</div>';
    return;
  }

  // Rendered (markdown or plain text)
  var ext = path.split('.').pop().toLowerCase();
  if (ext === 'md' || ext === 'txt') {
    var parsed = parseFM(file.content);
    var html = '';
    if (parsed.fm && Object.keys(parsed.fm).length) {
      html += '<div class="fm-box"><div class="fm-lbl">📋 Frontmatter / Metadata</div>';
      for (var k in parsed.fm) {
        html += '<div class="fm-row"><span class="fm-k">' + esc(k) + '</span><span class="fm-v">' + esc(String(parsed.fm[k])) + '</span></div>';
      }
      html += '</div>';
    }
    html += '<div class="md">' + marked.parse(parsed.body) + '</div>';
    body.innerHTML = html;
    processMermaid(body);
  } else {
    // Non-markdown text: show as raw with syntax hint
    body.innerHTML = '<div class="raw-view">' + esc(file.content) + '</div>';
  }
}

function processMermaid(container) {
  var blocks = container.querySelectorAll('pre code.language-mermaid');
  if (!blocks.length) return;
  blocks.forEach(function(el) {
    var code = el.textContent.trim();
    var wrap = document.createElement('div');
    wrap.style.cssText = 'background:var(--bg-nav);border:1px solid var(--border-l);border-radius:var(--r-lg);padding:16px;overflow-x:auto;margin:9px 0;text-align:center';
    var div = document.createElement('div');
    div.className = 'mermaid';
    div.textContent = code;
    wrap.appendChild(div);
    el.parentNode.replaceWith(wrap);
  });
  mermaid.run({ nodes: container.querySelectorAll('.mermaid') }).catch(function(e) {
    container.querySelectorAll('.mermaid:not([data-processed="true"])').forEach(function(m) {
      m.innerHTML = '<span style="color:var(--red);font-size:.8rem">⚠ Mermaid-fel: ' + esc(String(e.message || e)) + '</span>';
    });
  });
}

function setView(v) {
  curView = v;
  document.getElementById('vseg-r').classList.toggle('on', v === 'rendered');
  document.getElementById('vseg-w').classList.toggle('on', v === 'raw');
  if (curPath) renderFile(curPath, v);
}

function copyContent() {
  if (!curPath || !FILES[curPath]) return;
  var text = FILES[curPath].content || '';
  navigator.clipboard.writeText(text).then(function() {
    var btn = event.target;
    var orig = btn.textContent;
    btn.textContent = '✓ Kopierat';
    setTimeout(function() { btn.textContent = orig; }, 1500);
  });
}

// ── FRONTMATTER PARSER ────────────────────────────────
function parseFM(text) {
  var m = text.match(/^---\s*\n([\s\S]*?)\n---\s*\n?([\s\S]*)/);
  if (!m) return { fm: {}, body: text };
  var fm = {};
  m[1].split('\n').forEach(function(line) {
    var kv = line.match(/^(\w+):\s*(.*)/);
    if (kv) fm[kv[1]] = kv[2].replace(/^['"]|['"]$/g, '');
  });
  return { fm: fm, body: m[2] };
}

// ── UTILS ─────────────────────────────────────────────
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtSz(b) {
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
  return (b/1048576).toFixed(1) + ' MB';
}

// Init
buildTree('');
selectFile(<?= json_encode($defaultEntry) ?>);
</script>
<script>
(function(){
  var wrap = document.getElementById('hdr-split-dl');
  if (!wrap) return;
  document.addEventListener('click', function(e) {
    if (e.target.closest('#hdr-split-dl')) return;
    wrap.open = false;
  });
})();
</script>
</body>
</html>
