<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_common.php';

$isAuthed = skill_is_authed();

if (!is_dir(CONTENT_DIR)) {
    mkdir(CONTENT_DIR, 0755, true);
}

$msg     = '';
$msgType = 'success';

/* ── POST-hantering (kräver inloggning) ───────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAuthed) {
        skill_require_auth('login.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && isset($_FILES['skill_file'])) {
        $f = $_FILES['skill_file'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
            if ($ext === 'skill') {
                $fname  = sanitize_filename((string)$f['name']);
                $target = CONTENT_DIR . $fname;
                if (move_uploaded_file((string)$f['tmp_name'], $target)) {
                    $vErr = validate_skill_upload_archive($target);
                    if ($vErr !== null) {
                        unlink($target);
                        $msg     = $vErr;
                        $msgType = 'error';
                    } else {
                        $msg = __('index.msg_upload_ok', ['file' => h($fname)]);
                    }
                } else {
                    $msg = __('index.msg_upload_invalid');
                    $msgType = 'error';
                }
            } elseif ($ext === 'zip') {
                $fname  = sanitize_filename((string)$f['name']);
                $target = CONTENT_DIR . $fname;
                $zErr   = create_skill_from_uploaded_zip((string)$f['tmp_name'], $target);
                if ($zErr !== null) {
                    $msg     = $zErr;
                    $msgType = 'error';
                } else {
                    $msg = __('index.msg_zip_ok', ['file' => h($fname)]);
                }
            } else {
                $msg     = __('index.msg_wrong_ext');
                $msgType = 'error';
            }
        } else {
            $msg = __('index.msg_upload_err', ['code' => (string)(int)$f['error']]);
            $msgType = 'error';
        }
    }

    if ($action === 'delete') {
        $path = validate_file_param((string)($_POST['file'] ?? ''));
        if ($path && unlink($path)) {
            $msg = __('index.msg_deleted');
        } else {
            $msg = __('index.msg_delete_fail');
            $msgType = 'error';
        }
    }
}

$skills = get_skills();

// Samla alla unika taggar för filter-dropdown
$allTags = [];
foreach ($skills as $s) {
    if (!empty($s['meta']['tags'])) {
        foreach (array_map('trim', explode(',', $s['meta']['tags'])) as $t) {
            if ($t !== '') $allTags[$t] = true;
        }
    }
}
ksort($allTags);
$allTags = array_keys($allTags);

// ?tag=… för direktlänk till taggfilter (trimmas, begränsad längd)
$tagQuery = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
$tagQuery = preg_replace('/[\x00-\x1F\x7F]/u', '', $tagQuery);
if (strlen($tagQuery) > 200) {
    $tagQuery = substr($tagQuery, 0, 200);
}

/** ?sort=… — samma värden som #sort-select (kolumn + riktning) */
$sortOptions = [
    'modified-desc' => __('index.sort_modified_desc'),
    'modified-asc'  => __('index.sort_modified_asc'),
    'title-asc'     => __('index.sort_title_asc'),
    'title-desc'    => __('index.sort_title_desc'),
    'tags-asc'      => __('index.sort_tags_asc'),
    'tags-desc'     => __('index.sort_tags_desc'),
    'author-asc'    => __('index.sort_author_asc'),
    'author-desc'   => __('index.sort_author_desc'),
    'files-asc'     => __('index.sort_files_asc'),
    'files-desc'    => __('index.sort_files_desc'),
    'size-asc'      => __('index.sort_size_asc'),
    'size-desc'     => __('index.sort_size_desc'),
];
$sortQuery = isset($_GET['sort']) ? trim((string)$_GET['sort']) : '';
$sortQuery = strtolower(preg_replace('/[^a-z0-9\-]/', '', $sortQuery));
if ($sortQuery === '' || !isset($sortOptions[$sortQuery])) {
    $sortQuery = 'modified-desc';
}

