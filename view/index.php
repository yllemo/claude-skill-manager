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
<html lang="<?= h(skill_lang_html_lang()) ?>" data-theme="light">
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

/* MOBILE RESPONSIVE FOR SIDEBAR */
@media(max-width: 768px) {
  .sidebar{
    position:fixed;
    top:54px;
    left:-280px;
    width:280px;
    height:calc(100vh - 54px);
    z-index:1000;
    transition:left .3s ease;
    display:flex!important;
  }
  .sidebar.mobile-hidden{
    left:-280px;
  }
  .sidebar:not(.mobile-hidden){
    left:0;
  }
  .sidebar-overlay{
    position:fixed;
    top:54px;
    left:0;
    right:0;
    bottom:0;
    background:rgba(0,0,0,.5);
    z-index:999;
    display:none;
  }
  .content-main{
    width:100%;
  }
  .ctoolbar{
    padding:0 10px;
  }
  .cbody{
    padding:16px;
  }
  .sidebar-toggle{
    display:flex!important;
  }
  .hdr-split-dl{
    display:none!important;
  }
}

/* SIDEBAR TOGGLE BUTTON */
.sidebar-toggle{
  display:none;
  align-items:center;
  gap:4px;
  padding:4px 8px;
  font-size:.75rem;
  border-radius:var(--r);
  background:rgba(255,255,255,.1);
  border:1px solid rgba(255,255,255,.28);
  color:#fff;
  cursor:pointer;
  transition:background .15s;
}
.sidebar-toggle:hover{
  background:rgba(255,255,255,.22);
}

/* Mermaid i innehåll — markera text i SVG; helskärm via knapp */
.mermaid-block-wrap{position:relative;transition:box-shadow .15s}
.mermaid-block-wrap:hover{box-shadow:0 0 0 2px var(--accent)}
.mermaid-block-wrap svg,.mermaid-block-wrap svg text,.mermaid-block-wrap svg tspan{
  user-select:text!important;-webkit-user-select:text!important;
  shape-rendering:geometricPrecision;text-rendering:geometricPrecision;
}
.mermaid-fs-open-btn{
  position:absolute;top:6px;right:6px;z-index:3;line-height:1;padding:4px 8px;font:inherit;font-size:.78rem;
  border:1px solid var(--border-l);border-radius:var(--r);background:var(--bg-card);color:var(--text);cursor:pointer;
  box-shadow:var(--shadow);opacity:.88
}
.mermaid-fs-open-btn:hover{opacity:1;border-color:var(--accent);color:var(--accent)}

