<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_common.php';

skill_require_auth('../login.php');

if (!is_dir(CONTENT_DIR)) mkdir(CONTENT_DIR, 0755, true);

$isNew    = true;
$filePath = null;
$filename = '';
$error    = '';

// Load help content
$helpContent = is_file(__DIR__ . '/../skill-intro.md')
    ? (string)file_get_contents(__DIR__ . '/../skill-intro.md')
    : '';

// Load existing skill
if (!empty($_GET['file'])) {
    $filePath = validate_file_param((string)$_GET['file']);
    if ($filePath) {
        $isNew    = false;
        $filename = basename($filePath);
    }
}

/* ── SAVE (POST) ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skillName = trim($_POST['skill_name'] ?? '');
    $filesJson = $_POST['files_json'] ?? '{}';
    $origFile  = basename($_POST['original_file'] ?? '');

    if ($skillName === '') {
        $error = "Ange ett namn för skill-filen.";
    } else {
        $newFname = sanitize_filename($skillName);
        $savePath = CONTENT_DIR . $newFname;
        $files    = json_decode($filesJson, true) ?: [];

        $zip = new ZipArchive();
        $zip->open($savePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Preserve binary files from original ZIP
        $origToRead = ($origFile && $origFile !== $newFname)
            ? validate_file_param($origFile)
            : ($filePath ?? null);

        if ($origToRead) {
            $origZip = new ZipArchive();
            if ($origZip->open($origToRead) === true) {
                for ($i = 0; $i < $origZip->numFiles; $i++) {
                    $ename = (string)$origZip->getNameIndex($i);
                    if (str_ends_with($ename, '/')) continue;
                    $ext = strtolower(pathinfo($ename, PATHINFO_EXTENSION));
                    if (!in_array($ext, TEXT_EXTS)) {
                        $zip->addFromString($ename, (string)$origZip->getFromIndex($i));
                    }
                }
                $origZip->close();
            }
        }

        foreach ($files as $entryName => $content) {
            $safe = sanitize_entry((string)$entryName);
            if ($safe !== '') $zip->addFromString($safe, (string)$content);
        }
        $zip->close();

        // Delete old file if renamed
        if ($origFile && $origFile !== $newFname) {
            $oldPath = validate_file_param($origFile);
            if ($oldPath) unlink($oldPath);
        }

        header('Location: ../view/?file=' . urlencode($newFname));
        exit;
    }
}

/* ── LOAD FILES FOR EDITOR ───────────────────────────── */
$initialFiles = [];

if (!$isNew && $filePath) {
    $all = read_zip_files($filePath);
    foreach ($all as $name => $entry) {
        if ($entry['type'] === 'text') {
            $initialFiles[$name] = $entry['content'];
        }
    }
    $skillNameDefault = pathinfo($filename, PATHINFO_FILENAME);
} else {
    $initialFiles['SKILL.md'] = <<< 'SKILLMD'
---
name:
title:
description:
author:
version: 1.0.0
tags:
---

# Ny skill

## Syfte

Beskriv vad denna skill gör och när den används.

## Instruktioner

1. Steg ett
2. Steg två

## Exempel

```
# Exempel
```
SKILLMD;
    $skillNameDefault = '';
}

$pageTitle = $isNew ? 'Ny skill' : 'Redigera: ' . pathinfo($filename, PATHINFO_FILENAME);
$defaultEntry = '';
foreach (array_keys($initialFiles) as $n) {
    if (preg_match('/(?:^|\/)SKILL\.md$/i', $n)) { $defaultEntry = $n; break; }
}
if (!$defaultEntry && !empty($initialFiles)) {
    $defaultEntry = array_key_first($initialFiles);
}

$skillArchivePrefix = skill_archive_root_prefix_from_paths(array_keys($initialFiles));

$switchToAiUrl = $isNew ? '../ai/' : ('../ai/?file=' . rawurlencode($filename));
?>
<!DOCTYPE html>
<html lang="sv" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php favicon_link('../'); ?>
<title><?= h($pageTitle) ?> — <?= h(APP_NAME) ?></title>
<?php theme_script(); ?>
<?php common_css(); ?>
<style>
html,body{height:100%;overflow:hidden}
.workspace{flex:1;display:flex;overflow:hidden}

/* SIDEBAR */
.sidebar{width:248px;flex-shrink:0;background:var(--bg-nav);border-right:1px solid var(--border-l);display:flex;flex-direction:column;overflow:hidden}
.sb-hdr{padding:10px 12px;border-bottom:1px solid var(--border-l);flex-shrink:0}
.sb-hdr label{display:block;font-size:.65rem;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}
.skill-name-input{width:100%;padding:5px 8px;border:1px solid var(--border-l);border-radius:var(--r);font:inherit;font-size:.82rem;background:var(--bg);color:var(--text);margin-bottom:7px}
.skill-name-input:focus{outline:2px solid var(--accent);border-color:transparent}
.sb-btn-row{display:flex;gap:4px}
.add-file-custom{border:1px solid var(--border-l);border-radius:var(--r);padding:12px 14px;margin-bottom:16px;background:var(--bg-card)}
.add-file-custom-h{font-size:.8rem;font-weight:700;color:var(--accent);margin-bottom:6px}
.add-file-custom-d{font-size:.74rem;color:var(--text-2);margin-bottom:10px;line-height:1.45}
.structure-modal .modal-box{max-width:820px}
.structure-tree{font-family:Consolas,'Courier New',monospace;font-size:.68rem;line-height:1.45;background:var(--bg-nav);border:1px solid var(--border-l);border-radius:var(--r);padding:10px 12px;overflow-x:auto;margin:0 0 14px;color:var(--text)}
.structure-intro{font-size:.85rem;color:var(--text-2);margin-bottom:12px;line-height:1.45}
.structure-groups{display:flex;flex-direction:column;gap:10px}
.structure-group{border:1px solid var(--border-l);border-radius:var(--r);padding:10px 12px;background:var(--bg-card)}
.structure-group-h{font-size:.8rem;font-weight:700;color:var(--accent);margin-bottom:4px}
.structure-group-d{font-size:.74rem;color:var(--text-2);margin-bottom:8px;line-height:1.45}
.structure-group-line{margin:0 0 6px}
.structure-group-line:last-child{margin-bottom:0}
.structure-group-line strong{color:var(--text);font-weight:600}
.structure-group .btn{font-size:.74rem}
.structure-modal-footer{flex-wrap:wrap;gap:8px;justify-content:space-between}
.tree-section-hdr{padding:5px 12px 4px;font-size:.63rem;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border-l);border-top:1px solid var(--border-l);margin-top:4px;display:flex;align-items:center;justify-content:space-between}
.tree-wrap{flex:1;overflow-y:auto;padding:3px 0}