// Bygg JSON för klientsidan
$skillsJson = array_map(fn($s) => [
    'filename' => $s['filename'],
    'title'    => $s['meta']['title'] ?? $s['meta']['name'] ?? pathinfo($s['filename'], PATHINFO_FILENAME),
    'desc'     => $s['meta']['description'] ?? '',
    'tags'     => $s['meta']['tags'] ?? '',
    'author'   => $s['meta']['author'] ?? '',
    'version'  => $s['meta']['version'] ?? '',
    'files'    => $s['numFiles'] ?? 0,
    'size'     => fmt_size($s['size']),
    'modified' => date('Y-m-d H:i', $s['modified']),
    'ts'       => $s['modified'],
], $skills);
?>
<!DOCTYPE html>
<html lang="<?= h(skill_lang_html_lang()) ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php favicon_link(); ?>
<title><?= h(APP_NAME) ?></title>
<?php theme_script(); ?>
<?php common_css(); ?>
<style>
html, body { height: auto; overflow: auto; }
.main { flex: 1; padding: 22px 24px; max-width: 1100px; margin: 0 auto; width: 100%; }

/* MEDDELANDEN */
.msg { padding: 10px 16px; border-radius: var(--r); margin-bottom: 18px; font-size: .85rem; font-weight: 500; }
.msg.success { background: #e6f4ec; color: #2d6a3f; border: 1px solid #a3d4b0; }
.msg.error   { background: #fdecea; color: #8b1a1a; border: 1px solid #f5bbb8; }
[data-theme="dark"] .msg.success { background: #1a3526; color: #6fcf97; border-color: #2a5438; }
[data-theme="dark"] .msg.error   { background: #3b1111; color: #f28b82; border-color: #5c2020; }

/* TOOLBAR (sök + filter + upload) */
.toolbar {
  display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
  margin-bottom: 14px;
}
.search-wrap { position: relative; flex: 1; min-width: 180px; }
.search-ico { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); color: var(--text-2); font-size: .82rem; pointer-events: none; }
.search-input { width: 100%; padding: 7px 10px 7px 30px; border: 1px solid var(--border-l); border-radius: var(--r); font: inherit; font-size: .82rem; background: var(--bg); color: var(--text); }
.search-input:focus { outline: 2px solid var(--accent); border-color: transparent; }
.filter-select { padding: 7px 10px; border: 1px solid var(--border-l); border-radius: var(--r); font: inherit; font-size: .8rem; background: var(--bg); color: var(--text); cursor: pointer; }
.filter-select:focus { outline: 2px solid var(--accent); border-color: transparent; }
.sort-btn { padding: 7px 10px; border: 1px solid var(--border-l); border-radius: var(--r); font: inherit; font-size: .78rem; background: var(--bg); color: var(--text-2); cursor: pointer; white-space: nowrap; }
.sort-btn.active { background: var(--bg-nav); color: var(--text); }
.sort-btn:hover { background: var(--bg-nav); }

/* UPLOAD INLINE */
.upload-inline { display: flex; align-items: center; gap: 6px; }
.upload-inline input[type=file] { display: none; }
.upload-label { display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; border-radius: var(--r); font: inherit; font-size: .78rem; cursor: pointer; background: var(--bg); color: var(--text); border: 1px solid var(--border); transition: background .14s; white-space: nowrap; }
.upload-label:hover { background: var(--bg-nav); }

/* RESULTAT-RAD */
.result-bar { font-size: .75rem; color: var(--text-2); margin-bottom: 8px; padding: 0 2px; }

/* TABELL */
.skill-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
.skill-table thead th {
  padding: 8px 12px; text-align: left; font-size: .7rem; font-weight: 700;
  color: var(--text-2); text-transform: uppercase; letter-spacing: .05em;
  border-bottom: 2px solid var(--border-l); background: var(--bg-nav);
  white-space: nowrap; cursor: pointer; user-select: none;
  position: sticky; top: 0; z-index: 1;
}
.skill-table thead th:hover { color: var(--accent); }
.skill-table thead th .sort-arrow { margin-left: 4px; opacity: .4; font-style: normal; }
.skill-table thead th.sorted .sort-arrow { opacity: 1; color: var(--accent); }
.skill-table tbody tr { border-bottom: 1px solid var(--border-l); transition: background .1s; }
.skill-table tbody tr:hover { background: rgba(0,119,188,.05); }
.skill-table tbody tr.hidden-row { display: none; }
.skill-table td { padding: 9px 12px; vertical-align: middle; }

.col-title { min-width: 160px; }
.col-title a { color: var(--accent); font-weight: 600; text-decoration: none; }
.col-title .row-desc { font-size: .74rem; color: var(--text-2); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px; }
.col-tags { min-width: 100px; }
.tag { display: inline-block; background: var(--bg-info); border: 1px solid var(--border-l); border-radius: 3px; padding: 1px 6px; font-size: .67rem; color: var(--text-2); margin: 1px; cursor: pointer; text-decoration: none; }
a.tag { color: var(--text-2); }
.tag:hover { border-color: var(--accent); color: var(--accent); }
.col-meta { color: var(--text-2); white-space: nowrap; font-size: .76rem; }
.col-actions { white-space: nowrap; text-align: right; }
.col-actions .actions-wrap { display: flex; gap: 4px; justify-content: flex-end; align-items: center; }
/* En knapp med dropdown för nedladdning */
.split-dl { position: relative; display: inline-block; }
.split-dl > summary { list-style: none; cursor: pointer; }
.split-dl > summary::-webkit-details-marker { display: none; }
.split-dl-btn { margin: 0 !important; }
.split-dl[open] > .split-dl-btn { background: var(--bg-nav) !important; }
.split-dl-menu {
  position: absolute; right: 0; top: calc(100% + 3px); z-index: 200;
  min-width: 7.5rem; background: var(--bg); border: 1px solid var(--border-l);
  border-radius: var(--r); box-shadow: var(--shadow); padding: 4px 0;
}
.split-dl-opt { display: block; width: 100%; text-align: left; padding: 6px 12px; text-decoration: none; font: inherit; font-size: .76rem; color: var(--text); }
.split-dl-opt:hover { background: var(--bg-nav); color: var(--accent); }

/* TOM STATE */
.empty-state { text-align: center; padding: 48px 24px; color: var(--text-2); }
.empty-state .ei { font-size: 3rem; margin-bottom: 14px; }
.empty-state p { font-size: .88rem; }
.no-results { text-align: center; padding: 28px; color: var(--text-2); font-size: .85rem; }

@media(max-width: 700px) {
  .col-meta, .col-tags { display: none; }
  .toolbar { gap: 6px; }
  .main { padding: 12px; }
  .skill-table td { padding: 6px 8px; }
  .col-title a { font-size: .85rem; }
  .col-actions .actions-wrap { gap: 3px; }
  .split-dl-btn { padding: 2px 6px !important; font-size: .7rem !important; }
}
</style>
</head>
<body>

<header class="header">
  <a href="./" style="display:flex;align-items:center;gap:10px;text-decoration:none">
    <div class="logo-mark">📘</div>
    <div class="logo-text"><?= h(APP_NAME) ?><span class="logo-sub"><?= h(__('common.logo_sub')) ?></span></div>
  </a>
  <div class="hdr-sep"></div>
  <div class="hdr-title"><?= $isAuthed ? h(__('index.hdr_manage')) : h(__('index.hdr_overview')) ?></div>
  <div class="hdr-actions">
    <?php if ($isAuthed): ?>
    <a href="edit/" class="btn btn-white btn-sm">✏️ <?= h(__('index.btn_new_skill')) ?></a>
    <a href="download_content.php" class="btn btn-white btn-sm" title="<?= h(__('index.btn_all_content_title')) ?>">⬇ <?= h(__('index.btn_all_content')) ?></a>
    <a href="logout.php" class="btn btn-white btn-sm" onclick="return confirm(<?= json_encode(__('common.confirm_logout'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)">🔓 <?= h(__('common.logout')) ?></a>
    <?php else: ?>
    <a href="login.php" class="btn btn-white btn-sm">🔐 <?= h(__('common.login')) ?></a>
    <?php endif; ?>
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
    <a href="./" class="mobile-nav-item">
      <span class="icon">🏠</span>
      <span><?= h(__('common.home')) ?></span>
    </a>
    <?php if ($isAuthed): ?>
    <a href="edit/" class="mobile-nav-item">
      <span class="icon">✏️</span>
      <span><?= h(__('index.btn_new_skill')) ?></span>
    </a>
    <a href="download_content.php" class="mobile-nav-item">
      <span class="icon">⬇</span>
      <span><?= h(__('index.btn_all_content')) ?> (zip)</span>
    </a>
    <a href="logout.php" class="mobile-nav-item" onclick="return confirm(<?= json_encode(__('common.confirm_logout'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)">
      <span class="icon">🔓</span>
      <span><?= h(__('common.logout')) ?></span>
    </a>
    <?php else: ?>
    <a href="login.php" class="mobile-nav-item">
      <span class="icon">🔐</span>
      <span><?= h(__('common.login')) ?></span>
    </a>
    <?php endif; ?>
    <a href="javascript:void(0)" onclick="toggleTheme(); closeMobileNav();" class="mobile-nav-item">
      <span class="icon">🌓</span>
      <span><?= h(__('common.theme_toggle')) ?></span>
    </a>
  </div>
</nav>

<div class="main">

  <?php if ($msg): ?>
  <div class="msg <?= $msgType ?>"><?= $msg ?></div>
  <?php endif; ?>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <div class="search-wrap">
      <span class="search-ico">🔍</span>
      <input type="text" class="search-input" id="search-input"
             placeholder="<?= h(__('index.search_placeholder')) ?>"
             oninput="applyFilters()">
    </div>

    <select class="filter-select" id="tag-filter" onchange="applyFilters()">
      <option value=""><?= h(__('index.filter_all_tags')) ?></option>
      <?php foreach ($allTags as $tag): ?>
      <option value="<?= h($tag) ?>"><?= h($tag) ?></option>
      <?php endforeach; ?>
      <?php
        if ($tagQuery !== '') {
            $tagInList = false;
            foreach ($allTags as $t) {
                if (strcasecmp($t, $tagQuery) === 0) {
                    $tagInList = true;
                    break;
                }
            }
            if (!$tagInList) {
                echo '<option value="' . h($tagQuery) . '">' . h($tagQuery) . "</option>\n";
            }
        }
      ?>
    </select>

    <select class="filter-select" id="sort-select" onchange="applyFilters()">
      <?php foreach ($sortOptions as $val => $label): ?>
      <option value="<?= h($val) ?>"<?= $sortQuery === $val ? ' selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>

    <?php if ($isAuthed): ?>
    <!-- UPLOAD INLINE -->
    <form method="POST" enctype="multipart/form-data" id="upload-form" class="upload-inline">
      <input type="hidden" name="action" value="upload">
      <input type="file" name="skill_file" id="file-input" accept=".skill,.zip,application/zip"
             onchange="document.getElementById('upload-form').submit()">
      <label for="file-input" class="upload-label" title="<?= h(__('index.upload_title')) ?>">⬆ <?= h(__('index.upload_label')) ?></label>
    </form>
    <?php endif; ?>
  </div>

  <!-- RESULTAT-RAD -->
  <div class="result-bar" id="result-bar"></div>

  <?php if (empty($skills)): ?>
  <div class="empty-state">
    <div class="ei">🗂️</div>
    <p><?= __('index.empty') ?><?php if ($isAuthed): ?><br><?= __('index.empty_upload') ?><?php endif; ?></p>
  </div>
  <?php else: ?>

  <table class="skill-table" id="skill-table">
    <thead>
      <tr>
        <th class="col-title"   onclick="sortBy('title')">   <?= h(__('index.col_title')) ?>       <i class="sort-arrow" id="arr-title">↕</i></th>
        <th class="col-tags"    onclick="sortBy('tags')">    <?= h(__('index.col_tags')) ?>      <i class="sort-arrow" id="arr-tags">↕</i></th>
        <th class="col-meta"    onclick="sortBy('author')">  <?= h(__('index.col_author')) ?>  <i class="sort-arrow" id="arr-author">↕</i></th>
        <th class="col-meta"    onclick="sortBy('files')">   <?= h(__('index.col_files')) ?>       <i class="sort-arrow" id="arr-files">↕</i></th>
        <th class="col-meta"    onclick="sortBy('size')">    <?= h(__('index.col_size')) ?>     <i class="sort-arrow" id="arr-size">↕</i></th>
        <th class="col-meta"    onclick="sortBy('modified')"><?= h(__('index.col_modified')) ?>      <i class="sort-arrow" id="arr-modified">↕</i></th>
        <th class="col-actions"><?= h(__('index.col_actions')) ?></th>
      </tr>
    </thead>
    <tbody id="skill-tbody">
    <?php foreach ($skills as $s):
      $fname    = $s['filename'];
      $title    = $s['meta']['title'] ?? $s['meta']['name'] ?? pathinfo($fname, PATHINFO_FILENAME);
      $desc     = $s['meta']['description'] ?? '';
      $tags     = $s['meta']['tags'] ?? '';
      $author   = $s['meta']['author'] ?? '';
      $version  = $s['meta']['version'] ?? '';
      $numFiles = $s['numFiles'] ?? 0;
      $date     = date('Y-m-d H:i', $s['modified']);
    ?>
    <tr data-title="<?= h(strtolower($title)) ?>"
        data-desc="<?= h(strtolower($desc)) ?>"
        data-tags="<?= h(strtolower($tags)) ?>"
        data-author="<?= h(strtolower($author)) ?>"
        data-files="<?= $numFiles ?>"
        data-modified="<?= $s['modified'] ?>"
        data-size="<?= $s['size'] ?>">
      <td class="col-title">
        <a href="view/?file=<?= urlencode($fname) ?>">📄 <?= h($title) ?></a>
        <?php if ($desc): ?>
        <div class="row-desc"><?= h($desc) ?></div>
        <?php endif; ?>
      </td>
      <td class="col-tags">
        <?php foreach (array_filter(array_map('trim', explode(',', $tags))) as $tag):
          $tagHref = '?tag=' . rawurlencode($tag);
          if ($sortQuery !== 'modified-desc') {
              $tagHref .= '&sort=' . rawurlencode($sortQuery);
          }
        ?>
        <a href="<?= h($tagHref) ?>" class="tag" onclick="event.preventDefault(); setTagFilter(<?= json_encode($tag, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>);"><?= h($tag) ?></a>
        <?php endforeach; ?>
      </td>
      <td class="col-meta"><?= h($author) ?><?php if ($version): ?><br><span style="font-size:.7rem">v<?= h($version) ?></span><?php endif; ?></td>
      <td class="col-meta"><?= $numFiles ?></td>
      <td class="col-meta"><?= h(fmt_size($s['size'])) ?></td>
      <td class="col-meta"><?= h($date) ?></td>
      <td class="col-actions">
        <div class="actions-wrap">
          <a href="view/?file=<?= urlencode($fname) ?>"     class="btn btn-xs btn-primary">👁 <?= h(__('index.btn_view')) ?></a>
          <details class="split-dl">
            <summary class="btn btn-xs btn-secondary split-dl-btn" aria-label="<?= h(__('index.dl_aria')) ?>">⬇ ▾</summary>
            <div class="split-dl-menu" role="menu">
              <a class="split-dl-opt" href="download.php?file=<?= urlencode($fname) ?>&amp;ext=skill" role="menuitem"><?= h(__('index.dl_as_skill')) ?></a>
              <a class="split-dl-opt" href="download.php?file=<?= urlencode($fname) ?>&amp;ext=zip" role="menuitem"><?= h(__('index.dl_as_zip')) ?></a>
            </div>
          </details>
          <?php if ($isAuthed): ?>
          <a href="edit/?file=<?= urlencode($fname) ?>"     class="btn btn-xs btn-teal">✏️</a>
          <form method="POST" style="display:inline"
                onsubmit="return confirm(<?= json_encode(__('index.confirm_delete', ['name' => $title]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="file"   value="<?= h($fname) ?>">
            <button type="submit" class="btn btn-xs btn-danger">🗑</button>
          </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="no-results hidden" id="no-results">
    <?= h(__('index.no_results')) ?>
  </div>

  <?php endif; ?>

</div>

<footer><?= h(APP_NAME) ?> · <?= h(__('common.footer_format')) ?> · <?= date('Y') ?></footer>

<script>
var LANG = {
  resultAll: <?= json_encode(__('index.js_result_all'), JSON_UNESCAPED_UNICODE) ?>,
  resultOne: <?= json_encode(__('index.js_result_one'), JSON_UNESCAPED_UNICODE) ?>,
  resultPartial: <?= json_encode(__('index.js_result_partial'), JSON_UNESCAPED_UNICODE) ?>
};
var sortCol = 'modified', sortDir = 'desc';
/** Från ?tag=… vid sidladdning (tom sträng om inget) */
var tagQueryFromServer = <?= json_encode($tagQuery, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>;
/** Från ?sort=… (t.ex. title-asc, modified-desc) */
var sortQueryFromServer = <?= json_encode($sortQuery, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>;
var sortDefaultValue = 'modified-desc';

function initTagFilterFromQuery() {
  var sel = document.getElementById('tag-filter');
  if (!sel) return;
  var q = (typeof tagQueryFromServer === 'string' ? tagQueryFromServer : '').trim();
  if (!q) return;
  var lower = q.toLowerCase();
  for (var i = 0; i < sel.options.length; i++) {
    if (sel.options[i].value.toLowerCase() === lower) {
      sel.selectedIndex = i;
      return;
    }
  }
  var opt = document.createElement('option');
  opt.value = q;
  opt.textContent = q;
  sel.appendChild(opt);
  sel.value = q;
}

/** Uppdaterar ?tag= och ?sort= utan omladdning (standardvärden tas bort för kortare URL) */
function syncIndexQueryToUrl() {
  var url = new URL(window.location.href);
  var tagSel = document.getElementById('tag-filter');
  var sortSel = document.getElementById('sort-select');
  if (tagSel) {
    var tv = tagSel.value.trim();
    if (tv) {
      url.searchParams.set('tag', tv);
    } else {
      url.searchParams.delete('tag');
    }
  }
  if (sortSel) {
    var sv = sortSel.value.trim() || sortDefaultValue;
    if (sv && sv !== sortDefaultValue) {
      url.searchParams.set('sort', sv);
    } else {
      url.searchParams.delete('sort');
    }
  }
  var next = url.pathname + url.search + url.hash;
  if (next !== window.location.pathname + window.location.search + window.location.hash) {
    history.replaceState({}, '', next);
  }
}

function applyFilters() {
  var q      = document.getElementById('search-input').value.trim().toLowerCase();
  var tagF   = document.getElementById('tag-filter').value.toLowerCase();
  var sortV  = document.getElementById('sort-select').value.split('-');
  sortCol    = sortV[0];
  sortDir    = sortV[1] || 'asc';

  var rows   = Array.from(document.querySelectorAll('#skill-tbody tr'));
  var visible = 0;

  rows.forEach(function(row) {
    var title    = row.dataset.title   || '';
    var desc     = row.dataset.desc    || '';
    var tags     = row.dataset.tags    || '';
    var author   = row.dataset.author  || '';
    var haystack = title + ' ' + desc + ' ' + tags + ' ' + author;

    var matchQ   = !q    || haystack.includes(q);
    var matchTag = !tagF || tags.split(',').map(function(t){ return t.trim(); }).includes(tagF);

    if (matchQ && matchTag) {
      row.classList.remove('hidden-row');
      visible++;
    } else {
      row.classList.add('hidden-row');
    }
  });

  // Sort visible rows
  var tbody = document.getElementById('skill-tbody');
  var visibleRows = rows.filter(function(r) { return !r.classList.contains('hidden-row'); });
  visibleRows.sort(function(a, b) {
    var av = getSortVal(a), bv = getSortVal(b);
    if (av < bv) return sortDir === 'asc' ? -1 :  1;
    if (av > bv) return sortDir === 'asc' ?  1 : -1;
    return 0;
  });
  visibleRows.forEach(function(r) { tbody.appendChild(r); });

  // Result bar
  var total = rows.length;
  document.getElementById('result-bar').textContent =
    visible === total
      ? (total === 1 ? LANG.resultOne : LANG.resultAll.replace('{n}', String(total)))
      : LANG.resultPartial.replace('{visible}', String(visible)).replace('{total}', String(total));

  // No results
  document.getElementById('no-results').classList.toggle('hidden', visible > 0);

  updateSortArrows();
  syncIndexQueryToUrl();
}

function getSortVal(row) {
  if (sortCol === 'title')    return row.dataset.title    || '';
  if (sortCol === 'author')   return row.dataset.author   || '';
  if (sortCol === 'tags')     return row.dataset.tags     || '';
  if (sortCol === 'files')    return parseInt(row.dataset.files   || '0');
  if (sortCol === 'size')     return parseInt(row.dataset.size    || '0');
  if (sortCol === 'modified') return parseInt(row.dataset.modified || '0');
  return '';
}

function sortBy(col) {
  var sel = document.getElementById('sort-select');
  var cur = sel.value.split('-');
  var newDir = (cur[0] === col && cur[1] === 'asc') ? 'desc' : 'asc';
  // Special defaults
  if (col === 'modified' || col === 'size' || col === 'files') newDir = (cur[0] === col && cur[1] === 'desc') ? 'asc' : 'desc';
  sel.value = col + '-' + newDir;
  applyFilters();
}

function updateSortArrows() {
  ['title','tags','author','files','size','modified'].forEach(function(c) {
    var el = document.getElementById('arr-' + c);
    var th = el && el.closest('th');
    if (!el) return;
    if (c === sortCol) {
      el.textContent = sortDir === 'asc' ? '↑' : '↓';
      th.classList.add('sorted');
    } else {
      el.textContent = '↕';
      th.classList.remove('sorted');
    }
  });
}

function setTagFilter(tag) {
  document.getElementById('tag-filter').value = tag;
  applyFilters();
  document.getElementById('search-input').focus();
}

function initSortFromQueryString() {
  var sortSel = document.getElementById('sort-select');
  if (!sortSel) return;
  var s = (typeof sortQueryFromServer === 'string' ? sortQueryFromServer : '').trim();
  if (!s) s = sortDefaultValue;
  var found = false;
  for (var i = 0; i < sortSel.options.length; i++) {
    if (sortSel.options[i].value === s) {
      sortSel.selectedIndex = i;
      found = true;
      break;
    }
  }
  if (!found && s) {
    var opt = document.createElement('option');
    opt.value = s;
    opt.textContent = s;
    sortSel.appendChild(opt);
    sortSel.value = s;
  }
}

window.addEventListener('popstate', function() {
  var params = new URLSearchParams(window.location.search);
  var p = params.get('tag') || '';
  var sel = document.getElementById('tag-filter');
  if (sel) {
    p = p.trim();
    if (!p) {
      sel.value = '';
    } else {
      var lower = p.toLowerCase();
      var found = false;
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value.toLowerCase() === lower) {
          sel.selectedIndex = i;
          found = true;
          break;
        }
      }
      if (!found) {
        var opt = document.createElement('option');
        opt.value = p;
        opt.textContent = p;
        sel.appendChild(opt);
        sel.value = p;
      }
    }
  }
  var sortSel = document.getElementById('sort-select');
  if (sortSel) {
    var sv = (params.get('sort') || '').trim().toLowerCase();
    sv = sv.replace(/[^a-z0-9\-]/g, '');
    if (!sv) sv = sortDefaultValue;
    var sf = false;
    for (var j = 0; j < sortSel.options.length; j++) {
      if (sortSel.options[j].value === sv) {
        sortSel.selectedIndex = j;
        sf = true;
        break;
      }
    }
    if (!sf && sv) {
      var o2 = document.createElement('option');
      o2.value = sv;
      o2.textContent = sv;
      sortSel.appendChild(o2);
      sortSel.value = sv;
    }
  }
  applyFilters();
});

var skillTbody = document.getElementById('skill-tbody');
if (skillTbody) skillTbody.addEventListener('toggle', function(e) {
  var t = e.target;
  if (!t.classList || !t.classList.contains('split-dl') || !t.open) return;
  document.querySelectorAll('#skill-tbody .split-dl[open]').forEach(function(d) {
    if (d !== t) d.open = false;
  });
}, true);
document.addEventListener('click', function(e) {
  if (e.target.closest('.split-dl')) return;
  document.querySelectorAll('#skill-tbody .split-dl-details[open]').forEach(function(d) { d.open = false; });
});

// Init: ?tag= och ?sort= (sort sätts även av PHP selected på option)
initTagFilterFromQuery();
initSortFromQueryString();
applyFilters();
</script>
</body>
</html>