/* Helskärm pan/zoom — undvik will-change som kan rasterisera SVG vid zoom */
#mermaid-fs{position:fixed;inset:0;z-index:10000;display:flex;flex-direction:column;min-height:0;background:#fff}
[data-theme="dark"] #mermaid-fs{background:#000}
#mermaid-fs.hidden{display:none!important}
.mermaid-fs-bar{flex-shrink:0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px 12px;padding:10px 14px;background:var(--bg-nav);border-bottom:1px solid var(--border-l)}
.mermaid-fs-tabs{display:flex;gap:4px;align-items:center}
.mermaid-fs-tab{padding:5px 12px;font:inherit;font-size:.76rem;border:1px solid var(--border-l);border-radius:var(--r);background:var(--bg);color:var(--text-2);cursor:pointer}
.mermaid-fs-tab.on{background:var(--accent);color:#fff;border-color:var(--accent);font-weight:600}
.mermaid-fs-hint{font-size:.72rem;color:var(--text-2);flex:1;min-width:140px;line-height:1.35}
.mermaid-fs-bar .mermaid-fs-actions button{font:inherit;font-size:.82rem;padding:6px 12px;border-radius:var(--r);border:1px solid var(--border-l);background:var(--bg);color:var(--text);cursor:pointer}
.mermaid-fs-bar .mermaid-fs-actions button:hover{background:var(--bg-card);color:var(--accent)}
.mermaid-fs-bar .mermaid-fs-actions button:disabled{opacity:.45;cursor:not-allowed}
.mermaid-fs-body{flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden;position:relative}
.mermaid-fs-viewport{flex:1;min-height:0;overflow:hidden;touch-action:none;cursor:grab;position:relative}
.mermaid-fs-viewport.hidden{display:none!important}
.mermaid-fs-viewport.mermaid-fs-panning{cursor:grabbing}
.mermaid-fs-code{flex:1;min-height:0;overflow:auto;padding:16px 20px;background:var(--bg)}
.mermaid-fs-code.hidden{display:none!important}
.mermaid-fs-code-pre{margin:0;font-family:Consolas,'Courier New',monospace;font-size:.82rem;line-height:1.55;white-space:pre;word-break:break-word;color:var(--text)}
.mermaid-fs-canvas{position:absolute;left:0;top:0;transform-origin:0 0}
.mermaid-fs-canvas svg,.mermaid-fs-canvas svg text,.mermaid-fs-canvas svg tspan{
  max-width:none!important;height:auto!important;
  user-select:text!important;-webkit-user-select:text!important;
  shape-rendering:geometricPrecision;text-rendering:geometricPrecision;
}
.mermaid-fs-canvas svg{display:block}

/* Utskrift: endast renderat innehåll (markdown m.m.), ingen app-krom */
@media print {
  @page { margin: 12mm; }
  /* Mörkt tema använder ljusa variabler vid print så text inte blir vit på vit */
  [data-theme="dark"] {
    --bg: #fff;
    --bg-footer: #f5f5f5;
    --bg-nav: #f3f4f6;
    --bg-info: #eef2f7;
    --bg-card: #fafafa;
    --text: #1a1a1a;
    --text-2: #444;
    --link: #0066cc;
    --border: #ccc;
    --border-l: #ddd;
    --shadow: none;
    --accent: #0077bc;
    --darkBlue: #1e3a5f;
  }
  html, body {
    height: auto !important;
    min-height: 0 !important;
    overflow: visible !important;
    background: #fff !important;
    color: var(--text) !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  body { display: block !important; }
  .header,
  .sidebar,
  .sidebar-overlay,
  .mobile-nav,
  .mobile-nav-overlay,
  .ctoolbar,
  footer,
  #mermaid-fs,
  .mermaid-fs-open-btn {
    display: none !important;
  }
  .workspace {
    display: block !important;
    overflow: visible !important;
  }
  .content-main {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    overflow: visible !important;
    min-height: 0 !important;
  }
  .cbody {
    overflow: visible !important;
    padding: 0 !important;
    flex: none !important;
  }
  .md { max-width: none !important; }
  .md a { color: #0066cc !important; text-decoration: underline; }
  .md code, .md pre { border-color: #ccc !important; }
  .raw-view, .img-preview, .file-info-box { max-width: none !important; }
}
</style>
</head>
<body>

<header class="header">
  <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="<?= h(__('view.sidebar_toggle_tree')) ?>">
    📁 <?= h(__('view.sidebar_files')) ?>
  </button>
  <a href="../" style="display:flex;align-items:center;gap:10px;text-decoration:none">
    <div class="logo-mark">📘</div>
    <div class="logo-text"><?= h(APP_NAME) ?><span class="logo-sub"><?= h(__('view.logo_sub')) ?></span></div>
  </a>
  <div class="hdr-sep"></div>
  <div class="hdr-title"><?= h($title) ?></div>
  <div class="hdr-actions">
    <a href="../" class="btn btn-white btn-sm">← <?= h(__('common.back')) ?></a>
    <?php if ($isAuthed): ?>
    <a href="../edit/?file=<?= urlencode($filename) ?>" class="btn btn-white btn-sm">✏️ <?= h(__('common.edit')) ?></a>
    <?php else: ?>
    <a href="../login.php?back=<?= urlencode($loginBack) ?>" class="btn btn-white btn-sm">🔐 <?= h(__('common.login')) ?></a>
    <?php endif; ?>
    <details class="hdr-split-dl" id="hdr-split-dl">
      <summary class="btn btn-white btn-sm hdr-split-btn" aria-label="<?= h(__('view.download_aria')) ?>">⬇ <?= h(__('view.download_btn')) ?></summary>
      <div class="hdr-split-menu" role="menu">
        <a class="hdr-split-opt" href="../download.php?file=<?= urlencode($filename) ?>&amp;ext=skill" role="menuitem"><?= h(__('index.dl_as_skill')) ?></a>
        <a class="hdr-split-opt" href="../download.php?file=<?= urlencode($filename) ?>&amp;ext=zip" role="menuitem"><?= h(__('index.dl_as_zip')) ?></a>
      </div>
    </details>
    <button class="theme-btn" onclick="toggleTheme()" title="<?= h(__('common.theme_toggle')) ?>">🌓</button>
    <button class="hamburger-btn" onclick="toggleMobileNav()" aria-label="<?= h(__('common.menu')) ?>">
      <span></span>
      <span></span>
      <span></span>
    </button>
  </div>
</header>

<!-- Mobile Navigation -->
<div class="mobile-nav-overlay" onclick="closeMobileNav()"></div>
<nav class="mobile-nav">
  <div class="mobile-nav-header">
    <div class="mobile-nav-title"><?= h(APP_NAME) ?></div>
    <div class="mobile-nav-subtitle"><?= h(__('common.nav_subtitle')) ?></div>
  </div>
  <div class="mobile-nav-section">
    <a href="../" class="mobile-nav-item">
      <span class="icon">←</span>
      <span><?= h(__('common.back')) ?></span>
    </a>
    <a href="javascript:void(0)" onclick="toggleSidebar(); closeMobileNav();" class="mobile-nav-item">
      <span class="icon">📁</span>
      <span><?= h(__('view.sidebar_toggle_tree')) ?></span>
    </a>
    <?php if ($isAuthed): ?>
    <a href="../edit/?file=<?= urlencode($filename) ?>" class="mobile-nav-item">
      <span class="icon">✏️</span>
      <span><?= h(__('common.edit')) ?></span>
    </a>
    <?php else: ?>
    <a href="../login.php?back=<?= urlencode($loginBack) ?>" class="mobile-nav-item">
      <span class="icon">🔐</span>
      <span><?= h(__('common.login')) ?></span>
    </a>
    <?php endif; ?>
  </div>
  <div class="mobile-nav-section" style="border-top: 1px solid var(--border-l); padding-top: 8px;">
    <a href="../download.php?file=<?= urlencode($filename) ?>&amp;ext=skill" class="mobile-nav-item">
      <span class="icon">⬇️</span>
      <span><?= h(__('view.mobile_dl_skill')) ?></span>
    </a>
    <a href="../download.php?file=<?= urlencode($filename) ?>&amp;ext=zip" class="mobile-nav-item">
      <span class="icon">📦</span>
      <span><?= h(__('view.mobile_dl_zip')) ?></span>
    </a>
    <a href="javascript:void(0)" onclick="toggleTheme(); closeMobileNav();" class="mobile-nav-item">
      <span class="icon">🌓</span>
      <span><?= h(__('common.theme_toggle')) ?></span>
    </a>
  </div>
</nav>

<!-- Sidebar Overlay for mobile -->
<div class="sidebar-overlay"></div>

<div class="workspace">

  <!-- SIDEBAR -->
  <div class="sidebar mobile-hidden"  id="sidebar">
    <div class="sb-hdr">
      <div class="sb-title"><?= h($title) ?></div>
      <div class="sb-meta"><?= h(__('view.files_count', ['n' => (string)$numFiles, 'size' => fmt_size($totalSize)])) ?></div>
      <div class="sb-actions">
        <?php if ($isAuthed): ?>
        <a href="../edit/?file=<?= urlencode($filename) ?>" class="btn btn-xs btn-teal">✏️ <?= h(__('common.edit')) ?></a>
        <?php else: ?>
        <a href="../login.php?back=<?= urlencode($loginBack) ?>" class="btn btn-xs btn-teal">🔐 <?= h(__('common.login')) ?></a>
        <?php endif; ?>
        <a href="../" class="btn btn-xs btn-secondary">← <?= h(__('common.start')) ?></a>
      </div>
    </div>

    <?php if (!empty($skillMeta['description'])): ?>
    <div class="sb-section">
      <div class="sb-section-lbl"><?= h(__('view.section_description')) ?></div>
      <div style="font-size:.76rem;color:var(--text-2);line-height:1.5;white-space:pre-wrap"><?= h($skillMeta['description']) ?></div>
    </div>
    <?php endif; ?>

    <?php
    $metaDisplay = array_filter($skillMeta, fn($v, $k) => $v !== '' && !in_array($k, ['title','description','tags']), ARRAY_FILTER_USE_BOTH);
    if ($metaDisplay): ?>
    <div class="sb-section">
      <div class="sb-section-lbl"><?= h(__('view.section_metadata')) ?></div>
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
      <div class="sb-section-lbl"><?= h(__('view.section_tags')) ?></div>
      <?php foreach ($tags as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- FILE TREE -->
    <div class="tree-wrap" id="tree-wrap"></div>
  </div>

  <!-- CONTENT -->
  <div class="content-main">
    <div class="ctoolbar">
      <div class="breadcrumb" id="breadcrumb"><?= h(__('view.breadcrumb_pick')) ?></div>
      <div class="view-seg">
        <button class="vseg on" id="vseg-r" onclick="setView('rendered')"><?= h(__('view.seg_rendered')) ?></button>
        <button class="vseg" id="vseg-w" onclick="setView('raw')"><?= h(__('view.seg_raw')) ?></button>
      </div>
      <button class="btn btn-xs btn-secondary" onclick="copyContent()">📋 <?= h(__('view.copy')) ?></button>
    </div>
    <div class="cbody" id="cbody">
      <p style="color:var(--text-2);font-size:.85rem"><?= h(__('view.empty_pick')) ?></p>
    </div>
  </div>

</div>

<footer><?= h(APP_NAME) ?> · <?= h($filename) ?> · <?= h(fmt_size((int)filesize($filePath))) ?></footer>

<div id="mermaid-fs" class="hidden" role="dialog" aria-modal="true" aria-label="<?= h(__('view.mermaid_fs_aria')) ?>">
  <div class="mermaid-fs-bar">
    <div class="mermaid-fs-tabs">
      <button type="button" class="mermaid-fs-tab on" id="mermaid-fs-tab-diagram" onclick="setMermaidFsPanel('diagram')"><?= h(__('view.mermaid_tab_diagram')) ?></button>
      <button type="button" class="mermaid-fs-tab" id="mermaid-fs-tab-code" onclick="setMermaidFsPanel('code')"><?= h(__('view.mermaid_tab_code')) ?></button>
    </div>
    <span class="mermaid-fs-hint"><?= h(__('view.mermaid_hint')) ?></span>
    <div class="mermaid-fs-actions" style="display:flex;gap:6px;align-items:center;flex-shrink:0">
      <button type="button" id="mermaid-fs-fit" onclick="fitMermaidFullscreen()">↺ <?= h(__('view.mermaid_fit')) ?></button>
      <button type="button" id="mermaid-fs-close" onclick="closeMermaidFullscreen()">✕ <?= h(__('common.close')) ?></button>
    </div>
  </div>
  <div class="mermaid-fs-body">
    <div class="mermaid-fs-viewport" id="mermaid-fs-viewport">
      <div class="mermaid-fs-canvas" id="mermaid-fs-canvas"></div>
    </div>
    <div class="mermaid-fs-code hidden" id="mermaid-fs-code" aria-hidden="true">
      <pre class="mermaid-fs-code-pre"><code id="mermaid-fs-code-text"></code></pre>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script>
var VIEW_LANG = {
  fmLabel: <?= json_encode(__('view.fm_label'), JSON_UNESCAPED_UNICODE) ?>,
  binaryFile: <?= json_encode(__('view.binary_file'), JSON_UNESCAPED_UNICODE) ?>,
  binaryPath: <?= json_encode(__('view.binary_path'), JSON_UNESCAPED_UNICODE) ?>,
  binarySize: <?= json_encode(__('view.binary_size'), JSON_UNESCAPED_UNICODE) ?>,
  binaryType: <?= json_encode(__('view.binary_type'), JSON_UNESCAPED_UNICODE) ?>,
  binaryKind: <?= json_encode(__('view.binary_kind'), JSON_UNESCAPED_UNICODE) ?>,
  mermaidWrapTitle: <?= json_encode(__('view.mermaid_wrap_title'), JSON_UNESCAPED_UNICODE) ?>,
  mermaidFsAria: <?= json_encode(__('view.mermaid_fs_open_aria'), JSON_UNESCAPED_UNICODE) ?>,
  mermaidFsTitle: <?= json_encode(__('view.mermaid_fs_open_title'), JSON_UNESCAPED_UNICODE) ?>,
  mermaidErr: <?= json_encode(__('view.mermaid_error'), JSON_UNESCAPED_UNICODE) ?>,
  copied: <?= json_encode(__('view.copied'), JSON_UNESCAPED_UNICODE) ?>
};
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
    body.innerHTML = '<div class="file-info-box"><div class="fib-icon">' + fIcon(path.split('/').pop()) + '</div><div class="fib-row"><span class="fib-k">' + esc(VIEW_LANG.binaryFile) + '</span><span class="fib-v">' + esc(path.split('/').pop()) + '</span></div><div class="fib-row"><span class="fib-k">' + esc(VIEW_LANG.binaryPath) + '</span><span class="fib-v">' + esc(path) + '</span></div><div class="fib-row"><span class="fib-k">' + esc(VIEW_LANG.binarySize) + '</span><span class="fib-v">' + fmtSz(file.size) + '</span></div><div class="fib-row"><span class="fib-k">' + esc(VIEW_LANG.binaryType) + '</span><span class="fib-v">' + esc(VIEW_LANG.binaryKind) + '</span></div></div>';
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
      html += '<div class="fm-box"><div class="fm-lbl">' + esc(VIEW_LANG.fmLabel) + '</div>';
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
    wrap.className = 'mermaid-block-wrap';
    wrap.title = VIEW_LANG.mermaidWrapTitle;
    wrap.style.cssText = 'background:var(--bg-nav);border:1px solid var(--border-l);border-radius:var(--r-lg);padding:16px;overflow-x:auto;margin:9px 0;text-align:center';
    var div = document.createElement('div');
    div.className = 'mermaid';
    div.textContent = code;
    wrap.appendChild(div);
    var fsBtn = document.createElement('button');
    fsBtn.type = 'button';
    fsBtn.className = 'mermaid-fs-open-btn';
    fsBtn.setAttribute('aria-label', VIEW_LANG.mermaidFsAria);
    fsBtn.title = VIEW_LANG.mermaidFsTitle;
    fsBtn.textContent = '⛶';
    fsBtn.onclick = function(ev) {
      ev.preventDefault();
      ev.stopPropagation();
      var n = 0;
      function tryOpen() {
        if (div.querySelector('svg')) {
          openMermaidFullscreen(div);
          return;
        }
        if (n++ < 40) setTimeout(tryOpen, 80);
      }
      tryOpen();
    };
    wrap.appendChild(fsBtn);
    wrap.setAttribute('data-mermaid-source', encodeURIComponent(code));
    el.parentNode.replaceWith(wrap);
  });
  var nodes = container.querySelectorAll('.mermaid');
  var p = mermaid.run({ nodes: nodes });
  if (p && typeof p.then === 'function') {
    p.catch(function(e) {
      container.querySelectorAll('.mermaid:not([data-processed="true"])').forEach(function(m) {
        m.innerHTML = '<span style="color:var(--red);font-size:.8rem">⚠ ' + esc(VIEW_LANG.mermaidErr) + esc(String(e.message || e)) + '</span>';
      });
    });
  }
}

/* ── Mermaid helskärm: pan / zoom / centrera ─────────── */
var mermaidFs = { panX: 0, panY: 0, scale: 1, drag: false, lx: 0, ly: 0, handlers: null, resize: null };
var mermaidFsCapture = { capture: true, passive: false };

function applyMermaidFsTransform() {
  var c = document.getElementById('mermaid-fs-canvas');
  if (!c) return;
  c.style.transform = 'translate(' + mermaidFs.panX + 'px,' + mermaidFs.panY + 'px) scale(' + mermaidFs.scale + ')';
}

function setMermaidFsPanel(mode) {
  var vp = document.getElementById('mermaid-fs-viewport');
  var code = document.getElementById('mermaid-fs-code');
  var td = document.getElementById('mermaid-fs-tab-diagram');
  var tc = document.getElementById('mermaid-fs-tab-code');
  var fitBtn = document.getElementById('mermaid-fs-fit');
  if (!vp || !code || !td || !tc) return;
  if (mode === 'code') {
    vp.classList.add('hidden');
    code.classList.remove('hidden');
    code.setAttribute('aria-hidden', 'false');
    td.classList.remove('on');
    tc.classList.add('on');
    if (fitBtn) fitBtn.disabled = true;
  } else {
    vp.classList.remove('hidden');
    code.classList.add('hidden');
    code.setAttribute('aria-hidden', 'true');
    td.classList.add('on');
    tc.classList.remove('on');
    if (fitBtn) fitBtn.disabled = false;
    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        fitMermaidFullscreen();
      });
    });
  }
}

