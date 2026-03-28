<?php
declare(strict_types=1);

define('CONTENT_DIR', __DIR__ . '/content/');
define('APP_NAME', 'Skill Manager');

const TEXT_EXTS = ['md','txt','json','yml','yaml','js','ts','jsx','tsx','py','sh','bash','css','html','xml','csv','toml','ini','cfg','conf','rst'];
const IMG_EXTS  = ['png','jpg','jpeg','gif','svg','webp'];

/* ── HELPERS ────────────────────────────────────────── */

function sanitize_filename(string $name): string {
    $name = pathinfo(basename($name), PATHINFO_FILENAME);
    $name = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $name);
    $name = trim($name, '-') ?: 'untitled';
    return $name . '.skill';
}

function validate_file_param(string $file): ?string {
    $file = basename($file);
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.skill$/', $file)) return null;
    $path = CONTENT_DIR . $file;
    return file_exists($path) ? $path : null;
}

function sanitize_entry(string $name): string {
    // Prevent path traversal in ZIP entries
    $name = str_replace(['\\', '..'], ['/', ''], $name);
    return ltrim($name, '/');
}

/**
 * Uppladdade .skill / .zip får endast innehålla .md- och .txt-filer.
 *
 * @return null om OK, annars felmeddelande
 */
function validate_skill_upload_archive(string $zipPath): ?string {
    if (!class_exists('ZipArchive')) {
        return 'ZipArchive saknas på servern.';
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return 'Kunde inte läsa arkivet.';
    }
    $allowed = ['md', 'txt'];
    $fileCount = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (str_ends_with($name, '/')) {
            continue;
        }
        $fileCount++;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            $zip->close();
            return 'Otillåten fil i arkivet: ' . $name . ' (endast .md och .txt tillåts vid uppladdning).';
        }
    }
    $zip->close();
    if ($fileCount === 0) {
        return 'Arkivet innehåller inga filer.';
    }
    return null;
}

/**
 * Skapar en .skill-fil i $destSkillPath från en uppladdad .zip (endast .md/.txt kopieras).
 *
 * @return null om OK, annars felmeddelande (vid fel raderas $destSkillPath om den skapades)
 */
function create_skill_from_uploaded_zip(string $tmpZipPath, string $destSkillPath): ?string {
    $err = validate_skill_upload_archive($tmpZipPath);
    if ($err !== null) {
        return $err;
    }
    $allowed = ['md', 'txt'];
    $zipIn = new ZipArchive();
    if ($zipIn->open($tmpZipPath) !== true) {
        return 'Kunde inte läsa zip-filen.';
    }
    $zipOut = new ZipArchive();
    if ($zipOut->open($destSkillPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $zipIn->close();
        return 'Kunde inte skapa .skill-fil.';
    }
    $added = 0;
    for ($i = 0; $i < $zipIn->numFiles; $i++) {
        $name = (string)$zipIn->getNameIndex($i);
        if (str_ends_with($name, '/')) {
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }
        $safe = sanitize_entry($name);
        if ($safe === '') {
            continue;
        }
        if ($zipOut->addFromString($safe, (string)$zipIn->getFromIndex($i))) {
            $added++;
        }
    }
    $zipOut->close();
    $zipIn->close();
    if ($added === 0) {
        if (is_file($destSkillPath)) {
            unlink($destSkillPath);
        }
        return 'Inga .md- eller .txt-filer kunde packas.';
    }
    return null;
}

function parse_frontmatter(string $content): array {
    $meta = [];
    $body = $content;
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)/s', $content, $m)) {
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^(\w+):\s*(.*)$/', trim($line), $kv)) {
                $meta[$kv[1]] = trim($kv[2], '"\'');
            }
        }
        $body = ltrim($m[2]);
    }
    return ['meta' => $meta, 'body' => $body];
}

