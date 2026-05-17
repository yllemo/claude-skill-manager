<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/ai_lib.php';

skill_require_auth('../login.php');

if (!is_dir(CONTENT_DIR)) {
    mkdir(CONTENT_DIR, 0755, true);
}

$isNew    = true;
$filePath = null;
$filename = '';
$error    = '';

$helpContent = is_file(__DIR__ . '/../skill-intro.md')
    ? (string)file_get_contents(__DIR__ . '/../skill-intro.md')
    : '';

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
        $error = __('ai.error_name');
    } else {
        $newFname = sanitize_filename($skillName);
        $savePath = CONTENT_DIR . $newFname;
        $files    = json_decode($filesJson, true) ?: [];

        $zip = new ZipArchive();
        $zip->open($savePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $origToRead = ($origFile && $origFile !== $newFname)
            ? validate_file_param($origFile)
            : ($filePath ?? null);

        if ($origToRead) {
            $origZip = new ZipArchive();
            if ($origZip->open($origToRead) === true) {
                for ($i = 0; $i < $origZip->numFiles; $i++) {
                    $ename = (string)$origZip->getNameIndex($i);
                    if (str_ends_with($ename, '/')) {
                        continue;
                    }
                    $ext = strtolower(pathinfo($ename, PATHINFO_EXTENSION));
                    if (!in_array($ext, skill_text_extensions(), true)) {
                        $zip->addFromString($ename, (string)$origZip->getFromIndex($i));
                    }
                }
                $origZip->close();
            }
        }

        foreach ($files as $entryName => $content) {
            $safe = sanitize_entry((string)$entryName);
            if ($safe !== '') {
                $zip->addFromString($safe, (string)$content);
            }
        }
        $zip->close();

        if ($origFile && $origFile !== $newFname) {
            $oldPath = validate_file_param($origFile);
            if ($oldPath) {
                unlink($oldPath);
            }
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
    $initialFiles['SKILL.md'] = skill_default_skill_md_template();
    $skillNameDefault = '';
}

$pageTitle = $isNew ? __('ai.page_new') : __('ai.page_edit', ['name' => pathinfo($filename, PATHINFO_FILENAME)]);

$defaultEntry = '';
foreach (array_keys($initialFiles) as $n) {
    if (preg_match('/(?:^|\/)SKILL\.md$/i', $n)) {
        $defaultEntry = $n;
        break;
    }
}
if (!$defaultEntry && !empty($initialFiles)) {
    $defaultEntry = array_key_first($initialFiles);
}
$entryParam = isset($_GET['entry']) ? sanitize_entry((string)$_GET['entry']) : '';
if ($entryParam !== '' && isset($initialFiles[$entryParam])) {
    $defaultEntry = $entryParam;
}

$skillArchivePrefix = skill_archive_root_prefix_from_paths(array_keys($initialFiles));

$aiCfg = skill_ai_config();
$aiDefaultsJson = json_encode([
    'defaultProvider' => (string)($aiCfg['default_provider'] ?? 'ollama'),
    'models'            => (array)($aiCfg['models'] ?? []),
    'systemPrompt'      => (string)($aiCfg['system_prompt'] ?? ''),
    'ollamaBrowserBase' => rtrim((string)($aiCfg['ollama_base'] ?? 'http://127.0.0.1:11434/v1'), '/'),
    'lmstudioBrowserBase' => rtrim((string)($aiCfg['lmstudio_base'] ?? 'http://127.0.0.1:1234/v1'), '/'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$switchToEditUrl = $isNew ? '../edit/' : ('../edit/?file=' . rawurlencode($filename));
?>
<!DOCTYPE html>
<html lang="<?= h(skill_lang_html_lang()) ?>" data-theme="light">
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

.sidebar{width:248px;flex-shrink:0;background:var(--bg-nav);border-right:1px solid var(--border-l);display:flex;flex-direction:column;overflow:hidden}
.sb-hdr{padding:10px 12px;border-bottom:1px solid var(--border-l);flex-shrink:0}
.sb-hdr label{display:block;font-size:.65rem;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}
.skill-name-input{width:100%;padding:5px 8px;border:1px solid var(--border-l);border-radius:var(--r);font:inherit;font-size:.82rem;background:var(--bg);color:var(--text);margin-bottom:7px}
.skill-name-input:focus{outline:2px solid var(--accent);border-color:transparent}
.sb-btn-row{display:flex;gap:4px}
.tree-section-hdr{padding:5px 12px 4px;font-size:.63rem;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border-l);border-top:1px solid var(--border-l);margin-top:4px;display:flex;align-items:center;justify-content:space-between}
.tree-wrap{flex:1;overflow-y:auto;padding:3px 0}

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

.editor-area{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.etoolbar{min-height:var(--toolbar-h);display:flex;align-items:center;padding:6px 12px;gap:6px;border-bottom:1px solid var(--border-l);background:var(--bg-nav);flex-shrink:0;flex-wrap:wrap}
.etoolbar-label{font-size:.78rem;font-weight:600;color:var(--text-2);flex:1;min-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.provider-row{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.prov-lbl{font-size:.68rem;font-weight:700;color:var(--text-2);text-transform:uppercase}
.prov-btn{opacity:.92}
.prov-btn.active{background:var(--accent)!important;color:#fff!important;border-color:var(--accent)!important}
.ai-model-input{width:min(160px,28vw);padding:4px 8px;font-size:.76rem;border:1px solid var(--border-l);border-radius:var(--r);background:var(--bg);color:var(--text)}
.local-ai-wrap{display:none;align-items:center;gap:5px;font-size:.72rem;color:var(--text-2);user-select:none}
.local-ai-wrap input{margin:0}

.ai-work{display:flex;flex:1;min-height:0;overflow:hidden;flex-direction:row}
.editor-wrap{flex:1;display:flex;flex-direction:column;min-width:0;min-height:0;overflow:hidden}
.editor-wrap .emain{flex:1;min-height:0}
.emain{display:flex;flex-direction:column;overflow:hidden;min-height:0;min-width:0}
.eleft{display:flex;flex-direction:column;overflow:hidden;min-width:0;min-height:0;flex:1}
.pane-hdr{padding:6px 12px;background:var(--bg-nav);border-bottom:1px solid var(--border-l);font-size:.7rem;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.05em;flex-shrink:0;display:flex;align-items:center;gap:6px}
.lang-badge{font-size:.62rem;padding:1px 5px;background:var(--accent);color:#fff;border-radius:3px;text-transform:none;letter-spacing:0;font-weight:600}
#monaco-container{flex:1;min-height:0;overflow:hidden}
.ai-attach-bar{padding:0 0 10px;margin:0 0 10px;border-bottom:1px solid var(--border-l)}
.ai-attach-btn{display:flex;width:100%;align-items:center;justify-content:center;gap:6px;padding:9px 12px;font-size:.82rem;font-weight:600}
.ai-attach-hint{margin:8px 0 0;font-size:.74rem;color:var(--text-2);line-height:1.4}
.ai-attach-file{font-weight:600;color:var(--accent);word-break:break-all}

.ai-panel{width:min(380px,36vw);min-width:260px;flex-shrink:0;border-left:1px solid var(--border-l);display:flex;flex-direction:column;background:var(--bg-nav);overflow:hidden}
.ai-panel-hdr{padding:8px 12px;border-bottom:1px solid var(--border-l);font-size:.68rem;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.06em}
.ai-messages{flex:1;overflow-y:auto;padding:10px 12px;font-size:.8rem;display:flex;flex-direction:column;gap:10px}
.ai-msg{border-radius:var(--r);padding:8px 10px;max-width:100%;word-break:break-word;white-space:pre-wrap}
.ai-msg.user{align-self:flex-end;background:rgba(0,119,188,.12);border:1px solid rgba(0,119,188,.25)}
.ai-msg.assistant{align-self:flex-start;background:var(--bg);border:1px solid var(--border-l)}
.ai-msg-role{font-size:.62rem;font-weight:700;color:var(--text-2);margin-bottom:4px;text-transform:uppercase}
.ai-input-wrap{padding:8px 12px;border-top:1px solid var(--border-l);background:var(--bg-nav)}
.ai-user-input{width:100%;min-height:120px;max-height:min(55vh,520px);padding:8px;border:1px solid var(--border-l);border-radius:var(--r);font:inherit;font-size:.8rem;background:var(--bg);color:var(--text);resize:vertical;overflow-y:auto}
.ai-actions{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;align-items:center}
.ai-status{font-size:.72rem;color:var(--text-2);flex:1;min-width:120px}

.error-bar{background:#fdecea;color:#8b1a1a;border-bottom:1px solid #f5bbb8;padding:8px 14px;font-size:.82rem;flex-shrink:0}
[data-theme="dark"] .error-bar{background:#3b1111;color:#f28b82;border-color:#5c2020}

.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(2px)}
.modal-overlay.hidden{display:none}
.modal-box{background:var(--bg);border:1px solid var(--border-l);border-radius:var(--r-lg);box-shadow:0 8px 40px rgba(0,0,0,.25);width:100%;max-width:720px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden}
.modal-hdr{padding:14px 18px;border-bottom:1px solid var(--border-l);display:flex;align-items:center;gap:10px;flex-shrink:0;background:var(--bg-nav)}
.modal-hdr-title{font-size:.95rem;font-weight:700;color:var(--accent);flex:1}
.modal-body{flex:1;overflow-y:auto;padding:18px 20px}
.modal-footer{padding:10px 18px;border-top:1px solid var(--border-l);background:var(--bg-nav);display:flex;justify-content:flex-end;gap:8px;flex-shrink:0}
.ai-settings-text{width:100%;min-height:220px;padding:10px;border:1px solid var(--border-l);border-radius:var(--r);font-family:Consolas,Monaco,monospace;font-size:.78rem;background:var(--bg);color:var(--text)}

.help-btn{display:inline-flex;align-items:center;gap:4px;width:30px;height:30px;border-radius:50%;justify-content:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.32);color:#fff;font-size:.85rem;font-weight:700;cursor:pointer;transition:background .15s;padding:0}
.help-btn:hover{background:rgba(255,255,255,.28)}

@media(max-width:1100px){
  .ai-panel{width:100%;min-width:0;border-left:none;border-top:1px solid var(--border-l);max-height:42vh}
  .ai-work{flex-direction:column}
}
@media(max-width:660px){.sidebar{display:none}}

.sidebar-toggle{display:none;align-items:center;gap:4px;padding:4px 8px;font-size:.75rem;border-radius:var(--r);background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.28);color:#fff;cursor:pointer}
.sidebar-toggle:hover{background:rgba(255,255,255,.22)}
@media(max-width:660px){
  .sidebar{position:fixed;top:54px;left:-280px;width:280px;height:calc(100vh - 54px);z-index:1000;transition:left .3s;display:flex!important}
  .sidebar:not(.mobile-hidden){left:0}
  .sidebar-toggle{display:flex!important}
  .sidebar-overlay{position:fixed;top:54px;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:999;display:none}
  .sidebar-overlay.show{display:block}
}

@media(max-width: 768px) {
  .hdr-actions .btn.btn-switch-mode { display: inline-flex !important; }
}
</style>
</head>
<body>

<header class="header">
  <button class="sidebar-toggle" type="button" onclick="toggleSidebar()" aria-label="<?= h(__('common.show_tools')) ?>">🛠️</button>
  <a href="../" style="display:flex;align-items:center;gap:10px;text-decoration:none">
    <div class="logo-mark">🤖</div>
    <div class="logo-text"><?= h(APP_NAME) ?><span class="logo-sub"><?= h(__('ai.logo_sub')) ?></span></div>
  </a>
  <div class="hdr-sep"></div>
  <div class="hdr-title"><?= h($pageTitle) ?></div>
  <div class="hdr-actions">
    <a href="<?= h($switchToEditUrl) ?>" class="btn btn-sm btn-teal btn-switch-mode" title="<?= h($isNew ? __('ai.switch_classic_title_new') : __('ai.switch_classic_title')) ?>">✏️ <?= h(__('ai.switch_classic')) ?></a>
    <?php if (!$isNew): ?>
    <a href="../view/?file=<?= urlencode($filename) ?>" class="btn btn-white btn-sm">👁 <?= h(__('common.view')) ?></a>
    <?php endif; ?>
    <a href="../" class="btn btn-white btn-sm">← <?= h(__('common.back')) ?></a>
    <a href="../logout.php" class="btn btn-white btn-sm" onclick="return confirm(<?= json_encode(__('common.confirm_logout'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)">🔓 <?= h(__('common.logout')) ?></a>
    <button class="help-btn" type="button" onclick="openHelp()" title="<?= h(__('ai.help_title')) ?>">?</button>
    <button class="theme-btn" type="button" onclick="toggleTheme()" title="<?= h(__('common.theme_title')) ?>">🌓</button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="workspace">
  <div class="sidebar mobile-hidden" id="sidebar">
    <div class="sb-hdr">
      <label><?= h(__('edit.sidebar_filename')) ?></label>
      <input type="text" id="skill-name-input" class="skill-name-input"
             value="<?= h($skillNameDefault) ?>" placeholder="<?= h(__('edit.placeholder_name')) ?>">
      <div class="sb-btn-row">
        <button type="button" class="btn btn-xs btn-success" style="flex:1" onclick="saveSkill()">💾 <?= h(__('common.save')) ?></button>
        <button type="button" class="btn btn-xs btn-secondary" onclick="addFile()"><?= h(__('edit.btn_add_file')) ?></button>
      </div>
    </div>
    <div class="tree-section-hdr"><span><?= h(__('edit.tree_header')) ?></span></div>
    <div class="tree-wrap" id="tree-wrap"></div>
  </div>

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
      <span class="etoolbar-label" id="current-file-label"><?= h(__('edit.current_file_loading')) ?></span>
      <div class="provider-row">
        <span class="prov-lbl">AI</span>
        <button type="button" class="btn btn-xs btn-secondary prov-btn" data-prov="openai">OpenAI</button>
        <button type="button" class="btn btn-xs btn-secondary prov-btn" data-prov="ollama">Ollama</button>
        <button type="button" class="btn btn-xs btn-secondary prov-btn" data-prov="lmstudio">LM Studio</button>
        <input type="text" id="ai-model" class="ai-model-input" placeholder="<?= h(__('ai.model_placeholder')) ?>" title="<?= h(__('ai.model_title')) ?>" autocomplete="off">
        <label id="wrap-local-ai" class="local-ai-wrap" title="<?= h(__('ai.local_browser_title')) ?>">
          <input type="checkbox" id="chk-local-ai" />
          <span><?= h(__('ai.local_browser')) ?></span>
        </label>
        <button type="button" class="btn btn-xs btn-secondary" onclick="openAiSettings()" title="<?= h(__('ai.settings_btn_title')) ?>">⚙️ <?= h(__('ai.settings_btn')) ?></button>
      </div>
      <button type="button" class="btn btn-sm btn-secondary" id="btn-rename-file" onclick="if(currentFile)renameMoveFile(currentFile)">📂 <?= h(__('edit.rename_btn')) ?></button>
      <button type="button" class="btn btn-sm btn-secondary" id="btn-delete-file" onclick="if(currentFile)removeFile(currentFile)">🗑 <?= h(__('edit.delete_btn')) ?></button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="insertTemplate()">📋 <?= h(__('edit.template')) ?></button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="formatDoc()">✨ <?= h(__('edit.format')) ?></button>
      <button type="button" class="btn btn-sm btn-success" onclick="saveSkill()">💾 <?= h(__('common.save')) ?></button>
    </div>

    <div class="ai-work">
      <div class="editor-wrap">
        <div class="emain">
          <div class="eleft">
            <div class="pane-hdr">✏️ <?= h(__('edit.editor_pane')) ?> <span class="lang-badge" id="lang-badge">markdown</span></div>
            <div id="monaco-container"></div>
          </div>
        </div>
      </div>

      <aside class="ai-panel" id="ai-panel">
        <div class="ai-panel-hdr"><?= h(__('ai.panel_title')) ?></div>
        <div class="ai-messages" id="ai-messages"></div>
        <div class="ai-input-wrap">
          <div class="ai-attach-bar">
            <button type="button" class="btn btn-primary ai-attach-btn" onclick="includeFileInAi()" title="<?= h(__('ai.attach_title')) ?>">
              📎 <?= h(__('ai.attach_btn')) ?>
            </button>
            <p class="ai-attach-hint">
              <?= __('ai.attach_hint') ?><span class="ai-attach-file" id="ai-attach-filename">—</span>
            </p>
          </div>
          <textarea id="ai-user-input" class="ai-user-input" placeholder="<?= h(__('ai.user_placeholder')) ?>"></textarea>
          <div class="ai-actions">
            <button type="button" class="btn btn-sm btn-primary" id="ai-send-btn" onclick="sendAiChat()"><?= h(__('ai.send')) ?></button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="applyLastAssistantToEditor(false)"><?= h(__('ai.insert')) ?></button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="applyLastAssistantToEditor(true)"><?= h(__('ai.replace_all')) ?></button>
            <span class="ai-status" id="ai-status"></span>
          </div>
        </div>
      </aside>
    </div>
  </div>
</div>

<footer><?= h(APP_NAME) ?> · AI · <?= $isNew ? h(__('ai.footer_new')) : h($filename) ?></footer>

<div class="modal-overlay hidden" id="help-modal" onclick="if(event.target===this)closeHelp()">
  <div class="modal-box">
    <div class="modal-hdr">
      <span class="modal-hdr-title">📘 <?= h(__('ai.help_modal')) ?></span>
      <button type="button" class="btn btn-xs btn-secondary" onclick="closeHelp()">✕</button>
    </div>
    <div class="modal-body"><div class="md" id="help-content"></div></div>
    <div class="modal-footer">
      <button type="button" class="btn btn-sm btn-primary" onclick="closeHelp()"><?= h(__('common.close')) ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="ai-settings-modal" onclick="if(event.target===this)closeAiSettings()">
  <div class="modal-box">
    <div class="modal-hdr">
      <span class="modal-hdr-title">⚙️ <?= h(__('ai.settings_modal')) ?></span>
      <button type="button" class="btn btn-xs btn-secondary" onclick="closeAiSettings()">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:.85rem;color:var(--text-2);margin-bottom:10px">
        <?= __('ai.settings_intro') ?>
      </p>
      <label class="prov-lbl" style="display:block;margin-bottom:6px;margin-top:12px"><?= h(__('ai.label_ollama')) ?></label>
      <input type="text" id="ai-settings-ollama-browser-base" class="skill-name-input" style="margin-bottom:10px;font-family:Consolas,monospace;font-size:.78rem" placeholder="http://127.0.0.1:11434/v1" autocomplete="off">
      <label class="prov-lbl" style="display:block;margin-bottom:6px"><?= h(__('ai.label_lmstudio')) ?></label>
      <input type="text" id="ai-settings-lmstudio-browser-base" class="skill-name-input" style="margin-bottom:10px;font-family:Consolas,monospace;font-size:.78rem" placeholder="http://127.0.0.1:1234/v1" autocomplete="off">
      <label class="prov-lbl" style="display:block;margin-bottom:6px"><?= h(__('ai.label_system')) ?></label>
      <textarea id="ai-settings-prompt" class="ai-settings-text" spellcheck="false"></textarea>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-sm btn-secondary" onclick="resetSystemPrompt()"><?= h(__('ai.reset_prompt')) ?></button>
      <button type="button" class="btn btn-sm btn-primary" onclick="saveAiSettings()"><?= h(__('ai.save')) ?></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.47.0/min/vs/loader.js"></script>
<script>
var EDIT_LANG = {
  treeRename: <?= json_encode(__('edit.tree_rename'), JSON_UNESCAPED_UNICODE) ?>,
  treeRenameAria: <?= json_encode(__('edit.tree_rename_aria'), JSON_UNESCAPED_UNICODE) ?>,
  treeDelete: <?= json_encode(__('edit.tree_delete'), JSON_UNESCAPED_UNICODE) ?>,
  treeDeleteAria: <?= json_encode(__('edit.tree_delete_aria'), JSON_UNESCAPED_UNICODE) ?>,
  alertFilename: <?= json_encode(__('edit.alert_filename'), JSON_UNESCAPED_UNICODE) ?>,
  alertNoFiles: <?= json_encode(__('edit.alert_no_files'), JSON_UNESCAPED_UNICODE) ?>,
  promptNewFile: <?= json_encode(__('edit.prompt_new_file'), JSON_UNESCAPED_UNICODE) ?>,
  alertBadName: <?= json_encode(__('edit.alert_bad_name'), JSON_UNESCAPED_UNICODE) ?>,
  alertMinFile: <?= json_encode(__('edit.alert_min_file'), JSON_UNESCAPED_UNICODE) ?>,
  confirmRemove: <?= json_encode(__('edit.confirm_remove'), JSON_UNESCAPED_UNICODE) ?>,
  promptRename: <?= json_encode(__('edit.prompt_rename'), JSON_UNESCAPED_UNICODE) ?>,
  alertBadPath: <?= json_encode(__('edit.alert_bad_path'), JSON_UNESCAPED_UNICODE) ?>,
  alertPathExists: <?= json_encode(__('edit.alert_path_exists'), JSON_UNESCAPED_UNICODE) ?>,
  templateConfirm: <?= json_encode(__('edit.template_confirm'), JSON_UNESCAPED_UNICODE) ?>
};
var AI_LANG = {
  includeBlockHeader: <?= json_encode(__('ai.include_block_header'), JSON_UNESCAPED_UNICODE) ?>,
  includeIntro: <?= json_encode(__('ai.include_intro'), JSON_UNESCAPED_UNICODE) ?>,
  statusSending: <?= json_encode(__('ai.status_sending'), JSON_UNESCAPED_UNICODE) ?>,
  statusDone: <?= json_encode(__('ai.status_done'), JSON_UNESCAPED_UNICODE) ?>,
  errEmpty: <?= json_encode(__('ai.err_empty_response'), JSON_UNESCAPED_UNICODE) ?>,
  errUnknownShort: <?= json_encode(__('ai.err_unknown_short'), JSON_UNESCAPED_UNICODE) ?>,
  errInvalidAnswer: <?= json_encode(__('ai.err_invalid_answer'), JSON_UNESCAPED_UNICODE) ?>,
  helpEmpty: <?= json_encode(__('ai.help_empty'), JSON_UNESCAPED_UNICODE) ?>,
  alertInstruction: <?= json_encode(__('ai.alert_instruction'), JSON_UNESCAPED_UNICODE) ?>,
  alertModel: <?= json_encode(__('ai.alert_model'), JSON_UNESCAPED_UNICODE) ?>,
  alertLocalBase: <?= json_encode(__('ai.alert_local_base'), JSON_UNESCAPED_UNICODE) ?>,
  alertNoReply: <?= json_encode(__('ai.alert_no_reply'), JSON_UNESCAPED_UNICODE) ?>,
  errPrefix: <?= json_encode(__('common.error_prefix'), JSON_UNESCAPED_UNICODE) ?>,
  errNet: <?= json_encode(__('common.error_network'), JSON_UNESCAPED_UNICODE) ?>,
  errNetLocal: <?= json_encode(__('common.error_network_local'), JSON_UNESCAPED_UNICODE) ?>
};
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

var SKILL_MD_TEMPLATE = <?= json_encode(skill_default_skill_md_template(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>;
var aiDefaults = <?= $aiDefaultsJson ?>;
var initialFiles = <?= json_encode($initialFiles, JSON_UNESCAPED_UNICODE) ?>;
var skillArchivePrefix = <?= json_encode($skillArchivePrefix, JSON_UNESCAPED_UNICODE) ?>;
var helpMd       = <?= json_encode($helpContent, JSON_UNESCAPED_UNICODE) ?>;
var defaultEntry = <?= json_encode($defaultEntry) ?>;

var LS_PROVIDER = 'skill_ai_provider';
var LS_MODEL_PFX = 'skill_ai_model_';
var LS_SYSTEM = 'skill_ai_system_prompt';

var edits = Object.assign({}, initialFiles);
var models = {};
var currentFile = null;
var monacoEditor = null;
var removedFromArchive = new Set();
var lastAssistantText = '';

function getStoredSystemPrompt() {
  try {
    var s = localStorage.getItem(LS_SYSTEM);
    if (s !== null && s !== '') return s;
  } catch (e) {}
  return aiDefaults.systemPrompt || '';
}

function setStoredSystemPrompt(text) {
  try { localStorage.setItem(LS_SYSTEM, text); } catch (e) {}
}

function getProvider() {
  try {
    var p = localStorage.getItem(LS_PROVIDER);
    if (p === 'openai' || p === 'ollama' || p === 'lmstudio') return p;
  } catch (e) {}
  return aiDefaults.defaultProvider || 'ollama';
}

function setProvider(p) {
  try { localStorage.setItem(LS_PROVIDER, p); } catch (e) {}
  document.querySelectorAll('.prov-btn').forEach(function(b) {
    b.classList.toggle('active', b.getAttribute('data-prov') === p);
  });
  var m = getModelForProvider(p);
  document.getElementById('ai-model').value = m;
  updateLocalAiUi();
}

function getModelForProvider(p) {
  try {
    var x = localStorage.getItem(LS_MODEL_PFX + p);
    if (x !== null && x !== '') return x;
  } catch (e) {}
  var d = (aiDefaults.models && aiDefaults.models[p]) ? aiDefaults.models[p] : '';
  return d || '';
}

function saveModelForProvider(p, model) {
  try { localStorage.setItem(LS_MODEL_PFX + p, model); } catch (e) {}
}

/** Ollama/LM Studio: true = fetch i webbläsaren till localhost; false = chat.php på servern */
function getLocalModeForProvider(p) {
  if (p !== 'ollama' && p !== 'lmstudio') return false;
  try {
    var v = localStorage.getItem('skill_ai_local_' + p);
    if (v === null) return true;
    return v === '1';
  } catch (e) { return true; }
}

function setLocalModeForProvider(p, on) {
  if (p !== 'ollama' && p !== 'lmstudio') return;
  try { localStorage.setItem('skill_ai_local_' + p, on ? '1' : '0'); } catch (e) {}
}

function getBrowserBaseForProvider(p) {
  try {
    var u = localStorage.getItem('skill_ai_browser_base_' + p);
    if (u !== null && u.trim() !== '') return u.trim().replace(/\/$/, '');
  } catch (e) {}
  if (p === 'ollama') return (aiDefaults.ollamaBrowserBase || 'http://127.0.0.1:11434/v1').replace(/\/$/, '');
  if (p === 'lmstudio') return (aiDefaults.lmstudioBrowserBase || 'http://127.0.0.1:1234/v1').replace(/\/$/, '');
  return '';
}

function updateLocalAiUi() {
  var p = getProvider();
  var wrap = document.getElementById('wrap-local-ai');
  var chk = document.getElementById('chk-local-ai');
  if (!wrap || !chk) return;
  if (p === 'ollama' || p === 'lmstudio') {
    wrap.style.display = 'inline-flex';
    chk.checked = getLocalModeForProvider(p);
  } else {
    wrap.style.display = 'none';
  }
}

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

function applySkillFolderPrefix(name) {
  var p = getSkillArchivePrefixLive().replace(/\\/g, '/').replace(/\/+$/, '');
  if (!p) return name;
  var norm = String(name).replace(/\\/g, '/');
  if (norm === p || norm.indexOf(p + '/') === 0) return name;
  return p + '/' + name;
}

/**
 * Normaliserar sökväg i arkivet (snedstreck, ingen path traversal).
 * Ledande "/" = relativt skill-paketets rot (mappen med SKILL.md) när arkivet har den strukturen.
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

function getLang(filename) {
  var ext = (filename.split('.').pop() || '').toLowerCase();
  return {
    md:'markdown', mdx:'markdown', markdown:'markdown', txt:'plaintext', rst:'plaintext',
    csv:'plaintext', tsv:'plaintext', json:'json', jsonl:'json', ndjson:'json', sef:'json',
    yml:'yaml', yaml:'yaml', toml:'ini', xml:'xml', svg:'xml',
    html:'html', htm:'html', css:'css', scss:'scss', less:'css',
    js:'javascript', mjs:'javascript', cjs:'javascript', ts:'typescript', jsx:'javascript', tsx:'typescript',
    vue:'html', svelte:'html',
    py:'python', rb:'ruby', php:'php', go:'go', rs:'rust', java:'java', kt:'kotlin', cs:'csharp',
    lua:'lua', r:'r', sql:'sql', sh:'shell', bash:'shell', ps1:'powershell',
    graphql:'plaintext', gql:'plaintext', hcl:'plaintext', tf:'plaintext',
    ini:'ini', cfg:'ini', conf:'ini', env:'ini', properties:'ini'
  }[ext] || 'plaintext';
}

function getMonacoTheme() {
  return document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';
}

var _origToggle = window.toggleTheme;
window.toggleTheme = function() {
  _origToggle();
  if (monacoEditor) monaco.editor.setTheme(getMonacoTheme());
  mermaid.initialize({ startOnLoad: false, theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default' });
};

require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.47.0/min/vs' } });
require(['vs/editor/editor.main'], function() {
  var container = document.getElementById('monaco-container');
  monacoEditor = monaco.editor.create(container, {
    theme: getMonacoTheme(),
    minimap: { enabled: false },
    wordWrap: 'on',
    fontSize: 13,
    lineHeight: 22,
    fontFamily: "'Consolas', 'Monaco', 'Courier New', monospace",
    scrollBeyondLastLine: false,
    automaticLayout: false,
    padding: { top: 10, bottom: 10 },
    renderLineHighlight: 'line',
    smoothScrolling: true,
    cursorBlinking: 'smooth',
    bracketPairColorization: { enabled: true },
  });

  function layoutEditor() {
    if (!monacoEditor) return;
    monacoEditor.layout({ width: container.clientWidth, height: container.clientHeight });
  }
  window.addEventListener('resize', layoutEditor);
  requestAnimationFrame(function() {
    requestAnimationFrame(layoutEditor);
  });
  initAiUi();
  selectFile(defaultEntry);
  requestAnimationFrame(layoutEditor);
});

function initAiUi() {
  var p = getProvider();
  setProvider(p);
  document.querySelectorAll('.prov-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var np = btn.getAttribute('data-prov');
      saveModelForProvider(getProvider(), document.getElementById('ai-model').value.trim());
      setProvider(np);
    });
  });
  document.getElementById('ai-model').addEventListener('change', function() {
    saveModelForProvider(getProvider(), document.getElementById('ai-model').value.trim());
  });
  document.getElementById('ai-model').addEventListener('blur', function() {
    saveModelForProvider(getProvider(), document.getElementById('ai-model').value.trim());
  });
  var chkLocal = document.getElementById('chk-local-ai');
  if (chkLocal) {
    chkLocal.addEventListener('change', function() {
      setLocalModeForProvider(getProvider(), chkLocal.checked);
    });
  }
}

function openAiSettings() {
  document.getElementById('ai-settings-prompt').value = getStoredSystemPrompt();
  var o = document.getElementById('ai-settings-ollama-browser-base');
  var l = document.getElementById('ai-settings-lmstudio-browser-base');
  if (o) {
    try {
      var so = localStorage.getItem('skill_ai_browser_base_ollama');
      o.value = (so !== null && so !== '') ? so : (aiDefaults.ollamaBrowserBase || '');
    } catch (e) { o.value = aiDefaults.ollamaBrowserBase || ''; }
  }
  if (l) {
    try {
      var sl = localStorage.getItem('skill_ai_browser_base_lmstudio');
      l.value = (sl !== null && sl !== '') ? sl : (aiDefaults.lmstudioBrowserBase || '');
    } catch (e) { l.value = aiDefaults.lmstudioBrowserBase || ''; }
  }
  document.getElementById('ai-settings-modal').classList.remove('hidden');
}
function closeAiSettings() {
  document.getElementById('ai-settings-modal').classList.add('hidden');
}
function saveAiSettings() {
  var t = document.getElementById('ai-settings-prompt').value;
  setStoredSystemPrompt(t);
  var o = document.getElementById('ai-settings-ollama-browser-base');
  var l = document.getElementById('ai-settings-lmstudio-browser-base');
  try {
    if (o) localStorage.setItem('skill_ai_browser_base_ollama', o.value.trim());
    if (l) localStorage.setItem('skill_ai_browser_base_lmstudio', l.value.trim());
  } catch (e) {}
  closeAiSettings();
}
function resetSystemPrompt() {
  document.getElementById('ai-settings-prompt').value = aiDefaults.systemPrompt || '';
}

function appendAiMessage(role, text) {
  var wrap = document.getElementById('ai-messages');
  var el = document.createElement('div');
  el.className = 'ai-msg ' + (role === 'user' ? 'user' : 'assistant');
  var r = document.createElement('div');
  r.className = 'ai-msg-role';
  r.textContent = role === 'user' ? 'Du' : 'Assistent';
  el.appendChild(r);
  var body = document.createElement('div');
  body.textContent = text;
  el.appendChild(body);
  wrap.appendChild(el);
  wrap.scrollTop = wrap.scrollHeight;
}

function setAiStatus(t) {
  var s = document.getElementById('ai-status');
  if (s) s.textContent = t || '';
}

/**
 * Omsluter hela filen i en kodstängsel med många backticks så att inre ```mermaid …```
 * (och andra kodblock) inte kan stänga yttre stängsel. Ökar stängsellängd om filen
 * mot förmodan innehåller en lika lång backtick-rad.
 * Undvik även raden "---" före filnamn (kan tolkas som YAML-frontmatter i vissa vyer).
 */
function wrapFileContentForPrompt(content) {
  var fenceLen = 32;
  var fence;
  for (;;) {
    fence = new Array(fenceLen + 1).join('`');
    if (content.indexOf(fence) === -1) break;
    fenceLen++;
    if (fenceLen > 256) break;
  }
  return fence + '\n' + content + '\n' + fence;
}

function includeFileInAi() {
  if (!monacoEditor || !currentFile) return;
  var ta = document.getElementById('ai-user-input');
  var raw = monacoEditor.getValue();
  var hdr = AI_LANG.includeBlockHeader.replace(/\{file\}/g, currentFile);
  var block =
    hdr +
    wrapFileContentForPrompt(raw) +
    '\n';
  ta.value = (ta.value.trim() ? ta.value.trim() + '\n\n' : '') + AI_LANG.includeIntro + block;
  ta.focus();
}

/**
 * Plockar ut innehåll ur ett yttre ```markdown / ```md-block.
 * Får inte använda *? (icke-greedig) mot första ``` — det är ofta slutet på ```mermaid.
 * Matchar från första raden till sista raden som bara är ``` (hela svaret som ett stängt staket).
 */
function extractMarkdownFromFence(text) {
  if (!text) return null;
  var t = text.trim();
  var m = t.match(/^```(?:markdown|md|text)?\s*\r?\n([\s\S]*)\r?\n```\s*$/i);
  if (m) return m[1].replace(/\r?\n$/, '');
  return null;
}

function sendAiChat() {
  var userText = document.getElementById('ai-user-input').value.trim();
  if (!userText) {
    alert(AI_LANG.alertInstruction);
    return;
  }
  var provider = getProvider();
  var model = document.getElementById('ai-model').value.trim() || getModelForProvider(provider);
  if (!model) {
    alert(AI_LANG.alertModel);
    return;
  }
  saveModelForProvider(provider, model);

  var systemPrompt = getStoredSystemPrompt();
  var messages = [
    { role: 'system', content: systemPrompt },
    { role: 'user', content: userText }
  ];

  appendAiMessage('user', userText);
  document.getElementById('ai-user-input').value = '';
  setAiStatus(AI_LANG.statusSending);
  document.getElementById('ai-send-btn').disabled = true;

  var useLocal = (provider === 'ollama' || provider === 'lmstudio') && getLocalModeForProvider(provider);

  function finishOk(content) {
    document.getElementById('ai-send-btn').disabled = false;
    lastAssistantText = content || '';
    appendAiMessage('assistant', lastAssistantText);
    setAiStatus(AI_LANG.statusDone);
  }
  function finishErr(msg) {
    document.getElementById('ai-send-btn').disabled = false;
    setAiStatus('');
    appendAiMessage('assistant', AI_LANG.errPrefix + msg);
  }

  if (useLocal) {
    var base = getBrowserBaseForProvider(provider);
    if (!base) {
      document.getElementById('ai-send-btn').disabled = false;
      setAiStatus('');
      alert(AI_LANG.alertLocalBase.replace(/\{provider\}/g, provider));
      return;
    }
    var url = base + '/chat/completions';
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ model: model, messages: messages, temperature: 0.7 })
    })
      .then(function(r) {
        return r.text().then(function(t) {
          var j;
          try {
            j = JSON.parse(t);
          } catch (e) {
            finishErr(t ? t.substring(0, 400) : r.statusText || AI_LANG.errInvalidAnswer);
            return;
          }
          if (!r.ok) {
            var err = j.error && (j.error.message || j.error);
            if (typeof err === 'object' && err && err.message) err = err.message;
            finishErr(typeof err === 'string' ? err : (JSON.stringify(j.error || j)).substring(0, 500));
            return;
          }
          var content = j.choices && j.choices[0] && j.choices[0].message && j.choices[0].message.content;
          if (!content) {
            finishErr(AI_LANG.errEmpty);
            return;
          }
          finishOk(content);
        });
      })
      .catch(function(e) {
        document.getElementById('ai-send-btn').disabled = false;
        setAiStatus('');
        appendAiMessage('assistant', AI_LANG.errNetLocal + (e && e.message ? e.message : e));
      });
    return;
  }

  fetch('chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ provider: provider, model: model, messages: messages })
  })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      document.getElementById('ai-send-btn').disabled = false;
      if (!data.ok) {
        setAiStatus('');
        appendAiMessage('assistant', AI_LANG.errPrefix + (data.error || AI_LANG.errUnknownShort));
        return;
      }
      lastAssistantText = data.content || '';
      appendAiMessage('assistant', lastAssistantText);
      setAiStatus(AI_LANG.statusDone);
    })
    .catch(function(e) {
      document.getElementById('ai-send-btn').disabled = false;
      setAiStatus('');
      appendAiMessage('assistant', AI_LANG.errNet + e);
    });
}

function applyLastAssistantToEditor(replaceAll) {
  if (!monacoEditor || !lastAssistantText) {
    alert(AI_LANG.alertNoReply);
    return;
  }
  var inner = extractMarkdownFromFence(lastAssistantText);
  var toInsert = inner !== null ? inner : lastAssistantText;
  if (replaceAll) {
    monacoEditor.setValue(toInsert);
  } else {
    var sel = monacoEditor.getSelection();
    var op = { identifier: 'insert-ai', range: sel, text: toInsert, forceMoveMarkers: true };
    monacoEditor.executeEdits('ai-insert', [op]);
  }
  monacoEditor.focus();
}

function skillEntryToMonacoUri(entryPath) {
  var norm = String(entryPath).replace(/\\/g, '/');
  return monaco.Uri.from({
    scheme: 'skill',
    path: '/' + encodeURIComponent(norm),
  });
}

function selectFile(path) {
  if (!monacoEditor) return;
  if (currentFile && models[currentFile]) {
    edits[currentFile] = models[currentFile].getValue();
  }
  currentFile = path;
  if (!models[path]) {
    var uri = skillEntryToMonacoUri(path);
    var lang = getLang(path);
    models[path] = monaco.editor.createModel(edits[path] || '', lang, uri);
  }
  monacoEditor.setModel(models[path]);
  monacoEditor.focus();
  var lang = getLang(path);
  monacoEditor.updateOptions({ wordWrap: (lang === 'markdown' || lang === 'plaintext') ? 'on' : 'off' });
  document.getElementById('current-file-label').textContent = path;
  document.getElementById('lang-badge').textContent = lang;
  buildTree(path);
  updateAiAttachFilename();
  updateFileActionButtons();
}

function updateAiAttachFilename() {
  var el = document.getElementById('ai-attach-filename');
  if (el) el.textContent = currentFile || '—';
}

function buildTree(active) {
  var wrap = document.getElementById('tree-wrap');
  wrap.innerHTML = '';
  var allPaths = getAllPaths();
  var pathCount = allPaths.length;
  var tree = {};
  allPaths.forEach(function(path) {
    var parts = path.split('/');
    var node = tree;
    for (var i = 0; i < parts.length - 1; i++) {
      if (!node[parts[i]]) node[parts[i]] = {};
      node = node[parts[i]];
    }
    node['__f__' + parts[parts.length - 1]] = path;
  });
  renderNode(tree, 0, wrap, active, pathCount);
}

function renderNode(node, depth, wrap, active, pathCount) {
  var keys = Object.keys(node).sort();
  var folders = keys.filter(function(k) { return !k.startsWith('__f__'); });
  var files = keys.filter(function(k) { return k.startsWith('__f__'); });
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
    btnRen.title = EDIT_LANG.treeRename;
    btnRen.setAttribute('aria-label', EDIT_LANG.treeRenameAria);
    btnRen.textContent = '✏️';
    btnRen.onclick = function(e) { e.stopPropagation(); renameMoveFile(fp); };
    var btnDel = document.createElement('button');
    btnDel.type = 'button';
    btnDel.className = 'tree-op-btn';
    btnDel.title = EDIT_LANG.treeDelete;
    btnDel.setAttribute('aria-label', EDIT_LANG.treeDeleteAria);
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
  Object.assign(result, edits);
  Object.keys(models).forEach(function(p) {
    result[p] = models[p].getValue();
  });
  return result;
}

function saveSkill() {
  var name = document.getElementById('skill-name-input').value.trim();
  if (!name) {
    alert(EDIT_LANG.alertFilename);
    document.getElementById('skill-name-input').focus();
    return;
  }
  var all = getAllEdits();
  if (Object.keys(all).length === 0) { alert(EDIT_LANG.alertNoFiles); return; }
  document.getElementById('form-skill-name').value = name;
  document.getElementById('form-files-json').value = JSON.stringify(all);
  document.getElementById('skill-form').submit();
}

function addFile() {
  var name = prompt(EDIT_LANG.promptNewFile);
  if (!name) return;
  name = normalizeArchivePath(name, currentFile);
  if (!name) { alert(EDIT_LANG.alertBadName); return; }
  if (getAllPaths().indexOf(name) !== -1) { selectFile(name); return; }
  removedFromArchive.delete(name);
  edits[name] = '';
  buildTree(name);
  selectFile(name);
}

function removeFile(path) {
  var all = getAllPaths();
  if (all.length <= 1) {
    alert(EDIT_LANG.alertMinFile);
    return;
  }
  if (!confirm(EDIT_LANG.confirmRemove.replace('{path}', path))) return;
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
  var name = prompt(EDIT_LANG.promptRename, oldPath);
  if (name == null) return;
  name = normalizeArchivePath(name, oldPath);
  if (!name) { alert(EDIT_LANG.alertBadPath); return; }
  if (name === oldPath) return;
  if (getAllPaths().indexOf(name) !== -1) {
    alert(EDIT_LANG.alertPathExists);
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
  if (open) currentFile = name;
  buildTree(open ? name : currentFile);
  if (open) selectFile(name);
  else updateFileActionButtons();
}

function insertTemplate() {
  if (!monacoEditor) return;
  if (monacoEditor.getValue().trim() && !confirm(EDIT_LANG.templateConfirm)) return;
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

function formatDoc() {
  if (!monacoEditor) return;
  monacoEditor.getAction('editor.action.formatDocument')?.run();
}

function openHelp() {
  var el = document.getElementById('help-content');
  if (!el._rendered) {
    el.innerHTML = marked.parse(helpMd || AI_LANG.helpEmpty);
    processMermaid(el);
    el._rendered = true;
  }
  document.getElementById('help-modal').classList.remove('hidden');
}
function closeHelp() {
  document.getElementById('help-modal').classList.add('hidden');
}

function toggleSidebar() {
  var sb = document.getElementById('sidebar');
  var ov = document.getElementById('sidebar-overlay');
  if (!sb) return;
  sb.classList.toggle('mobile-hidden');
  if (ov) ov.classList.toggle('show', !sb.classList.contains('mobile-hidden'));
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeHelp();
    closeAiSettings();
  }
  if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && document.activeElement && document.activeElement.id === 'ai-user-input') {
    e.preventDefault();
    sendAiChat();
  }
});

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