function fitMermaidFullscreen() {
  var codePanel = document.getElementById('mermaid-fs-code');
  if (codePanel && !codePanel.classList.contains('hidden')) return;
  var viewport = document.getElementById('mermaid-fs-viewport');
  var canvas = document.getElementById('mermaid-fs-canvas');
  if (!viewport || !canvas) return;
  var svg = canvas.querySelector('svg');
  if (!svg) return;
  var vw = viewport.clientWidth;
  var vh = viewport.clientHeight;
  var pad = 40;
  if (vw < 1 || vh < 1) return;

  function applyFit() {
    mermaidFs.panX = 0;
    mermaidFs.panY = 0;
    mermaidFs.scale = 1;
    canvas.style.transform = 'translate(0px,0px) scale(1)';
    void canvas.offsetHeight;

    var cr = canvas.getBoundingClientRect();
    var sr = svg.getBoundingClientRect();
    var w = sr.width;
    var h = sr.height;
    var cxLocal = sr.left - cr.left + sr.width / 2;
    var cyLocal = sr.top - cr.top + sr.height / 2;

    if (w < 4 || h < 4 || !isFinite(w) || !isFinite(h)) {
      var bb = svg.getBBox();
      var ctm = svg.getScreenCTM();
      if (ctm && svg.createSVGPoint) {
        var pt = svg.createSVGPoint();
        pt.x = bb.x;
        pt.y = bb.y;
        var p0 = pt.matrixTransform(ctm);
        pt.x = bb.x + bb.width;
        pt.y = bb.y + bb.height;
        var p1 = pt.matrixTransform(ctm);
        w = Math.max(Math.abs(p1.x - p0.x), 1);
        h = Math.max(Math.abs(p1.y - p0.y), 1);
        pt.x = bb.x + bb.width / 2;
        pt.y = bb.y + bb.height / 2;
        var pc = pt.matrixTransform(ctm);
        cxLocal = pc.x - cr.left;
        cyLocal = pc.y - cr.top;
      } else {
        w = Math.max(bb.width || 1, 1);
        h = Math.max(bb.height || 1, 1);
        cxLocal = w / 2;
        cyLocal = h / 2;
      }
    }

    if (!isFinite(cxLocal) || !isFinite(cyLocal)) {
      cxLocal = w / 2;
      cyLocal = h / 2;
    }

    var s = Math.min((vw - pad) / w, (vh - pad) / h);
    if (!isFinite(s) || s <= 0) s = 1;
    if (s > 120) s = 120;
    if (s < 0.02) s = 0.02;

    mermaidFs.scale = s;
    mermaidFs.panX = vw / 2 - cxLocal * s;
    mermaidFs.panY = vh / 2 - cyLocal * s;
    applyMermaidFsTransform();
  }

  requestAnimationFrame(function() {
    requestAnimationFrame(applyFit);
  });
}