function get_skill_meta(string $zipPath): array {
    $info = ['title' => '', 'description' => '', 'name' => '', 'tags' => ''];
    if (!class_exists('ZipArchive')) return $info;
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return $info;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('/(?:^|\/)SKILL\.md$/i', (string)$name)) {
            $content = (string)$zip->getFromIndex($i);
            $parsed  = parse_frontmatter($content);
            $info    = array_merge($info, $parsed['meta']);
            // Fallback: first H1 as title
            if (empty($info['title']) && preg_match('/^#\s+(.+)$/m', $parsed['body'], $m)) {
                $info['title'] = trim($m[1]);
            }
            break;
        }
    }
    $zip->close();
    if (empty($info['title'])) {
        $info['title'] = pathinfo(basename($zipPath), PATHINFO_FILENAME);
    }
    return $info;
}

function get_skills(): array {
    if (!is_dir(CONTENT_DIR)) return [];
    $files = glob(CONTENT_DIR . '*.skill') ?: [];
    $skills = [];
    foreach ($files as $f) {
        $meta     = get_skill_meta($f);
        $numFiles = 0;
        $zip = new ZipArchive();
        if ($zip->open($f) === true) {
            // Count non-directory entries
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string)$zip->getNameIndex($i);
                if (!str_ends_with($n, '/')) $numFiles++;
            }
            $zip->close();
        }
        $skills[] = [
            'filename' => basename($f),
            'size'     => (int)filesize($f),
            'modified' => (int)filemtime($f),
            'meta'     => $meta,
            'numFiles' => $numFiles,
        ];
    }
    usort($skills, fn($a, $b) => $b['modified'] - $a['modified']);
    return $skills;
}

/**
 * Read all files from a ZIP into an array.
 * Returns ['path' => ['type'=>'text'|'image'|'binary', 'content'=>..., 'size'=>...]]
 */
function read_zip_files(string $zipPath): array {
    $entries = [];
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return $entries;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = (string)$stat['name'];
        if (str_ends_with($name, '/')) continue;
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = (int)$stat['size'];
        if (in_array($ext, TEXT_EXTS)) {
            $entries[$name] = ['type' => 'text', 'content' => (string)$zip->getFromIndex($i), 'size' => $size];
        } elseif (in_array($ext, IMG_EXTS) && $size < 512 * 1024) {
            $raw  = (string)$zip->getFromIndex($i);
            $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $entries[$name] = ['type' => 'image', 'content' => "data:{$mime};base64," . base64_encode($raw), 'size' => $size];
        } else {
            $entries[$name] = ['type' => 'binary', 'content' => '', 'size' => $size];
        }
    }
    $zip->close();
    return $entries;
}

function fmt_size(int $bytes): string {
    if ($bytes < 1024)        return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 1) . ' MB';
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Favicon; $pathPrefix '' från projektrot, '../' från view/ eller edit/ */
function favicon_link(string $pathPrefix = ''): void {
    $href = $pathPrefix . 'favicon.ico';
    echo '<link rel="icon" href="' . h($href) . '" type="image/x-icon" sizes="any">' . "\n";
}

/* ── CSS ─────────────────────────────────────────────── */