/* TREE */
.tree-item{display:flex;align-items:center;gap:5px;padding:4px 10px;cursor:pointer;font-size:.79rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:background .12s;user-select:none}
.tree-item:hover{background:rgba(0,119,188,.07)}
.tree-item.active{background:rgba(0,119,188,.14);color:var(--accent);font-weight:600}
.tree-item.folder{color:var(--darkBlue);font-weight:600;cursor:default;font-size:.78rem}
[data-theme="dark"] .tree-item.folder{color:#7ec8f0}
.tree-item .ti{font-size:.8rem;flex-shrink:0}
.tree-indent{display:inline-block;flex-shrink:0}
.tree-row{display:flex;align-items:stretch;gap:0;min-width:0;width:100%}
.tree-row .tree-item{flex:1;min-width:0}
.tree-item-ops{display:flex;align-items:center;gap:1px;flex-shrink:0;padding-right:4px;opacity:.55}
.tree-row:hover .tree-item-ops{opacity:1}
.tree-op-btn{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:1px solid var(--border-l);border-radius:var(--r);background:var(--bg);font-size:.68rem;cursor:pointer;color:var(--text-2)}
.tree-op-btn:hover{background:var(--bg-nav);color:var(--accent);border-color:var(--accent)}
.tree-op-btn:disabled{opacity:.35;cursor:not-allowed}

/* EDITOR AREA */
.editor-area{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.etoolbar{height:var(--toolbar-h);display:flex;align-items:center;padding:0 12px;gap:6px;border-bottom:1px solid var(--border-l);background:var(--bg-nav);flex-shrink:0;overflow-x:auto}
.etoolbar-label{font-size:.78rem;font-weight:600;color:var(--text-2);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}

/* SPLIT: monaco + preview */
.emain{flex:1;display:grid;grid-template-columns:1fr 1fr;overflow:hidden;min-height:0}
.eleft,.eright{display:flex;flex-direction:column;overflow:hidden;min-width:0;min-height:0}
.eleft{border-right:1px solid var(--border-l);position:relative}
.pane-hdr{padding:6px 12px;background:var(--bg-nav);border-bottom:1px solid var(--border-l);font-size:.7rem;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.05em;flex-shrink:0;display:flex;align-items:center;gap:6px}
.lang-badge{font-size:.62rem;padding:1px 5px;background:var(--accent);color:#fff;border-radius:3px;text-transform:none;letter-spacing:0;font-weight:600}
#monaco-container{flex:1;min-height:0;overflow:hidden}
.preview-scroll{flex:1;overflow-y:auto;padding:18px 20px}

/* ERROR */
.error-bar{background:#fdecea;color:#8b1a1a;border-bottom:1px solid #f5bbb8;padding:8px 14px;font-size:.82rem;flex-shrink:0}
[data-theme="dark"] .error-bar{background:#3b1111;color:#f28b82;border-color:#5c2020}

/* ── HELP MODAL ─────────────────────────────────────── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(2px)}
.modal-overlay.hidden{display:none}
.modal-box{background:var(--bg);border:1px solid var(--border-l);border-radius:var(--r-lg);box-shadow:0 8px 40px rgba(0,0,0,.25);width:100%;max-width:760px;max-height:85vh;display:flex;flex-direction:column;overflow:hidden}
.modal-hdr{padding:14px 18px;border-bottom:1px solid var(--border-l);display:flex;align-items:center;gap:10px;flex-shrink:0;background:var(--bg-nav)}
.modal-hdr-title{font-size:.95rem;font-weight:700;color:var(--accent);flex:1}
.modal-body{flex:1;overflow-y:auto;padding:22px 26px}
.modal-footer{padding:10px 18px;border-top:1px solid var(--border-l);background:var(--bg-nav);display:flex;justify-content:flex-end;flex-shrink:0}
.help-btn{display:inline-flex;align-items:center;gap:4px;width:30px;height:30px;border-radius:50%;justify-content:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.32);color:#fff;font-size:.85rem;font-weight:700;cursor:pointer;transition:background .15s;padding:0}
.help-btn:hover{background:rgba(255,255,255,.28)}

@media(max-width:900px){.emain{grid-template-columns:1fr}.eright{display:none}}
@media(max-width:660px){.sidebar{display:none}}

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
  .editor-area{
    width:100%;
  }
  .etoolbar{
    padding:0 10px;
  }
  .sidebar-toggle{
    display:flex!important;
  }
  .help-btn{
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

@media(max-width: 768px) {
  .hdr-actions .btn.btn-switch-mode { display: inline-flex !important; }
}
</style>
</head>
<body>

<header class="header">
  <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Visa verktyg">
    🛠️ Verktyg
  </button>
  <a href="../" style="display:flex;align-items:center;gap:10px;text-decoration:none">
    <div class="logo-mark">📘</div>
    <div class="logo-text"><?= h(APP_NAME) ?><span class="logo-sub"><?= $isNew ? 'Ny skill' : 'Redigera' ?></span></div>
  </a>
  <div class="hdr-sep"></div>
  <div class="hdr-title"><?= h($pageTitle) ?></div>
  <div class="hdr-actions">
    <a href="<?= h($switchToAiUrl) ?>" class="btn btn-sm btn-teal btn-switch-mode" title="<?= $isNew ? 'Byt till AI-redigering (ny tom skill)' : 'Öppna samma arkiv i AI-redigering' ?>">🤖 Byt till AI-redigering</a>
    <?php if (!$isNew): ?>
    <a href="../view/?file=<?= urlencode($filename) ?>" class="btn btn-white btn-sm">👁 Visa</a>
    <?php endif; ?>
    <a href="../" class="btn btn-white btn-sm">← Tillbaka</a>
    <a href="../logout.php" class="btn btn-white btn-sm" onclick="return confirm('Logga ut?')">🔓 Logga ut</a>
    <button class="help-btn" onclick="openHelp()" title="Vad är en .skill-fil?">?</button>
    <button class="theme-btn" onclick="toggleTheme()" title="Växla tema">🌓</button>
    <button class="hamburger-btn" onclick="toggleMobileNav()" aria-label="Meny">
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
    <div class="mobile-nav-subtitle">Navigation</div>
  </div>
  <div class="mobile-nav-section">
    <a href="../" class="mobile-nav-item">
      <span class="icon">←</span>
      <span>Tillbaka</span>
    </a>
    <a href="javascript:void(0)" onclick="toggleSidebar(); closeMobileNav();" class="mobile-nav-item">
      <span class="icon">🛠️</span>
      <span>Visa verktyg</span>
    </a>
    <a href="<?= h($switchToAiUrl) ?>" class="mobile-nav-item">
      <span class="icon">🤖</span>
      <span>Byt till AI-redigering</span>
    </a>
    <?php if (!$isNew): ?>
    <a href="../view/?file=<?= urlencode($filename) ?>" class="mobile-nav-item">
      <span class="icon">👁</span>
      <span>Visa skill</span>
    </a>
    <?php endif; ?>
    <a href="javascript:void(0)" onclick="saveSkill(); closeMobileNav();" class="mobile-nav-item">
      <span class="icon">💾</span>
      <span>Spara</span>
    </a>
  </div>
  <div class="mobile-nav-section" style="border-top: 1px solid var(--border-l); padding-top: 8px;">
    <a href="../logout.php" class="mobile-nav-item" onclick="return confirm('Logga ut?')">
      <span class="icon">🔓</span>
      <span>Logga ut</span>
    </a>
    <a href="javascript:void(0)" onclick="openHelp(); closeMobileNav();" class="mobile-nav-item">
      <span class="icon">?</span>
      <span>Hjälp</span>
    </a>
    <a href="javascript:void(0)" onclick="toggleTheme(); closeMobileNav();" class="mobile-nav-item">
      <span class="icon">🌓</span>
      <span>Växla tema</span>
    </a>
  </div>
</nav>

<!-- Sidebar Overlay for mobile -->
<div class="sidebar-overlay"></div>

<div class="workspace">

  <!-- SIDEBAR -->
  <div class="sidebar mobile-hidden" id="sidebar">
    <div class="sb-hdr">
      <label>Filnamn (.skill)</label>
      <input type="text" id="skill-name-input" class="skill-name-input"
             value="<?= h($skillNameDefault) ?>" placeholder="min-skill">
      <div class="sb-btn-row">
        <button type="button" class="btn btn-xs btn-success" style="flex:1" onclick="saveSkill()">💾 Spara</button>
        <button type="button" class="btn btn-xs btn-secondary" onclick="openAddFileModal()" title="Lägg till fil – egen sökväg eller standardmappar">+ Fil</button>
      </div>
    </div>
    <div class="tree-section-hdr">
      <span>Filer i arkivet</span>
    </div>
    <div class="tree-wrap" id="tree-wrap"></div>
  </div>

  <!-- EDITOR -->
  <div class="editor-area">
    <form id="skill-form" method="POST" style="display:contents">
      <input type="hidden" name="original_file" value="<?= h($filename) ?>">
      <input type="hidden" name="skill_name" id="form-skill-name">
      <input type="hidden" name="files_json" id="form-files-json">
    </form>

    <?php if ($error): ?>
    <div class="error-bar">⚠️ <?= h($error) ?></div>
    <?php endif; ?>

    <div class="etoolbar">
      <span class="etoolbar-label" id="current-file-label">Väljer fil…</span>
      <button type="button" class="btn btn-sm btn-secondary" id="btn-rename-file" onclick="if(currentFile)renameMoveFile(currentFile)" title="Byt namn eller flytta (sökväg i arkivet)">📂 Namn</button>
      <button type="button" class="btn btn-sm btn-secondary" id="btn-delete-file" onclick="if(currentFile)removeFile(currentFile)" title="Ta bort fil från arkivet">🗑 Ta bort</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="insertTemplate()">📋 Mall</button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="formatDoc()">✨ Formatera</button>
      <button type="button" class="btn btn-sm btn-success" onclick="saveSkill()">💾 Spara</button>
    </div>

    <div class="emain">
      <div class="eleft">
        <div class="pane-hdr">
          ✏️ Editor
          <span class="lang-badge" id="lang-badge">markdown</span>
        </div>
        <div id="monaco-container"></div>
      </div>
      <div class="eright">
        <div class="pane-hdr">👁 Förhandsgranskning</div>
        <div class="preview-scroll"><div class="md" id="ed-preview"></div></div>
      </div>
    </div>

  </div>
</div>

<footer><?= h(APP_NAME) ?> · <?= $isNew ? 'Ny skill' : h($filename) ?></footer>

<!-- HELP MODAL -->
<div class="modal-overlay hidden" id="help-modal" onclick="if(event.target===this)closeHelp()">
  <div class="modal-box">
    <div class="modal-hdr">
      <span class="modal-hdr-title">📘 Vad är en .skill-fil?</span>
      <button class="btn btn-xs btn-secondary" onclick="closeHelp()">✕ Stäng</button>
    </div>
    <div class="modal-body">
      <div class="md" id="help-content"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-sm btn-primary" onclick="closeHelp()">Stäng</button>
    </div>
  </div>
</div>

<div class="modal-overlay hidden structure-modal" id="skill-structure-modal" onclick="if(event.target===this)closeSkillStructureModal()">
  <div class="modal-box">
    <div class="modal-hdr">
      <span class="modal-hdr-title">+ Fil i arkivet</span>
      <button type="button" class="btn btn-xs btn-secondary" onclick="closeSkillStructureModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="add-file-custom">
        <div class="add-file-custom-h">Egen sökväg</div>
        <p class="add-file-custom-d">Skapa en tom fil var du vill i arkivet. Ange t.ex. <code>references/guide.md</code>, <code>docs/README.md</code> eller <code>SKILL.md</code>.</p>
        <button type="button" class="btn btn-sm btn-secondary" onclick="addFileFromPrompt()">📝 Ange sökväg…</button>
      </div>
      <p class="structure-intro">
        <strong>Standardmappar:</strong> välj en grupp för att skapa tomma mallfiler (befintliga sökvägar hoppas över). I en <code>.skill</code>-zip finns inga riktiga kataloger — sökvägar som <code>references/guide.md</code> skapar bara logiska mappar i trädet. Tabellen under beskriver <strong>syfte</strong> och hur innehåll typiskt <strong>laddas</strong> i agenten (on-demand jämfört med att köra skript).
      </p>
      <pre class="structure-tree" id="skill-structure-tree-diagram"></pre>
      <div class="structure-groups" id="skill-structure-groups"></div>
    </div>
    <div class="modal-footer structure-modal-footer">
      <button type="button" class="btn btn-sm btn-success" onclick="addAllSkillStructureFiles()">Lägg till alla saknade filer</button>
      <button type="button" class="btn btn-sm btn-primary" onclick="closeSkillStructureModal()">Stäng</button>
    </div>
  </div>
</div>

<!-- marked + mermaid MÅSTE laddas före Monaco-loadern (AMD-konflikt annars) -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<!-- Monaco via CDN -->
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.47.0/min/vs/loader.js"></script>
<script>
marked.setOptions({ breaks: true, gfm: true });
mermaid.initialize({ startOnLoad: false, theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default' });

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
  mermaid.run({ nodes: container.querySelectorAll('.mermaid') }).catch(function() {});
}

// ── STATE ──────────────────────────────────────────────
var initialFiles = <?= json_encode($initialFiles, JSON_UNESCAPED_UNICODE) ?>;
var helpMd       = <?= json_encode($helpContent, JSON_UNESCAPED_UNICODE) ?>;
var defaultEntry = <?= json_encode($defaultEntry) ?>;
/** Om SKILL.md ligger i t.ex. min-skill/SKILL.md — nya sökvägar som /references/x läggs under den mappen */
var skillArchivePrefix = <?= json_encode($skillArchivePrefix, JSON_UNESCAPED_UNICODE) ?>;

// edits tracks files not yet opened in Monaco
var edits = Object.assign({}, initialFiles);
var models = {};      // path -> Monaco ITextModel
var currentFile = null;
var monacoEditor = null;
/** Borttagna sökvägar som fortfarande finns i initialFiles (serverladdning) — döljs i träd och sparas inte */
var removedFromArchive = new Set();

/** Katalog som innehåller SKILL.md (t.ex. gs-it-… när posten är gs-it-…/SKILL.md). */
function getSkillArchivePrefixLive() {
  try {
    if (typeof getAllPaths === 'function') {
      var paths = getAllPaths();
      for (var i = 0; i < paths.length; i++) {
        var p = String(paths[i]).replace(/\\/g, '/');
        var m = p.match(/^(.+)\/SKILL\.md$/i);
        if (m) return m[1];
      }
    }
  } catch (e) {}
  return (typeof skillArchivePrefix === 'string' && skillArchivePrefix) ? skillArchivePrefix : '';
}

/**
 * Om arkivet har SKILL.md i en undermapp, lägger till den prefixet så /references/x inte hamnar i ZIP-rot.
 */
function applySkillFolderPrefix(name) {
  var p = getSkillArchivePrefixLive().replace(/\\/g, '/').replace(/\/+$/, '');
  if (!p) return name;
  var norm = String(name).replace(/\\/g, '/');
  if (norm === p || norm.indexOf(p + '/') === 0) return name;
  return p + '/' + name;
}

/**
 * Normaliserar sökväg i arkivet (snedstreck, ingen path traversal).
 * @param {string} name
 * @param {string} [basePath] Om name bara är ett filnamn: lägg under samma mapp som basePath (t.ex. references/a.md + b.md → references/b.md).
 * Ledande "/" = relativt skill-paketets rot (mappen med SKILL.md), inte ZIP-filens yttersta rot om den skiljer sig.
 */
function normalizeArchivePath(name, basePath) {
  if (!name || typeof name !== 'string') return '';
  var trimmed = name.trim();
  var fromArchiveRoot = /^\//.test(trimmed);
  name = trimmed.replace(/^\/+/, '').replace(/\\/g, '/');
  name = name.replace(/\/+/g, '/');
  if (name.startsWith('./')) name = name.slice(2);
  if (!name || name.indexOf('..') !== -1) return '';
  var parts = name.split('/').filter(function(p) { return p.length > 0; });
  name = parts.join('/');
  if (!name) return '';
  if (!fromArchiveRoot && basePath && name.indexOf('/') === -1) {
    var bp = String(basePath).replace(/\\/g, '/');
    var slash = bp.lastIndexOf('/');
    if (slash > 0) {
      var dir = bp.slice(0, slash);
      if (dir.indexOf('..') === -1) name = dir + '/' + name;
    }
  }
  return applySkillFolderPrefix(name);
}

function updateFileActionButtons() {
  var paths = getAllPaths();
  var n = paths.length;
  var ren = document.getElementById('btn-rename-file');
  var del = document.getElementById('btn-delete-file');
  if (ren) ren.disabled = !currentFile || n === 0;
  if (del) del.disabled = !currentFile || n <= 1;
}

// ── LANGUAGE MAP ───────────────────────────────────────
function getLang(filename) {
  var ext = (filename.split('.').pop() || '').toLowerCase();
  return {md:'markdown',markdown:'markdown',txt:'plaintext',js:'javascript',ts:'typescript',
          jsx:'javascript',tsx:'typescript',json:'json',yml:'yaml',yaml:'yaml',
          py:'python',sh:'shell',bash:'shell',css:'css',html:'html',xml:'xml',
          toml:'ini',ini:'ini',cfg:'ini',conf:'ini',csv:'plaintext',rst:'plaintext'}[ext] || 'plaintext';
}

function getMonacoTheme() {
  return document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';
}

// ── OVERRIDE toggleTheme to also update Monaco + Mermaid ──
var _origToggle = window.toggleTheme;
window.toggleTheme = function() {
  _origToggle();
  if (monacoEditor) monaco.editor.setTheme(getMonacoTheme());
  mermaid.initialize({ startOnLoad: false, theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default' });
  updatePreview();
};

// ── INIT MONACO ────────────────────────────────────────
require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.47.0/min/vs' } });
require(['vs/editor/editor.main'], function() {
  var container = document.getElementById('monaco-container');

  monacoEditor = monaco.editor.create(container, {
    theme:                getMonacoTheme(),
    minimap:              { enabled: false },
    wordWrap:             'on',
    fontSize:             13,
    lineHeight:           22,
    fontFamily:           "'Consolas', 'Monaco', 'Courier New', monospace",
    scrollBeyondLastLine: false,
    automaticLayout:      false,   // handled manually below
    padding:              { top: 10, bottom: 10 },
    renderLineHighlight:  'line',
    smoothScrolling:      true,
    cursorBlinking:       'smooth',
    bracketPairColorization: { enabled: true },
  });

  // Manual layout — measure container and tell Monaco
  function layoutEditor() {
    if (!monacoEditor) return;
    monacoEditor.layout({ width: container.clientWidth, height: container.clientHeight });
  }

  window.addEventListener('resize', layoutEditor);

  // Force layout on next two frames (flex/grid compute on second frame)
  requestAnimationFrame(function() {
    requestAnimationFrame(function() {
      layoutEditor();
    });
  });

  // Listen for content changes → update preview
  monacoEditor.onDidChangeModelContent(function() {
    updatePreview();
  });

  // Open default file, then layout again after model is set
  selectFile(defaultEntry);
  requestAnimationFrame(layoutEditor);
});

/** Unik URI per arkivpost — parse("inmemory://skill/"+path) kan slå ihop modeller för olika filer. */
function skillEntryToMonacoUri(entryPath) {
  var norm = String(entryPath).replace(/\\/g, '/');
  return monaco.Uri.from({
    scheme: 'skill',
    path: '/' + encodeURIComponent(norm),
  });
}

// ── FILE SELECTION ─────────────────────────────────────
function selectFile(path) {
  if (!monacoEditor) return;
  if (currentFile && models[currentFile]) {
    edits[currentFile] = models[currentFile].getValue();
  }
  currentFile = path;

  // Get or create Monaco model for this file
  if (!models[path]) {
    var uri  = skillEntryToMonacoUri(path);
    var lang = getLang(path);
    models[path] = monaco.editor.createModel(edits[path] || '', lang, uri);
  }
  monacoEditor.setModel(models[path]);
  monacoEditor.focus();

  // Update word wrap per language
  var lang = getLang(path);
  monacoEditor.updateOptions({ wordWrap: (lang === 'markdown' || lang === 'plaintext') ? 'on' : 'off' });

  document.getElementById('current-file-label').textContent = path;
  document.getElementById('lang-badge').textContent = lang;
  document.getElementById('pane-filename') && (document.getElementById('pane-filename').textContent = path);

  buildTree(path);
  updatePreview();
  updateFileActionButtons();
}

// ── PREVIEW ────────────────────────────────────────────
function updatePreview() {
  if (!monacoEditor) return;
  var val  = monacoEditor.getValue();
  var ext  = currentFile ? currentFile.split('.').pop().toLowerCase() : 'md';
  var prev = document.getElementById('ed-preview');
  if (ext === 'md' || ext === 'txt') {
    prev.innerHTML = marked.parse(val || '');
    processMermaid(prev);
  } else {
    prev.innerHTML = '<pre style="white-space:pre-wrap;font-size:.82rem;font-family:Consolas,monospace">' + esc(val) + '</pre>';
  }
}

// ── TREE ───────────────────────────────────────────────
function buildTree(active) {
  var wrap = document.getElementById('tree-wrap');
  wrap.innerHTML = '';
  var allPaths = getAllPaths();
  var pathCount = allPaths.length;
  var tree = {};
  allPaths.forEach(function(path) {
    var parts = path.split('/');
    var node  = tree;
    for (var i = 0; i < parts.length - 1; i++) {
      if (!node[parts[i]]) node[parts[i]] = {};
      node = node[parts[i]];
    }
    node['__f__' + parts[parts.length - 1]] = path;
  });
  renderNode(tree, 0, wrap, active, pathCount);
}

function renderNode(node, depth, wrap, active, pathCount) {
  var keys    = Object.keys(node).sort();
  var folders = keys.filter(function(k) { return !k.startsWith('__f__'); });
  var files   = keys.filter(function(k) { return  k.startsWith('__f__'); });
  folders.forEach(function(k) {
    var el = document.createElement('div');
    el.className = 'tree-item folder';
    el.innerHTML = indent(depth) + '<span class="ti">📁</span><span>' + esc(k) + '/</span>';
    wrap.appendChild(el);
    renderNode(node[k], depth + 1, wrap, active, pathCount);
  });
  files.forEach(function(k) {
    var fp = node[k], fname = k.replace('__f__', '');
    var row = document.createElement('div');
    row.className = 'tree-row';
    var el = document.createElement('div');
    el.className = 'tree-item' + (fp === active ? ' active' : '');
    el.innerHTML = indent(depth) + '<span class="ti">' + fIcon(fname) + '</span><span style="overflow:hidden;text-overflow:ellipsis;flex:1;min-width:0">' + esc(fname) + '</span>';
    el.onclick = function() { selectFile(fp); };
    var ops = document.createElement('div');
    ops.className = 'tree-item-ops';
    var btnRen = document.createElement('button');
    btnRen.type = 'button';
    btnRen.className = 'tree-op-btn';
    btnRen.title = 'Byt namn / flytta';
    btnRen.setAttribute('aria-label', 'Byt namn');
    btnRen.textContent = '✏️';
    btnRen.onclick = function(e) { e.stopPropagation(); renameMoveFile(fp); };
    var btnDel = document.createElement('button');
    btnDel.type = 'button';
    btnDel.className = 'tree-op-btn';
    btnDel.title = 'Ta bort fil';
    btnDel.setAttribute('aria-label', 'Ta bort');
    btnDel.textContent = '🗑';
    btnDel.onclick = function(e) { e.stopPropagation(); removeFile(fp); };
    btnDel.disabled = pathCount <= 1;
    ops.appendChild(btnRen);
    ops.appendChild(btnDel);
    row.appendChild(el);
    row.appendChild(ops);
    wrap.appendChild(row);
  });
}

function indent(d) { return '<span class="tree-indent" style="width:' + (d*13) + 'px"></span>'; }
function fIcon(name) {
  var ext = (name.split('.').pop() || '').toLowerCase();
  return {md:'📄',txt:'📝',json:'📋',yml:'⚙️',yaml:'⚙️',js:'📜',ts:'📜',py:'🐍',sh:'🖥️',css:'🎨',html:'🌐',xml:'📰',csv:'📊'}[ext] || '📎';
}

// ── GET ALL PATHS (models + unopened edits) ────────────
function getAllPaths() {
  var paths = {};
  Object.keys(initialFiles).forEach(function(p) {
    if (!removedFromArchive.has(p)) paths[p] = true;
  });
  Object.keys(edits).forEach(function(p) { paths[p] = true; });
  Object.keys(models).forEach(function(p) { paths[p] = true; });
  return Object.keys(paths).filter(function(p) { return !removedFromArchive.has(p); }).sort();
}

function getAllEdits() {
  var result = {};
  // Start from initial
  Object.assign(result, edits);
  // Override with model values (in case files were opened & edited)
  Object.keys(models).forEach(function(p) {
    result[p] = models[p].getValue();
  });
  return result;
}

// ── SAVE ───────────────────────────────────────────────
function saveSkill() {
  var name = document.getElementById('skill-name-input').value.trim();
  if (!name) {
    alert('Ange ett filnamn för skill-filen.');
    document.getElementById('skill-name-input').focus();
    return;
  }
  var all = getAllEdits();
  if (Object.keys(all).length === 0) { alert('Det finns inga filer att spara.'); return; }
  document.getElementById('form-skill-name').value  = name;
  document.getElementById('form-files-json').value  = JSON.stringify(all);
  document.getElementById('skill-form').submit();
}

// ── ADD FILE (+ Fil öppnar modal; denna anropas från "Ange sökväg…") ──
function addFileFromPrompt() {
  var name = prompt('Nytt filnamn i arkivet\n(t.ex. references/README.md):');
  if (!name) return;
  name = normalizeArchivePath(name, currentFile);
  if (!name) {
    alert('Ogiltigt filnamn.');
    return;
  }
  if (getAllPaths().indexOf(name) !== -1) {
    closeSkillStructureModal();
    selectFile(name);
    return;
  }
  removedFromArchive.delete(name);
  edits[name] = '';
  buildTree(name);
  selectFile(name);
  closeSkillStructureModal();
}

// ── STANDARDMAPPAR (Cursor-liknande skill-struktur) ────
var SKILL_STRUCTURE_TREE_DIAGRAM = [
  'your-skill-name/',
  '├── SKILL.md              # Huvudfil – YAML frontmatter + instruktioner',
  '├── references/           # Extra markdown, API-guider, exempel (laddas vid behov)',
  '│   ├── api-guide.md',
  '│   ├── examples.md',
  '│   └── troubleshooting.md',
  '├── docs/                 # Alternativ till references/ – samma typ av material',
  '│   ├── CONCEPTS.md',
  '│   └── CLI-REFERENCE.md',
  '├── scripts/              # Python/shell – körs via bash; laddas ej in i context som standard',
  '│   ├── generate.py',
  '│   └── validate.sh',
  '├── templates/            # Mallar för nya filer / strukturerade prompts (vid behov)',
  '│   └── report-template.md',
  '├── workflows/            # Steg-för-steg arbetsflöden (vid behov)',
  '│   └── workflow-a.md',
  '└── assets/               # Bilder, JSON-konfig m.m. (vid behov)',
  '    └── config.json',
].join('\n');

var SKILL_STRUCTURE_GROUPS = [
  {
    title: 'references/',
    syfte: 'Extra markdown-dokumentation, API-guider och exempel.',
    laddas: 'Vid behov (on-demand).',
    files: ['references/api-guide.md', 'references/examples.md', 'references/troubleshooting.md'],
  },
  {
    title: 'docs/',
    syfte: 'Samma roll som references/ — välj en mapp eller använd båda efter behov.',
    laddas: 'Vid behov (on-demand).',
    files: ['docs/CONCEPTS.md', 'docs/CLI-REFERENCE.md'],
  },
  {
    title: 'scripts/',
    syfte: 'Python- och shell-skript som agenten kan köra via bash.',
    laddas: 'Körs vid behov; koden laddas inte in i kontexten om du inte öppnar filen.',
    files: ['scripts/generate.py', 'scripts/validate.sh'],
  },
  {
    title: 'templates/',
    syfte: 'Mallar för att generera nya filer eller strukturerade prompts.',
    laddas: 'Vid behov (on-demand).',
    files: ['templates/report-template.md'],
  },
  {
    title: 'workflows/',
    syfte: 'Steg-för-steg arbetsflöden och procedurer.',
    laddas: 'Vid behov (on-demand).',
    files: ['workflows/workflow-a.md'],
  },
  {
    title: 'assets/',
    syfte: 'Statiska filer: bilder, konfigurationsfiler m.m.',
    laddas: 'Vid behov (on-demand).',
    files: ['assets/config.json'],
  },
];

function defaultContentForStructurePath(path) {
  var base = (path.split('/').pop() || path || '').replace(/\.[^.]+$/, '');
  var ext = (path.split('.').pop() || '').toLowerCase();
  if (ext === 'md') {
    return '# ' + base.replace(/[-_]/g, ' ') + '\n\n';
  }
  if (ext === 'py') {
    return '#!/usr/bin/env python3\n# -*- coding: utf-8 -*-\n\n"""' + path + '"""\n\n';
  }
  if (ext === 'sh' || ext === 'bash') {
    return '#!/usr/bin/env bash\nset -euo pipefail\n\n';
  }
  if (ext === 'json') {
    return '{}\n';
  }
  return '';
}

function getAllSkillStructurePaths() {
  var out = [];
  SKILL_STRUCTURE_GROUPS.forEach(function(g) {
    g.files.forEach(function(f) {
      out.push(f);
    });
  });
  return out;
}

function addFilesFromPreset(paths, closeModal) {
  var added = [];
  paths.forEach(function(p) {
    var name = normalizeArchivePath(p);
    if (!name) return;
    if (getAllPaths().indexOf(name) !== -1) return;
    removedFromArchive.delete(name);
    edits[name] = defaultContentForStructurePath(name);
    added.push(name);
  });
  if (added.length === 0) {
    alert('Alla dessa filer finns redan i arkivet.');
    return;
  }
  buildTree(added[added.length - 1]);
  selectFile(added[added.length - 1]);
  if (closeModal) closeSkillStructureModal();
}

function addSkillStructureGroup(index) {
  var g = SKILL_STRUCTURE_GROUPS[index];
  if (!g) return;
  addFilesFromPreset(g.files, false);
}

function addAllSkillStructureFiles() {
  addFilesFromPreset(getAllSkillStructurePaths(), true);
}

function buildSkillStructureGroupsUI() {
  var container = document.getElementById('skill-structure-groups');
  if (!container) return;
  container.innerHTML = '';
  var diagram = document.getElementById('skill-structure-tree-diagram');
  if (diagram) diagram.textContent = SKILL_STRUCTURE_TREE_DIAGRAM;
  SKILL_STRUCTURE_GROUPS.forEach(function(g, i) {
    var wrap = document.createElement('div');
    wrap.className = 'structure-group';
    var h = document.createElement('div');
    h.className = 'structure-group-h';
    h.textContent = g.title;
    var d = document.createElement('div');
    d.className = 'structure-group-d';
    var lineS = document.createElement('p');
    lineS.className = 'structure-group-line';
    var sStrong = document.createElement('strong');
    sStrong.textContent = 'Syfte: ';
    lineS.appendChild(sStrong);
    lineS.appendChild(document.createTextNode(g.syfte));
    var lineL = document.createElement('p');
    lineL.className = 'structure-group-line';
    var lStrong = document.createElement('strong');
    lStrong.textContent = 'Laddas: ';
    lineL.appendChild(lStrong);
    lineL.appendChild(document.createTextNode(g.laddas));
    d.appendChild(lineS);
    d.appendChild(lineL);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-xs btn-secondary';
    btn.textContent = 'Lägg till ' + g.title.replace(/\/?$/, '');
    btn.onclick = function() {
      addSkillStructureGroup(i);
    };
    wrap.appendChild(h);
    wrap.appendChild(d);
    wrap.appendChild(btn);
    container.appendChild(wrap);
  });
}

function openAddFileModal() {
  buildSkillStructureGroupsUI();
  document.getElementById('skill-structure-modal').classList.remove('hidden');
}

function closeSkillStructureModal() {
  document.getElementById('skill-structure-modal').classList.add('hidden');
}

// ── REMOVE / RENAME FILE ───────────────────────────────
function removeFile(path) {
  var all = getAllPaths();
  if (all.length <= 1) {
    alert('Arkivet måste innehålla minst en fil.');
    return;
  }
  if (!confirm('Ta bort filen «' + path + '» från arkivet?')) return;
  var next = all.filter(function(p) { return p !== path; })[0];
  removedFromArchive.add(path);
  delete edits[path];
  if (models[path]) {
    models[path].dispose();
    delete models[path];
  }
  if (currentFile === path) {
    selectFile(next);
  } else {
    buildTree(currentFile);
    updateFileActionButtons();
  }
}

function renameMoveFile(oldPath) {
  var name = prompt('Ny sökväg i arkivet (nytt namn eller mapp, t.ex. docs/guide.md):', oldPath);
  if (name == null) return;
  name = normalizeArchivePath(name, oldPath);
  if (!name) { alert('Ogiltig sökväg.'); return; }
  if (name === oldPath) return;
  if (getAllPaths().indexOf(name) !== -1) {
    alert('Det finns redan en fil med den sökvägen.');
    return;
  }
  var content = '';
  if (models[oldPath]) {
    content = models[oldPath].getValue();
  } else if (edits[oldPath] !== undefined) {
    content = edits[oldPath];
  } else if (initialFiles[oldPath] !== undefined) {
    content = initialFiles[oldPath];
  }
  removedFromArchive.add(oldPath);
  delete edits[oldPath];
  if (models[oldPath]) {
    models[oldPath].dispose();
    delete models[oldPath];
  }
  removedFromArchive.delete(name);
  edits[name] = content;
  var open = currentFile === oldPath;
  if (open) {
    currentFile = name;
  }
  buildTree(open ? name : currentFile);
  if (open) {
    selectFile(name);
  } else {
    updateFileActionButtons();
  }
}

// ── TEMPLATE ───────────────────────────────────────────
// Samma YAML + brödtext som vid ny skill (SKILL.md); övriga filer får en enkel markdown-mall.
var SKILL_MD_TEMPLATE = [
  '---',
  'name:',
  'title:',
  'description:',
  'author:',
  'version: 1.0.0',
  'tags:',
  '---',
  '',
  '# Ny skill',
  '',
  '## Syfte',
  '',
  'Beskriv vad denna skill gör och när den används.',
  '',
  '## Instruktioner',
  '',
  '1. Steg ett',
  '2. Steg två',
  '',
  '## Exempel',
  '',
  '```',
  '# Exempel',
  '```',
  '',
].join('\n');

function insertTemplate() {
  if (!monacoEditor) return;
  if (monacoEditor.getValue().trim() && !confirm('Ersätt innehållet med mallen?')) return;
  var fname = (currentFile || '').split('/').pop() || '';
  var isSkillMd = /^SKILL\.md$/i.test(fname);
  if (isSkillMd) {
    monacoEditor.setValue(SKILL_MD_TEMPLATE);
  } else {
    monacoEditor.setValue([
      '# Titel','','## Syfte','',
      'Beskriv vad denna skill gör och när den används.','',
      '## Instruktioner','','1. Steg ett','2. Steg två','3. Steg tre','',
      '## Exempel','','```','# Exempelkod','```','','## Anteckningar','','- Viktig notering',
    ].join('\n'));
  }
  monacoEditor.focus();
}

// ── FORMAT ─────────────────────────────────────────────
function formatDoc() {
  if (!monacoEditor) return;
  monacoEditor.getAction('editor.action.formatDocument')?.run();
}

// ── HELP MODAL ─────────────────────────────────────────
function openHelp() {
  var el = document.getElementById('help-content');
  if (!el._rendered) {
    el.innerHTML = marked.parse(helpMd || '_Ingen hjälptext hittades._');
    el._rendered = true;
  }
  document.getElementById('help-modal').classList.remove('hidden');
}
function closeHelp() {
  document.getElementById('help-modal').classList.add('hidden');
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeHelp();
    closeSkillStructureModal();
  }
});

// ── UTILS ──────────────────────────────────────────────
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