function unwireMermaidFs() {
  var view = document.getElementById('mermaid-fs-viewport');
  var h = mermaidFs.handlers;
  if (h) {
    window.removeEventListener('pointermove', h.move, mermaidFsCapture);
    window.removeEventListener('pointerup', h.up, true);
    window.removeEventListener('pointercancel', h.up, true);
  }
  if (view && h) {
    view.removeEventListener('wheel', h.wheel);
    view.removeEventListener('pointerdown', h.down);
    view.removeEventListener('dblclick', h.dbl);
  }
  if (view && mermaidFs.drag) {
    mermaidFs.drag = false;
    view.classList.remove('mermaid-fs-panning');
  }
  mermaidFs.handlers = null;
}

function wireMermaidFs() {
  var view = document.getElementById('mermaid-fs-viewport');
  if (!view) return;
  unwireMermaidFs();
  var h = {};
  h.wheel = function(e) {
    e.preventDefault();
    var r = view.getBoundingClientRect();
    var mx = e.clientX - r.left;
    var my = e.clientY - r.top;
    var factor = e.deltaY > 0 ? 0.92 : 1.08;
    var newScale = Math.max(0.05, Math.min(12, mermaidFs.scale * factor));
    var ratio = newScale / mermaidFs.scale;
    mermaidFs.panX = mx - (mx - mermaidFs.panX) * ratio;
    mermaidFs.panY = my - (my - mermaidFs.panY) * ratio;
    mermaidFs.scale = newScale;
    applyMermaidFsTransform();
  };
  h.down = function(e) {
    if (mermaidFs.drag) return;
    if (e.button !== 0 && e.button !== 1) return;
    e.preventDefault();
    mermaidFs.drag = true;
    mermaidFs.lx = e.clientX;
    mermaidFs.ly = e.clientY;
    view.classList.add('mermaid-fs-panning');
    try { view.setPointerCapture(e.pointerId); } catch (x) {}
    window.addEventListener('pointermove', h.move, mermaidFsCapture);
    window.addEventListener('pointerup', h.up, true);
    window.addEventListener('pointercancel', h.up, true);
  };
  h.move = function(e) {
    if (!mermaidFs.drag) return;
    e.preventDefault();
    mermaidFs.panX += e.clientX - mermaidFs.lx;
    mermaidFs.panY += e.clientY - mermaidFs.ly;
    mermaidFs.lx = e.clientX;
    mermaidFs.ly = e.clientY;
    applyMermaidFsTransform();
  };
  h.up = function(e) {
    if (!mermaidFs.drag) return;
    mermaidFs.drag = false;
    view.classList.remove('mermaid-fs-panning');
    window.removeEventListener('pointermove', h.move, mermaidFsCapture);
    window.removeEventListener('pointerup', h.up, true);
    window.removeEventListener('pointercancel', h.up, true);
    try { view.releasePointerCapture(e.pointerId); } catch (x) {}
  };
  h.dbl = function(e) {
    if (e.target.closest('#mermaid-fs-code')) return;
    fitMermaidFullscreen();
  };
  view.addEventListener('wheel', h.wheel, { passive: false });
  view.addEventListener('pointerdown', h.down);
  view.addEventListener('dblclick', h.dbl);
  mermaidFs.handlers = h;
}