function common_css(): void { ?>
<style>
:root{
  --accent:#0077bc;--accent-d:#005fa3;
  --bg:#FFFFFE;--bg-footer:#F5F5F5;--bg-nav:#F4F9FC;--bg-info:#F2F9F9;--bg-card:#ffffff;
  --text:#333333;--text-2:#6E6E6E;--link:#005799;--border:#979797;--border-l:#d1d9dc;
  --shadow:0 1px 4px rgba(0,119,188,.10),0 2px 12px rgba(0,0,0,.07);
  --r:4px;--r-lg:8px;
  --turquoise:#008391;--green:#5a8b3b;--yellow:#ffd666;--red:#d24723;--darkBlue:#3f5564;
  --sidebar-w:240px;--toolbar-h:46px;
}
[data-theme="dark"]{
  --bg:#1F1F1F;--bg-footer:#121212;--bg-nav:#1a1a1a;--bg-info:#242424;--bg-card:#2a2a2a;
  --text:#FFFFFF;--text-2:#b0b8bb;--link:#479EF5;--border:#555;--border-l:#3a3a3a;
  --shadow:0 1px 4px rgba(0,0,0,.4),0 2px 12px rgba(0,0,0,.3);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:var(--text);background:var(--bg);display:flex;flex-direction:column;min-height:100vh}

/* HEADER */
.header{height:54px;background:var(--accent);display:flex;align-items:center;padding:0 18px;gap:14px;box-shadow:0 2px 8px rgba(0,0,0,.25);flex-shrink:0;z-index:100;position:relative}
.logo-mark{width:34px;height:34px;background:rgba(255,255,255,.14);border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.logo-text{color:#fff;font-weight:700;font-size:.9rem;letter-spacing:.01em}
.logo-sub{color:rgba(255,255,255,.65);font-size:.68rem;font-weight:400;display:block}
.hdr-sep{width:1px;height:26px;background:rgba(255,255,255,.22)}
.hdr-title{color:rgba(255,255,255,.88);font-size:.82rem;font-weight:500;flex:1}
.hdr-actions{display:flex;gap:5px;align-items:center}

/* HAMBURGER MENU */
.hamburger-btn{width:32px;height:32px;border-radius:var(--r);background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.28);color:#fff;cursor:pointer;display:none;flex-direction:column;align-items:center;justify-content:center;gap:3px;transition:background .15s;order:999}
.hamburger-btn:hover{background:rgba(255,255,255,.22)}
.hamburger-btn span{width:18px;height:2px;background:#fff;border-radius:1px;transition:all .3s ease}
.hamburger-btn.active span:nth-child(1){transform:rotate(-45deg) translate(-4px, 4px)}
.hamburger-btn.active span:nth-child(2){opacity:0}
.hamburger-btn.active span:nth-child(3){transform:rotate(45deg) translate(-4px, -4px)}

/* MOBILE NAVIGATION OVERLAY */
.mobile-nav-overlay{position:fixed;top:54px;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:999;display:none}
.mobile-nav{position:fixed;top:54px;left:-280px;width:280px;height:calc(100vh - 54px);background:var(--bg-nav);border-right:1px solid var(--border-l);z-index:1000;transition:left .3s ease;overflow-y:auto}
.mobile-nav.active{left:0}
.mobile-nav-header{padding:16px;border-bottom:1px solid var(--border-l);background:var(--accent);color:#fff}
.mobile-nav-title{font-weight:700;font-size:.9rem;margin-bottom:4px}
.mobile-nav-subtitle{font-size:.75rem;opacity:.8}
.mobile-nav-section{padding:12px 0}
.mobile-nav-item{display:flex;align-items:center;gap:8px;padding:10px 16px;text-decoration:none;color:var(--text);font-size:.85rem;border-bottom:1px solid var(--border-l);transition:background .2s}
.mobile-nav-item:hover{background:var(--bg)}
.mobile-nav-item:last-child{border-bottom:none}
.mobile-nav-item .icon{width:20px;text-align:center;font-size:.9rem}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:var(--r);font:inherit;font-size:.78rem;cursor:pointer;border:none;transition:background .14s,opacity .14s;text-decoration:none;white-space:nowrap;flex-shrink:0}
.btn-xs{padding:3px 8px;font-size:.72rem}
.btn-sm{padding:4px 10px;font-size:.76rem}
.btn-white{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.32)}
.btn-white:hover{background:rgba(255,255,255,.26)}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:var(--accent-d)}
.btn-secondary{background:var(--bg);color:var(--text);border:1px solid var(--border)}
.btn-secondary:hover{background:var(--bg-nav)}
.btn-success{background:var(--green);color:#fff}
.btn-success:hover{opacity:.88}
.btn-teal{background:var(--turquoise);color:#fff}
.btn-teal:hover{opacity:.88}
.btn-danger{background:var(--red);color:#fff}
.btn-danger:hover{opacity:.88}
.theme-btn{width:32px;height:32px;border-radius:var(--r);background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.28);color:#fff;font-size:.95rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
.theme-btn:hover{background:rgba(255,255,255,.22)}

/* MARKDOWN */
.md{max-width:800px}
.md h1{font-size:1.55rem;color:var(--accent);margin:0 0 12px;padding-bottom:7px;border-bottom:2px solid var(--border-l)}
.md h2{font-size:1.18rem;margin:24px 0 9px;padding-bottom:3px;border-bottom:1px solid var(--border-l)}
.md h3{font-size:1.02rem;margin:17px 0 6px}
.md h4{font-size:.95rem;font-weight:700;margin:13px 0 4px}
.md p{margin:0 0 10px}
.md ul,.md ol{margin:0 0 10px 20px}
.md li{margin-bottom:2px}
.md a{color:var(--link);text-decoration:none}
.md a:hover{color:var(--accent)}
.md strong{font-weight:700}
.md em{font-style:italic}
.md code{background:var(--bg-nav);border:1px solid var(--border-l);border-radius:3px;padding:1px 4px;font-family:'Consolas','Monaco',monospace;font-size:.83em;color:var(--darkBlue)}
[data-theme="dark"] .md code{color:#7dd3fc}
.md pre{background:var(--bg-nav);border:1px solid var(--border-l);border-radius:var(--r);padding:12px 15px;overflow-x:auto;margin:9px 0}
.md pre code{background:none;border:none;padding:0;font-size:.83rem;color:var(--text)}
.md blockquote{border-left:3px solid var(--accent);margin:9px 0;padding:6px 13px;background:var(--bg-info);border-radius:0 var(--r) var(--r) 0;color:var(--text-2)}
.md table{width:100%;border-collapse:collapse;margin:9px 0;font-size:.86rem}
.md th{background:var(--accent);color:#fff;padding:6px 10px;text-align:left;font-weight:600}
.md td{padding:6px 10px;border-bottom:1px solid var(--border-l)}
.md tr:nth-child(even) td{background:var(--bg-nav)}
.md hr{border:none;border-top:1px solid var(--border-l);margin:16px 0}

/* FRONTMATTER BOX */
.fm-box{background:var(--bg-nav);border:1px solid var(--accent);border-radius:var(--r-lg);padding:10px 14px;margin-bottom:18px;font-size:.8rem}
.fm-lbl{font-size:.68rem;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.06em;margin-bottom:7px}
.fm-row{display:flex;gap:9px;margin-bottom:2px}
.fm-k{color:var(--accent);font-weight:600;min-width:96px;flex-shrink:0}
.fm-v{color:var(--text-2);word-break:break-word}

/* MISC */
.hidden{display:none!important}
footer{background:var(--bg-footer);border-top:1px solid var(--border-l);padding:7px 18px;font-size:.72rem;color:var(--text-2);text-align:center;flex-shrink:0}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-l);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:var(--border)}

/* MOBILE RESPONSIVE STYLES */
@media(max-width: 768px) {
  .hamburger-btn{display:flex}
  .hdr-sep{display:none}
  .hdr-actions .btn{padding:4px 8px;font-size:.72rem}
  .hdr-actions .theme-btn{width:28px;height:28px;font-size:.85rem}
  .hdr-actions .btn:not(.hamburger-btn):not(.sidebar-toggle){display:none!important}
  .hdr-actions .theme-btn{display:none!important}
  .header{padding:0 12px;gap:8px}
  .logo-text{font-size:.8rem}
  .hdr-title{font-size:.76rem}
  
  /* Main content adjustments */
  .main{padding:8px 12px}
  
  /* Buttons more touch-friendly */
  .btn{min-height:36px;padding:6px 12px;font-size:.8rem}
  .btn-xs{min-height:32px;padding:5px 10px;font-size:.74rem}
  .btn-sm{min-height:34px;padding:5px 11px;font-size:.77rem}
  
  /* Toolbar improvements */
  .toolbar{gap:6px;margin-bottom:12px}
  .search-input{font-size:.8rem;padding:8px 10px 8px 32px}
  .filter-select{font-size:.8rem;padding:8px 10px}
  .upload-label{font-size:.8rem;padding:7px 12px}
}

@media(max-width: 480px) {
  .header{padding:0 8px;gap:6px}
  .logo-mark{width:30px;height:30px;font-size:1rem}
  .logo-text{font-size:.75rem}
  .logo-sub{display:none}
  .hdr-title{font-size:.72rem}
  
  .main{padding:6px 8px}
  
  .toolbar{flex-direction:column;align-items:stretch;gap:8px}
  .search-wrap{order:1}
  .filter-select{order:2}
  .upload-inline{order:3;justify-content:center}
  
  .btn{font-size:.75rem}
  .btn-xs{font-size:.7rem}
  .btn-sm{font-size:.73rem}
}
</style>
<?php }

/* ── THEME SCRIPT ────────────────────────────────────── */

function theme_script(): void { ?>
<script>
(function(){
  var t = localStorage.getItem('skill-theme') || 'light';
  document.documentElement.setAttribute('data-theme', t);
})();
function toggleTheme() {
  var t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', t);
  localStorage.setItem('skill-theme', t);
}

// Mobile navigation functionality
function toggleMobileNav() {
  var hamburger = document.querySelector('.hamburger-btn');
  var overlay = document.querySelector('.mobile-nav-overlay');
  var nav = document.querySelector('.mobile-nav');
  
  if (hamburger && overlay && nav) {
    hamburger.classList.toggle('active');
    overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
    nav.classList.toggle('active');
  }
}

function closeMobileNav() {
  var hamburger = document.querySelector('.hamburger-btn');
  var overlay = document.querySelector('.mobile-nav-overlay');
  var nav = document.querySelector('.mobile-nav');
  
  if (hamburger) hamburger.classList.remove('active');
  if (overlay) overlay.style.display = 'none';
  if (nav) nav.classList.remove('active');
}

// Sidebar toggle for view/edit pages
function toggleSidebar() {
  var sidebar = document.querySelector('.sidebar');
  var sidebarOverlay = document.querySelector('.sidebar-overlay');
  
  if (sidebar && sidebar.classList.contains('mobile-hidden')) {
    sidebar.classList.remove('mobile-hidden');
    if (sidebarOverlay) sidebarOverlay.style.display = 'block';
  } else if (sidebar) {
    sidebar.classList.add('mobile-hidden');
    if (sidebarOverlay) sidebarOverlay.style.display = 'none';
  }
}

// Initialize mobile functionality
document.addEventListener('DOMContentLoaded', function() {
  // Close mobile nav when clicking overlay
  var overlay = document.querySelector('.mobile-nav-overlay');
  if (overlay) {
    overlay.addEventListener('click', closeMobileNav);
  }
  
  // Close mobile nav when clicking nav items
  var navItems = document.querySelectorAll('.mobile-nav-item');
  navItems.forEach(function(item) {
    item.addEventListener('click', function() {
      // Small delay to allow navigation
      setTimeout(closeMobileNav, 100);
    });
  });
  
  // Handle sidebar overlay
  var sidebarOverlay = document.querySelector('.sidebar-overlay');
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function() {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.add('mobile-hidden');
        sidebarOverlay.style.display = 'none';
      }
    });
  }
  
  // Handle window resize
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
      closeMobileNav();
      var sidebar = document.querySelector('.sidebar');
      var sidebarOverlay = document.querySelector('.sidebar-overlay');
      if (sidebar) sidebar.classList.remove('mobile-hidden');
      if (sidebarOverlay) sidebarOverlay.style.display = 'none';
    }
  });
});
</script>
<?php }