function openMermaidFullscreen(source) {
  var overlay = document.getElementById('mermaid-fs');
  var canvas = document.getElementById('mermaid-fs-canvas');
  var codeText = document.getElementById('mermaid-fs-code-text');
  if (!overlay || !canvas) return;
  var wrap = source.closest ? source.closest('.mermaid-block-wrap') : null;
  var src = '';
  if (wrap) {
    var raw = wrap.getAttribute('data-mermaid-source');
    if (raw) {
      try { src = decodeURIComponent(raw); } catch (x) {}
    }
  }
  if (codeText) codeText.textContent = src;
  canvas.innerHTML = '';
  var clone = source.cloneNode(true);
  clone.removeAttribute('id');
  canvas.appendChild(clone);
  clone.querySelectorAll('svg text, svg tspan').forEach(function(el) {
    el.style.setProperty('user-select', 'text', 'important');
  });
  overlay.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  mermaidFs.panX = 0;
  mermaidFs.panY = 0;
  mermaidFs.scale = 1;
  canvas.style.transform = 'none';
  setMermaidFsPanel('diagram');
  wireMermaidFs();
  mermaidFs.resize = function() {
    var cp = document.getElementById('mermaid-fs-code');
    if (cp && !cp.classList.contains('hidden')) return;
    fitMermaidFullscreen();
  };
  window.addEventListener('resize', mermaidFs.resize);
  requestAnimationFrame(function() {
    requestAnimationFrame(function() {
      fitMermaidFullscreen();
    });
  });
}

function closeMermaidFullscreen() {
  var overlay = document.getElementById('mermaid-fs');
  var canvas = document.getElementById('mermaid-fs-canvas');
  var codeText = document.getElementById('mermaid-fs-code-text');
  unwireMermaidFs();
  if (mermaidFs.resize) {
    window.removeEventListener('resize', mermaidFs.resize);
    mermaidFs.resize = null;
  }
  if (overlay) overlay.classList.add('hidden');
  if (canvas) canvas.innerHTML = '';
  if (codeText) codeText.textContent = '';
  var vp = document.getElementById('mermaid-fs-viewport');
  var code = document.getElementById('mermaid-fs-code');
  if (vp) vp.classList.remove('hidden');
  if (code) code.classList.add('hidden');
  var td = document.getElementById('mermaid-fs-tab-diagram');
  var tc = document.getElementById('mermaid-fs-tab-code');
  var fitBtn = document.getElementById('mermaid-fs-fit');
  if (td) td.classList.add('on');
  if (tc) tc.classList.remove('on');
  if (fitBtn) fitBtn.disabled = false;
  document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
  if (e.key !== 'Escape') return;
  var fs = document.getElementById('mermaid-fs');
  if (fs && !fs.classList.contains('hidden')) closeMermaidFullscreen();
});

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
    btn.textContent = '✓ ' + VIEW_LANG.copied;
    setTimeout(function() { btn.textContent = orig; }, 1500);
  });
}

// ── FRONTMATTER PARSER ────────────────────────────────
function parseFM(text) {
  var m = text.match(/^---\s*\n([\s\S]*?)\n---\s*\n?([\s\S]*)/);
  if (!m) return { fm: {}, body: text };
  var lines = m[1].split(/\r?\n/);
  var n = lines.length;
  var i = 0;
  var fm = {};
  function strTrim(s) { return String(s).replace(/^\s+|\s+$/g, ''); }
  while (i < n) {
    var line = lines[i];
    if (strTrim(line) === '') { i++; continue; }
    var tm = line.match(/^tags:\s*(.*)$/);
    if (tm) {
      var tRest = strTrim(tm[1]);
      if (tRest !== '') {
        fm.tags = tRest;
        i++;
        continue;
      }
      i++;
      var tagList = [];
      while (i < n) {
        var tNext = lines[i];
        if (strTrim(tNext) === '') { i++; continue; }
        var lm = tNext.match(/^\s*-\s+(.+)$/);
        if (lm) {
          tagList.push(lm[1].replace(/^['"]|['"]$/g, ''));
          i++;
        } else if (/^\s*-\s*$/.test(tNext)) {
          i++;
        } else {
          break;
        }
      }
      fm.tags = tagList.join(', ');
      continue;
    }
    var kv = line.match(/^(\w+):\s*(.*)$/);
    if (kv) {
      var key = kv[1];
      var rest = strTrim(kv[2]);
      if (/^>\-?\s*$/.test(rest) || /^\|\-?\s*$/.test(rest)) {
        var isFolded = rest.charAt(0) === '>';
        i++;
        var chunk = [];
        while (i < n) {
          var next = lines[i];
          if (/^[a-zA-Z_][a-zA-Z0-9_]*:\s/.test(next)) break;
          if (/^[ \t]+/.test(next)) {
            chunk.push(next.replace(/^[ \t]+/, ''));
            i++;
            continue;
          }
          if (strTrim(next) === '') {
            i++;
            chunk.push(isFolded ? ' ' : '');
            continue;
          }
          break;
        }
        fm[key] = isFolded
          ? strTrim(chunk.join(' ').replace(/\s+/g, ' '))
          : strTrim(chunk.join('\n'));
        continue;
      }
      fm[key] = rest.replace(/^['"]|['"]$/g, '');
      i++;
      continue;
    }
    i++;
  }
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
