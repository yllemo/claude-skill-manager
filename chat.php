<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_common.php';

/**
 * SKILL Chat — laddar .skill från content/ via ?file=name.skill (server-side).
 * Accepterar även äldre länkar där file= är absolut /s/…-URL.
 */
$chatBootstrap = null;
$chatLoadError = '';
$chatFilename  = '';

$fileParam = trim((string)($_GET['file'] ?? ''));
if ($fileParam !== '') {
    // Extrahera basename om en full URL eller sökväg skickats in
    if (preg_match('#([a-zA-Z0-9_\-]+\.skill)(?:\?.*)?$#', $fileParam, $m)) {
        $fileParam = $m[1];
    } else {
        $fileParam = basename($fileParam);
    }

    $skillPath = validate_file_param($fileParam);
    if ($skillPath === null) {
        $chatLoadError = __('view.skill_not_found');
    } elseif (!skill_user_can_view_file($fileParam)) {
        skill_require_skill_view($fileParam, 'login.php');
    } else {
        $chatFilename = basename($skillPath);
        $meta         = get_skill_meta($skillPath);
        $entries      = read_zip_files($skillPath);
        $filesOut     = [];
        foreach ($entries as $path => $entry) {
            $type = (string)($entry['type'] ?? 'binary');
            if ($type === 'text') {
                $filesOut[] = [
                    'path'    => $path,
                    'content' => (string)($entry['content'] ?? ''),
                    'isText'  => true,
                    'isImage' => false,
                    'dataUrl' => '',
                    'size'    => (int)($entry['size'] ?? 0),
                    'include' => true,
                ];
            } elseif ($type === 'image') {
                $filesOut[] = [
                    'path'    => $path,
                    'content' => '',
                    'isText'  => false,
                    'isImage' => true,
                    'dataUrl' => (string)($entry['content'] ?? ''),
                    'size'    => (int)($entry['size'] ?? 0),
                    'include' => false,
                ];
            } else {
                $filesOut[] = [
                    'path'    => $path,
                    'content' => '',
                    'isText'  => false,
                    'isImage' => false,
                    'dataUrl' => '',
                    'size'    => (int)($entry['size'] ?? 0),
                    'include' => false,
                ];
            }
        }
        $title = trim((string)($meta['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($chatFilename, PATHINFO_FILENAME);
        }
        $chatBootstrap = [
            'filename' => $chatFilename,
            'meta'     => [
                'name' => $title,
                'desc' => (string)($meta['description'] ?? ''),
            ],
            'files'    => $filesOut,
        ];
    }
}

$pageTitle = $chatFilename !== ''
    ? ('SKILL Chat — ' . $chatFilename)
    : 'SKILL Chat';
$backHomeUrl = './';
$backViewUrl = $chatFilename !== ''
    ? 'view/?file=' . rawurlencode($chatFilename)
    : '';
?>
<!DOCTYPE html>
<html lang="<?= h(skill_lang_html_lang()) ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<?php favicon_link('./'); ?>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NCA2NCIgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiByb2xlPSJpbWciIGFyaWEtbGFiZWw9IlNLSUxMIENoYXQiPgogIDxyZWN0IHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgcng9IjE0IiBmaWxsPSIjMDA3N2JjIi8+CiAgPHBhdGggZD0iTTIwIDQ0IEwyMCA1MyBMMzEgNDQgWiIgZmlsbD0iI2ZmZmZmZiIvPgogIDxyZWN0IHg9IjEyIiB5PSIxNSIgd2lkdGg9IjQwIiBoZWlnaHQ9IjMwIiByeD0iNyIgZmlsbD0iI2ZmZmZmZiIvPgogIDxyZWN0IHg9IjE4IiB5PSIyMiIgd2lkdGg9IjI4IiBoZWlnaHQ9IjQiIHJ4PSIyIiBmaWxsPSIjMDA3N2JjIi8+CiAgPHJlY3QgeD0iMTgiIHk9IjI5LjUiIHdpZHRoPSIyOCIgaGVpZ2h0PSI0IiByeD0iMiIgZmlsbD0iIzhDQzVENCIvPgogIDxyZWN0IHg9IjE4IiB5PSIzNyIgd2lkdGg9IjE4IiBoZWlnaHQ9IjQiIHJ4PSIyIiBmaWxsPSIjMDA3N2JjIi8+Cjwvc3ZnPgo=">
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
<script>
window.SKILL_CHAT_BOOTSTRAP = <?= json_encode($chatBootstrap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
window.SKILL_CHAT_LOAD_ERROR = <?= json_encode($chatLoadError, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
</script>
<style>
:root{
  --gs-blue:#0077bc;
  --bg-color:#FFFFFE; --bg-footer:#F5F5F5; --bg-nav:#F4F9FC; --bg-info:#F2F9F9;
  --text-color:#333333; --text-secondary:#6E6E6E; --link-color:#005799; --border-color:#979797;
  --border-soft:#e2e6e9; --bubble-user:#0077bc; --bubble-ai:#F2F9F9;
  --success:#5a8b3b; --warning:#f2a900; --error:#d24723; --info:#008391;
  --shadow:0 1px 3px rgba(0,0,0,.08),0 4px 16px rgba(0,0,0,.04);
}
[data-theme="dark"]{
  --bg-color:#1F1F1F; --bg-footer:#121212; --bg-nav:#141414; --bg-info:#282828;
  --text-color:#FFFFFF; --text-secondary:#E3E8E9; --link-color:#479EF5; --border-color:#666666;
  --border-soft:#333; --bubble-ai:#282828;
  --shadow:0 1px 3px rgba(0,0,0,.4),0 4px 16px rgba(0,0,0,.3);
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family:'Goteborg',Arial,Helvetica,sans-serif; font-size:16px; line-height:1.5;
  color:var(--text-color); background:var(--bg-color); display:flex; flex-direction:column; height:100vh; overflow:hidden;
}
button{font-family:inherit; cursor:pointer}
.btn{border-radius:4px; border:none; padding:.55rem 1rem; font-size:.95rem; font-weight:600; transition:filter .15s,background .15s}
.btn:hover{filter:brightness(1.05)}
.btn:active{filter:brightness(.95)}
.btn:disabled{opacity:.5; cursor:not-allowed}
.btn-primary{background:var(--gs-blue); color:#fff}
.btn-secondary{background:var(--bg-color); color:var(--text-color); border:1px solid var(--border-color)}
.btn-ghost{background:transparent; color:var(--text-color); border:1px solid transparent; padding:.4rem .6rem}
.btn-ghost:hover{background:rgba(127,127,127,.12)}

/* Header */
header{
  background:var(--gs-blue); color:#fff; display:flex; align-items:center; gap:.9rem;
  padding:0 1.1rem; height:60px; flex-shrink:0; box-shadow:var(--shadow); z-index:20;
}
.brand{font-weight:700; letter-spacing:.2px}
.brand a{color:#fff; text-decoration:none}
.brand .sub{opacity:.85; font-weight:400; font-size:.85rem; margin-left:.5rem; border-left:1px solid rgba(255,255,255,.4); padding-left:.6rem}
.hdr-back{
  display:inline-flex; align-items:center; gap:.35rem; color:#fff; text-decoration:none;
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25);
  padding:.4rem .75rem; border-radius:4px; font-size:.82rem; font-weight:600; white-space:nowrap;
}
.hdr-back:hover{background:rgba(255,255,255,.24)}
header .spacer{flex:1}
header .badge{
  background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.25); color:#fff;
  font-size:.78rem; padding:.3rem .65rem; border-radius:999px; display:flex; align-items:center; gap:.4rem; max-width:340px; overflow:hidden;
}
header .badge .dot{width:8px; height:8px; border-radius:50%; background:#ffd666; flex-shrink:0}
header .badge.ok .dot{background:#a6e07a}
header .badge.err .dot{background:#ffb3a3}
header .badge span{white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
.icon-btn{background:rgba(255,255,255,.12); color:#fff; border:none; width:38px; height:38px; border-radius:4px; font-size:1.1rem; display:flex; align-items:center; justify-content:center}
.icon-btn:hover{background:rgba(255,255,255,.24)}

/* Layout */
.layout{flex:1; display:flex; min-height:0}
aside{
  width:340px; flex-shrink:0; background:var(--bg-nav); border-right:1px solid var(--border-soft);
  display:flex; flex-direction:column; min-height:0;
}
.aside-scroll{overflow-y:auto; padding:1rem; flex:1}
main{flex:1; display:flex; flex-direction:column; min-height:0; min-width:0}

.section-title{font-size:.72rem; text-transform:uppercase; letter-spacing:.8px; color:var(--text-secondary); margin:1.3rem 0 .5rem; font-weight:700}
.skill-meta{background:var(--bg-info); border:1px solid var(--border-soft); border-radius:6px; padding:.8rem .9rem; margin-top:.4rem}
.skill-meta h3{margin:0 0 .3rem; font-size:1rem; color:var(--gs-blue)}
.skill-meta p{margin:0; font-size:.85rem; color:var(--text-secondary)}

.file-list{list-style:none; margin:.3rem 0 0; padding:0; display:flex; flex-direction:column; gap:.3rem}
.file-item{display:flex; align-items:center; gap:.55rem; padding:.45rem .6rem; background:var(--bg-color); border:1px solid var(--border-soft); border-radius:5px; font-size:.84rem}
.file-item input[type=checkbox]{accent-color:var(--gs-blue); width:16px; height:16px; flex-shrink:0}
.file-item .fname{flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:pointer}
.file-item .fname:hover{color:var(--gs-blue); text-decoration:underline}
.file-item .fsize{color:var(--text-secondary); font-size:.74rem; flex-shrink:0}
.file-item.binary{opacity:.6}
.file-item.binary input{visibility:hidden}
.file-item .frm{border:none; background:transparent; color:var(--text-secondary); cursor:pointer; font-size:.9rem; padding:0 2px; line-height:1}
.file-item .frm:hover{color:var(--error)}
.input-upload{
  border:2px dashed var(--border-color); border-radius:8px; padding:1rem .9rem; text-align:center;
  background:var(--bg-color); transition:border-color .15s,background .15s; cursor:pointer; margin-top:.4rem;
}
.input-upload:hover,.input-upload.drag{border-color:var(--gs-blue); background:var(--bg-info)}
.input-upload p{margin:.15rem 0; font-size:.82rem; color:var(--text-secondary)}
.input-upload strong{color:var(--text-color); font-size:.88rem}
.ctx-bar{margin-top:.7rem; font-size:.78rem; color:var(--text-secondary); display:flex; justify-content:space-between; align-items:center}
.ctx-bar .num{font-weight:700; color:var(--text-color)}

/* Chat */
.chat-scroll{flex:1; overflow-y:auto; padding:1.4rem; display:flex; flex-direction:column; gap:1rem}
.empty-state{margin:auto; text-align:center; max-width:430px; color:var(--text-secondary)}
.empty-state .ico{font-size:2.6rem; margin-bottom:.6rem}
.empty-state h2{color:var(--text-color); margin:.2rem 0 .6rem; font-weight:700}
.empty-state .hint{display:inline-flex; gap:.4rem; align-items:center; background:var(--bg-info); border:1px solid var(--border-soft); padding:.5rem .8rem; border-radius:6px; font-size:.85rem; margin-top:.3rem}

.msg{display:flex; gap:.7rem; max-width:820px; width:100%; margin:0 auto}
.msg .avatar{width:34px; height:34px; border-radius:6px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.85rem; font-weight:700; color:#fff}
.msg.user .avatar{background:var(--text-secondary)}
.msg.assistant .avatar{background:var(--gs-blue)}
.msg .body{flex:1; min-width:0}
.msg .name{font-size:.74rem; color:var(--text-secondary); font-weight:700; margin-bottom:.2rem}
.bubble{padding:.7rem .95rem; border-radius:8px; font-size:.95rem; overflow-wrap:break-word}
.msg.user .bubble{background:var(--bubble-user); color:#fff}
.msg.assistant .bubble{background:var(--bubble-ai); border:1px solid var(--border-soft)}
.bubble p{margin:.4rem 0}
.bubble p:first-child{margin-top:0}.bubble p:last-child{margin-bottom:0}
.bubble pre{background:rgba(0,0,0,.06); padding:.7rem; border-radius:5px; overflow-x:auto; font-size:.85rem}
[data-theme="dark"] .bubble pre{background:rgba(255,255,255,.07)}
.bubble code{font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.86em}
.bubble :not(pre)>code{background:rgba(0,0,0,.07); padding:.1rem .35rem; border-radius:3px}
[data-theme="dark"] .bubble :not(pre)>code{background:rgba(255,255,255,.1)}
.bubble table{border-collapse:collapse; width:100%; font-size:.85rem; margin:.4rem 0}
.bubble th,.bubble td{border:1px solid var(--border-soft); padding:.35rem .5rem; text-align:left}
.bubble a{color:var(--link-color)}
.cursor-blink::after{content:'▋'; animation:blink 1s steps(2) infinite; color:var(--gs-blue)}
@keyframes blink{50%{opacity:0}}

/* Code blocks & mermaid in chat */
.cb-block,.mmd-block{border:1px solid var(--border-soft); border-radius:8px; overflow:hidden; margin:.6rem 0; background:var(--bg-color)}
.cb-bar{display:flex; align-items:center; gap:.5rem; padding:.35rem .55rem; background:var(--bg-nav); border-bottom:1px solid var(--border-soft)}
.cb-bar .spacer{flex:1}
.cb-lang{font-size:.7rem; text-transform:uppercase; letter-spacing:.6px; font-weight:700; color:var(--text-secondary); font-family:ui-monospace,Menlo,Consolas,monospace}
.cb-seg{display:flex; gap:.25rem}
.cb-seg button{padding:.2rem .6rem; font-size:.74rem; font-weight:600; border:1px solid var(--border-color); background:var(--bg-color); color:var(--text-color); border-radius:4px}
.cb-seg button.active{background:var(--gs-blue); color:#fff; border-color:var(--gs-blue)}
.cb-copy{padding:.2rem .6rem; font-size:.74rem; font-weight:600; border:1px solid var(--border-color); background:var(--bg-color); color:var(--text-color); border-radius:4px}
.cb-copy:hover{border-color:var(--gs-blue)}
.cb-copy.copied{color:var(--success); border-color:var(--success)}
.cb-block .cb-pre,.mmd-block .cb-pre{margin:0; border:none; border-radius:0; background:transparent; padding:.7rem .85rem; overflow-x:auto; font-size:.85rem}
.mmd-diagram{background:#ffffff; padding:.9rem; overflow-x:auto; text-align:center}
.mmd-diagram svg{max-width:100%; height:auto}
.mmd-err{background:rgba(210,71,35,.1); color:var(--error); border:1px solid var(--error); border-radius:5px; padding:.6rem .8rem; font-size:.82rem; text-align:left; font-family:ui-monospace,Menlo,Consolas,monospace}

/* Composer */
.composer{border-top:1px solid var(--border-soft); padding:.9rem 1.1rem; background:var(--bg-color); flex-shrink:0}
.composer .row{display:flex; gap:.6rem; align-items:flex-end; max-width:820px; margin:0 auto}
.composer textarea{
  flex:1; resize:none; border:1px solid var(--border-color); border-radius:8px; padding:.7rem .9rem;
  font-family:inherit; font-size:.95rem; background:var(--bg-color); color:var(--text-color); line-height:1.4; max-height:160px;
}
.composer textarea:focus{outline:2px solid var(--gs-blue); outline-offset:-1px; border-color:var(--gs-blue)}
.send-btn{width:46px; height:46px; border-radius:8px; background:var(--gs-blue); color:#fff; border:none; font-size:1.2rem; flex-shrink:0; display:flex; align-items:center; justify-content:center}
.send-btn.stop{background:var(--error)}
.composer .meta{max-width:820px; margin:.4rem auto 0; font-size:.74rem; color:var(--text-secondary); display:flex; justify-content:space-between}

/* Modal */
.overlay{position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:center; justify-content:center; z-index:50; padding:1rem}
.overlay.open{display:flex}
.modal{background:var(--bg-color); border-radius:10px; width:560px; max-width:100%; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow)}
.modal-head{display:flex; align-items:center; padding:1.1rem 1.3rem; border-bottom:1px solid var(--border-soft); position:sticky; top:0; background:var(--bg-color); z-index:1}
.modal-head h2{margin:0; font-size:1.15rem; color:var(--gs-blue)}
.modal-head .spacer{flex:1}
.modal-body{padding:1.3rem}
.field{margin-bottom:1.1rem}
.field label{display:block; font-size:.82rem; font-weight:700; margin-bottom:.35rem}
.field .help{font-size:.76rem; color:var(--text-secondary); margin-top:.3rem}
.field input,.field select,.field textarea{
  width:100%; padding:.6rem .7rem; border:1px solid var(--border-color); border-radius:5px;
  font-family:inherit; font-size:.92rem; background:var(--bg-color); color:var(--text-color);
}
.field textarea{resize:vertical; min-height:90px; line-height:1.4}
.field input:focus,.field select:focus,.field textarea:focus{outline:2px solid var(--gs-blue); outline-offset:-1px}
.preset-tabs{display:flex; gap:.4rem; margin-bottom:1.1rem}
.preset-tabs button{flex:1; padding:.55rem; border:1px solid var(--border-color); background:var(--bg-color); color:var(--text-color); border-radius:5px; font-size:.85rem; font-weight:600}
.preset-tabs button.active{background:var(--gs-blue); color:#fff; border-color:var(--gs-blue)}
.model-row{display:flex; gap:.5rem}
.model-row select{flex:1}
.test-result{margin-top:.6rem; font-size:.84rem; padding:.55rem .7rem; border-radius:5px; display:none}
.test-result.show{display:block}
.test-result.ok{background:rgba(90,139,59,.15); color:var(--success); border:1px solid var(--success)}
.test-result.err{background:rgba(210,71,35,.12); color:var(--error); border:1px solid var(--error)}
.modal-foot{display:flex; gap:.6rem; justify-content:flex-end; padding:1rem 1.3rem; border-top:1px solid var(--border-soft); position:sticky; bottom:0; background:var(--bg-color)}
.note{background:var(--bg-info); border:1px solid var(--border-soft); border-radius:6px; padding:.7rem .85rem; font-size:.8rem; color:var(--text-secondary); margin-bottom:1.1rem}
.note strong{color:var(--text-color)}
.note code{background:rgba(0,0,0,.07); padding:.05rem .3rem; border-radius:3px; font-family:ui-monospace,monospace; font-size:.85em}
[data-theme="dark"] .note code{background:rgba(255,255,255,.1)}

.modal.wide{width:820px}
.fv-head .path{font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.95rem; color:var(--gs-blue); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:480px}
.fv-head .sz{font-size:.78rem; color:var(--text-secondary); margin-left:.6rem; flex-shrink:0}
.fv-toolbar{display:flex; gap:.4rem; padding:.7rem 1.3rem; border-bottom:1px solid var(--border-soft); align-items:center}
.fv-toolbar .seg{display:flex; gap:.3rem}
.fv-toolbar .seg button{padding:.35rem .8rem; font-size:.8rem; border:1px solid var(--border-color); background:var(--bg-color); color:var(--text-color); border-radius:4px; font-weight:600}
.fv-toolbar .seg button.active{background:var(--gs-blue); color:#fff; border-color:var(--gs-blue)}
.fv-toolbar .spacer{flex:1}
.fv-body{padding:1.3rem; overflow:auto; max-height:calc(90vh - 170px)}
.fv-pre{margin:0; white-space:pre-wrap; word-break:break-word; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.84rem; line-height:1.55; background:var(--bg-info); border:1px solid var(--border-soft); border-radius:6px; padding:1rem}
[data-theme="dark"] .fv-pre{background:var(--bg-nav)}
.fv-md{font-size:.95rem}
.fv-md h1,.fv-md h2,.fv-md h3{color:var(--text-color); border-bottom:1px solid var(--border-soft); padding-bottom:.3rem}
.fv-md pre{background:var(--bg-info); padding:.8rem; border-radius:5px; overflow-x:auto}
[data-theme="dark"] .fv-md pre{background:var(--bg-nav)}
.fv-md code{font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.86em}
.fv-md :not(pre)>code{background:rgba(0,0,0,.07); padding:.1rem .35rem; border-radius:3px}
[data-theme="dark"] .fv-md :not(pre)>code{background:rgba(255,255,255,.1)}
.fv-md table{border-collapse:collapse; width:100%; margin:.5rem 0}
.fv-md th,.fv-md td{border:1px solid var(--border-soft); padding:.4rem .6rem; text-align:left}
.fv-md a{color:var(--link-color)}
.fv-md img{max-width:100%}
.fv-img{display:flex; justify-content:center; align-items:center; background:repeating-conic-gradient(rgba(127,127,127,.12) 0% 25%, transparent 0% 50%) 50%/22px 22px; border-radius:6px; padding:1rem}
.fv-img img{max-width:100%; max-height:60vh; border-radius:4px}
.fv-empty{text-align:center; color:var(--text-secondary); padding:2.5rem 1rem; line-height:1.8}
.copied{color:var(--success)!important; border-color:var(--success)!important}
.toast{position:fixed; left:50%; bottom:26px; transform:translateX(-50%) translateY(20px); background:var(--gs-blue); color:#fff; padding:.7rem 1.1rem; border-radius:6px; font-size:.88rem; box-shadow:var(--shadow); z-index:80; opacity:0; transition:opacity .2s,transform .2s; pointer-events:none}
.toast.show{opacity:1; transform:translateX(-50%) translateY(0)}

@media(max-width:820px){
  aside{position:absolute; left:0; top:60px; bottom:0; z-index:15; transform:translateX(-100%); transition:transform .2s; box-shadow:var(--shadow)}
  aside.open{transform:translateX(0)}
  .menu-btn{display:flex !important}
  header .badge{max-width:100px}
  .brand .sub{display:none}
  .hdr-back{padding:.35rem .55rem; font-size:.75rem}
}
.menu-btn{display:none}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-thumb{background:var(--border-color); border-radius:5px}
</style>
</head>
<body>
<header>
  <button class="icon-btn menu-btn" id="menuBtn" title="Visa filer" aria-label="Visa filer">☰</button>
  <a class="hdr-back" href="<?= h($backHomeUrl) ?>" title="Tillbaka till översikten">← Skill Manager</a>
  <?php if ($backViewUrl !== ''): ?>
  <a class="hdr-back" href="<?= h($backViewUrl) ?>" title="Tillbaka till skill-visaren">Visa skill</a>
  <?php endif; ?>
  <div class="brand">
    <?php if ($backViewUrl !== ''): ?>
    <a href="<?= h($backViewUrl) ?>">SKILL Chat</a><span class="sub"><?= h($chatFilename) ?></span>
    <?php else: ?>
    <a href="<?= h($backHomeUrl) ?>">SKILL Chat</a>
    <?php endif; ?>
  </div>
  <div class="spacer"></div>
  <div class="badge" id="statusBadge" title="LLM-status"><span class="dot"></span><span id="statusText">Ingen modell</span></div>
  <button class="icon-btn" id="downloadBtn" title="Ladda ner chatt som Markdown" aria-label="Ladda ner chatt som Markdown">⬇</button>
  <button class="icon-btn" id="settingsBtn" title="Inställningar" aria-label="Inställningar">⚙</button>
  <button class="icon-btn" id="themeBtn" title="Växla tema" aria-label="Växla tema">◐</button>
</header>

<div class="layout">
  <aside id="sidebar">
    <div class="aside-scroll">
      <div id="skillMeta" style="display:none">
        <div class="section-title">Laddad skill</div>
        <div class="skill-meta">
          <h3 id="skillName">—</h3>
          <p id="skillDesc">—</p>
        </div>
      </div>

      <div id="inputSection">
        <div class="section-title">Kör mot fil</div>
        <div class="input-upload" id="inputDropzone" title="Ladda upp fil som skillen ska bearbeta">
          <p><strong>Ladda upp arbetsfil</strong></p>
          <p>Text, Markdown, JSON, CSV, kod …</p>
          <button type="button" class="btn btn-secondary" id="pickInputFile" style="margin-top:.55rem; font-size:.82rem; padding:.4rem .8rem">Välj fil</button>
          <input type="file" id="inWorkFile" hidden
            accept=".md,.markdown,.txt,.json,.yaml,.yml,.csv,.tsv,.html,.htm,.xml,.css,.js,.ts,.py,.php,.sql,.sh,.log,.env,.ini,.toml,.svg,.sef,.ac,.bpmn">
        </div>
        <ul class="file-list" id="inputFileList" style="margin-top:.55rem"></ul>
        <p class="help" id="inputHint" style="font-size:.75rem; color:var(--text-secondary); margin:.45rem 0 0; line-height:1.4">
          Filen skickas som indata tillsammans med skill-instruktionerna.
        </p>
      </div>

      <div id="fileSection" style="display:none">
        <div class="section-title">Skill-filer i kontext</div>
        <ul class="file-list" id="fileList"></ul>
        <div class="ctx-bar">
          <span><span class="num" id="ctxFiles">0</span> filer valda</span>
          <span>~<span class="num" id="ctxTokens">0</span> tokens</span>
        </div>
      </div>
    </div>
  </aside>

  <main>
    <div class="chat-scroll" id="chatScroll">
      <div class="empty-state" id="emptyState">
        <div class="ico">💬</div>
        <h2>Chatta med din skill</h2>
        <p>Skill-innehållet laddas automatiskt. Ladda upp en arbetsfil till vänster om skillen ska köras mot ett dokument, och ställ sedan din fråga.</p>
        <div class="hint">⚙ Ställ in LLM-anslutning först (OpenAI, LM Studio eller Ollama)</div>
      </div>
    </div>
    <div class="composer">
      <div class="row">
        <textarea id="input" rows="1" placeholder="Ställ en fråga — t.ex. bearbeta den uppladdade filen enligt skillen…"></textarea>
        <button class="send-btn" id="sendBtn" title="Skicka" aria-label="Skicka">➤</button>
      </div>
      <div class="meta">
        <span id="modelMeta">Ingen modell vald</span>
        <span id="ctxMeta"></span>
      </div>
    </div>
  </main>
</div>

<!-- Settings modal -->
<div class="overlay" id="overlay">
  <div class="modal">
    <div class="modal-head">
      <h2>LLM-inställningar</h2>
      <div class="spacer"></div>
      <button class="btn btn-ghost" id="closeModal" style="font-size:1.3rem">✕</button>
    </div>
    <div class="modal-body">
      <div class="preset-tabs">
        <button data-prov="openai">OpenAI-kompatibel</button>
        <button data-prov="lmstudio">LM Studio</button>
        <button data-prov="ollama">Ollama</button>
      </div>

      <div class="note" id="provNote"></div>

      <div class="field">
        <label for="baseUrl">Bas-URL</label>
        <input type="text" id="baseUrl" placeholder="https://api.openai.com/v1">
      </div>
      <div class="field" id="keyField">
        <label for="apiKey">API-nyckel</label>
        <input type="password" id="apiKey" placeholder="sk-…" autocomplete="off">
        <div class="help">Sparas lokalt i webbläsarens IndexedDB. Lämna tom för lokala servrar utan nyckel.</div>
      </div>
      <div class="field">
        <label for="model">Modell</label>
        <div class="model-row">
          <select id="modelSelect"><option value="">— skriv eller hämta —</option></select>
          <button class="btn btn-secondary" id="fetchModels" style="white-space:nowrap">Hämta</button>
        </div>
        <input type="text" id="model" placeholder="t.ex. gpt-4o-mini / llama3.1 / qwen2.5" style="margin-top:.5rem">
      </div>
      <div class="field">
        <label for="temp">Temperatur: <span id="tempVal">0.3</span></label>
        <input type="range" id="temp" min="0" max="1" step="0.05" value="0.3">
      </div>
      <div class="field">
        <label for="sysPrompt">Systemprompt (<code>{context}</code> = skill, <code>{input}</code> = uppladdad arbetsfil)</label>
        <textarea id="sysPrompt"></textarea>
      </div>
      <button class="btn btn-secondary" id="testBtn" style="width:100%">Testa anslutning</button>
      <div class="test-result" id="testResult"></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" id="cancelBtn">Avbryt</button>
      <button class="btn btn-primary" id="saveBtn">Spara</button>
    </div>
  </div>
</div>

<!-- File preview modal -->
<div class="overlay" id="fileOverlay">
  <div class="modal wide">
    <div class="modal-head fv-head">
      <span class="path" id="fvPath">fil</span>
      <span class="sz" id="fvSize"></span>
      <div class="spacer"></div>
      <button class="btn btn-ghost" id="fvClose" style="font-size:1.3rem">✕</button>
    </div>
    <div class="fv-toolbar" id="fvToolbar">
      <div class="seg" id="fvSeg" style="display:none">
        <button id="fvRendered">Renderad</button>
        <button id="fvRaw">Källkod</button>
      </div>
      <div class="spacer"></div>
      <button class="btn btn-secondary" id="fvCopy" style="font-size:.8rem; padding:.35rem .8rem">Kopiera</button>
    </div>
    <div class="fv-body" id="fvBody"></div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ---------- IndexedDB ---------- */
const DB_NAME='skillchat-db', STORE='kv';
function idbOpen(){return new Promise((res,rej)=>{const r=indexedDB.open(DB_NAME,1);r.onupgradeneeded=()=>{if(!r.result.objectStoreNames.contains(STORE))r.result.createObjectStore(STORE)};r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)})}
async function idbSet(k,v){const db=await idbOpen();return new Promise((res,rej)=>{const tx=db.transaction(STORE,'readwrite');tx.objectStore(STORE).put(v,k);tx.oncomplete=()=>res();tx.onerror=()=>rej(tx.error)})}
async function idbGet(k){const db=await idbOpen();return new Promise((res,rej)=>{const tx=db.transaction(STORE,'readonly');const rq=tx.objectStore(STORE).get(k);rq.onsuccess=()=>res(rq.result);rq.onerror=()=>rej(rq.error)})}

/* ---------- Config & defaults ---------- */
const DEFAULT_SYS = 'Du är en hjälpsam assistent som följer instruktionerna i skill-innehållet nedan. Använd arbetsfilen/indatan när en sådan är uppladdad. Om något saknas i materialet, säg det tydligt och gissa inte. Hänvisa gärna till vilken fil informationen kommer ifrån. Svara på svenska om inte användaren skriver på ett annat språk.\n\n=== SKILL-INNEHÅLL ===\n{context}\n\n=== ARBETSFIL / INDATA ===\n{input}';
const PRESETS = {
  openai:   {label:'OpenAI-kompatibel', baseUrl:'https://api.openai.com/v1', needsKey:true,  api:'chat', note:'Fungerar med OpenAI och alla OpenAI-kompatibla tjänster. Bas-URL ska sluta på <code>/v1</code>.'},
  lmstudio: {label:'LM Studio', baseUrl:'http://localhost:1234/v1', needsKey:false, api:'chat', note:'Starta LM Studios lokala server och slå på <strong>CORS</strong> i serverinställningarna. Standard-URL: <code>http://localhost:1234/v1</code>.'},
  ollama:   {label:'Ollama', baseUrl:'http://localhost:11434', needsKey:false, api:'ollama', note:'Kräver att Ollama tillåter denna sida. Starta med <code>OLLAMA_ORIGINS=*</code> (eller specifik origin). Bas-URL utan <code>/v1</code>: <code>http://localhost:11434</code>.'}
};
let config = {provider:'openai', baseUrl:PRESETS.openai.baseUrl, apiKey:'', model:'', temperature:0.3, sysPrompt:DEFAULT_SYS};
let editProvider = 'openai';

/* ---------- State ---------- */
let files = [];          // skill-filer {path, content, isText, size, include, source:'skill'}
let inputFiles = [];     // uppladdade arbetsfiler {path, content, isText, size}
let skillMeta = {name:'', desc:''};
let history = [];        // {role, content}
let streaming = false;
let abortCtrl = null;

const $ = id => document.getElementById(id);
let toastTimer=null;
function toast(msg){const t=$('toast'); t.textContent=msg; t.classList.add('show'); clearTimeout(toastTimer); toastTimer=setTimeout(()=>t.classList.remove('show'),2400);}

/* ---------- Theme ---------- */
async function initTheme(){
  let t='light';
  try{ t = (await idbGet('theme')) || 'light'; }catch(e){}
  document.documentElement.setAttribute('data-theme', t);
}
$('themeBtn').onclick = async ()=>{
  const cur = document.documentElement.getAttribute('data-theme');
  const next = cur==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme', next);
  await idbSet('theme', next);
};

/* ---------- File handling ---------- */
const WORK_TEXT_EXT = ['md','markdown','txt','json','yaml','yml','csv','tsv','html','htm','xml','css','js','mjs','cjs','ts','jsx','tsx','py','php','rb','go','rs','java','sql','sh','bash','ps1','log','env','ini','cfg','conf','toml','svg','sef','ac','bpmn','r','lua','pl'];
function extOf(name){return (name.split('.').pop()||'').toLowerCase();}
function isWorkTextFile(name){return WORK_TEXT_EXT.includes(extOf(name));}
function estTokens(str){return Math.ceil(str.length/4);}
function fmtSize(n){return n<1024?n+' B':n<1048576?(n/1024).toFixed(1)+' KB':(n/1048576).toFixed(1)+' MB';}
function fmtNum(n){return n.toLocaleString('sv-SE');}
function addImage(path, dataUrl, size){files.push({path:path.replace(/^\.?\//,''), content:'', isText:false, isImage:true, dataUrl, size, include:false});}

function parseFrontmatter(md){
  const m = md.match(/^---\s*\n([\s\S]*?)\n---/);
  if(!m) return {};
  const out={}; m[1].split('\n').forEach(line=>{
    const i=line.indexOf(':'); if(i>0){const k=line.slice(0,i).trim(); let v=line.slice(i+1).trim().replace(/^["']|["']$/g,''); out[k]=v;}
  });
  return out;
}

function addFile(path, content, isText){
  // strip leading folder names down to relative-ish path
  const clean = path.replace(/^\.?\//,'');
  files.push({path:clean, content:content||'', isText, size:isText?new Blob([content]).size:(content?content.length:0), include:isText});
}

function afterLoad(){
  // sort: SKILL.md first, then .md, then rest
  files.sort((a,b)=>{
    const aS=/skill\.md$/i.test(a.path)?0:/\.md$/i.test(a.path)?1:2;
    const bS=/skill\.md$/i.test(b.path)?0:/\.md$/i.test(b.path)?1:2;
    return aS-bS || a.path.localeCompare(b.path);
  });
  // detect meta from a SKILL.md
  const skill = files.find(f=>/skill\.md$/i.test(f.path)) || files.find(f=>/\.md$/i.test(f.path));
  if(skill){
    const fm = parseFrontmatter(skill.content);
    skillMeta.name = fm.name || skill.path.split('/').pop();
    skillMeta.desc = fm.description || (skill.content.replace(/^---[\s\S]*?---/,'').trim().split('\n').find(l=>l.trim()) || '').slice(0,160);
  }
  renderFiles();
}

function renderFiles(){
  if(files.length===0){
    $('skillMeta').style.display='none';
    $('fileSection').style.display='none';
    return;
  }
  $('skillMeta').style.display='block';
  $('skillName').textContent = skillMeta.name || 'Skill';
  $('skillDesc').textContent = skillMeta.desc || '';
  $('fileSection').style.display='block';
  const ul=$('fileList'); ul.innerHTML='';
  files.forEach((f,i)=>{
    const li=document.createElement('li');
    li.className='file-item'+(f.isText?'':' binary');
    li.innerHTML=`<input type="checkbox" ${f.include?'checked':''} ${f.isText?'':'disabled'} data-i="${i}">
      <span class="fname" data-open="${i}" title="Öppna ${f.path}">${f.path}</span>
      <span class="fsize">${fmtSize(f.size)}</span>`;
    ul.appendChild(li);
  });
  ul.querySelectorAll('input').forEach(c=>c.onchange=e=>{files[+e.target.dataset.i].include=e.target.checked; updateCtx();});
  ul.querySelectorAll('.fname').forEach(el=>el.onclick=()=>openFilePreview(+el.dataset.open));
  updateCtx();
}

function buildContext(){
  return files.filter(f=>f.isText&&f.include)
    .map(f=>`=== FIL: ${f.path} ===\n${f.content}`).join('\n\n');
}
function buildInputContext(){
  if(!inputFiles.length) return '(ingen arbetsfil uppladdad)';
  return inputFiles.map(f=>`=== ARBETSFIL: ${f.path} ===\n${f.content}`).join('\n\n');
}
function buildSystemPrompt(){
  const ctx = buildContext();
  const inputCtx = buildInputContext();
  let sys = (config.sysPrompt || DEFAULT_SYS).replaceAll('{context}', ctx);
  if(sys.includes('{input}')){
    sys = sys.replaceAll('{input}', inputCtx);
  }else if(inputFiles.length){
    sys += '\n\n=== ARBETSFIL / INDATA ===\n' + inputCtx;
  }
  return sys;
}
function updateCtx(){
  const sel = files.filter(f=>f.isText&&f.include);
  const skillCtx = buildContext();
  const inputCtx = inputFiles.length ? buildInputContext() : '';
  const totalTok = estTokens(skillCtx + '\n' + inputCtx);
  $('ctxFiles').textContent = sel.length;
  $('ctxTokens').textContent = fmtNum(estTokens(skillCtx));
  const parts = [];
  if(sel.length) parts.push(`${sel.length} skill-fil(er)`);
  if(inputFiles.length) parts.push(`${inputFiles.length} arbetsfil(er)`);
  parts.push(`~${fmtNum(totalTok)} tokens`);
  $('ctxMeta').textContent = parts.join(' · ');
  renderInputFiles();
}

function renderInputFiles(){
  const ul = $('inputFileList');
  if(!ul) return;
  ul.innerHTML = '';
  inputFiles.forEach((f,i)=>{
    const li = document.createElement('li');
    li.className = 'file-item';
    li.innerHTML = `<span class="fname" data-input-open="${i}" title="Förhandsvisa">${f.path}</span>
      <span class="fsize">${fmtSize(f.size)}</span>
      <button type="button" class="frm" data-input-rm="${i}" title="Ta bort">✕</button>`;
    ul.appendChild(li);
  });
  ul.querySelectorAll('[data-input-open]').forEach(el=>{
    el.onclick = ()=>openInputPreview(+el.dataset.inputOpen);
  });
  ul.querySelectorAll('[data-input-rm]').forEach(el=>{
    el.onclick = ()=>{
      inputFiles.splice(+el.dataset.inputRm, 1);
      updateCtx();
      toast('Arbetsfil borttagen');
    };
  });
}

async function addWorkFile(file){
  if(!file) return;
  if(!isWorkTextFile(file.name)){
    alert('Filtypen stöds inte som arbetsfil. Använd textbaserade format (md, txt, json, csv, kod …).');
    return;
  }
  if(file.size > 2 * 1024 * 1024){
    if(!confirm('Filen är större än 2 MB. Fortsätta ändå?')) return;
  }
  const content = await file.text();
  const path = file.name.replace(/^\.?\//,'');
  // Ersätt fil med samma namn
  inputFiles = inputFiles.filter(f=>f.path !== path);
  inputFiles.push({
    path,
    content,
    isText: true,
    size: new Blob([content]).size,
  });
  updateCtx();
  toast('Arbetsfil laddad: ' + path);
}

function openInputPreview(i){
  const f = inputFiles[i];
  if(!f) return;
  // Återanvänd fil-preview: tillfälligt via files-index-trick
  const fake = {path:f.path, content:f.content, isText:true, isImage:false, size:f.size};
  fvState.file = fake;
  $('fvPath').textContent = f.path + ' (arbetsfil)';
  $('fvPath').title = f.path;
  $('fvSize').textContent = fmtSize(f.size);
  $('fvCopy').style.display = 'inline-block';
  fvState.isMd = /\.(md|markdown)$/i.test(f.path);
  fvState.raw = !fvState.isMd;
  $('fvSeg').style.display = fvState.isMd ? 'flex' : 'none';
  renderFileView();
  $('fileOverlay').classList.add('open');
}

function wireInputUpload(){
  const dz = $('inputDropzone');
  const pick = $('pickInputFile');
  const input = $('inWorkFile');
  if(!dz || !pick || !input) return;
  pick.onclick = e=>{ e.preventDefault(); e.stopPropagation(); input.click(); };
  dz.onclick = e=>{
    if(e.target === pick || pick.contains(e.target)) return;
    input.click();
  };
  input.onchange = e=>{
    const f = e.target.files && e.target.files[0];
    if(f) addWorkFile(f).catch(err=>alert('Kunde inte läsa filen: '+err.message));
    input.value = '';
  };
  ['dragenter','dragover'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault(); dz.classList.add('drag');}));
  ['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault(); dz.classList.remove('drag');}));
  dz.addEventListener('drop', e=>{
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if(f) addWorkFile(f).catch(err=>alert('Kunde inte läsa filen: '+err.message));
  });
}

/** Ladda skill som redan lästs server-side i chat.php (?file=name.skill). */
function loadFromBootstrap(boot){
  if(!boot || !Array.isArray(boot.files)) throw new Error('Ogiltig bootstrap');
  resetFiles();
  boot.files.forEach(f=>{
    if(f.isImage && f.dataUrl){
      addImage(f.path, f.dataUrl, f.size||0);
    }else{
      addFile(f.path, f.content||'', !!f.isText);
      if(files.length){
        files[files.length-1].include = f.include !== false && !!f.isText;
        files[files.length-1].size = f.size||files[files.length-1].size;
      }
    }
  });
  if(boot.meta){
    skillMeta.name = boot.meta.name || skillMeta.name;
    skillMeta.desc = boot.meta.desc || skillMeta.desc;
  }
  afterLoad();
}
function resetFiles(){files=[]; skillMeta={name:'',desc:''};}

/* ---------- Settings modal ---------- */
function applyProviderUI(prov){
  editProvider=prov;
  const p=PRESETS[prov];
  document.querySelectorAll('.preset-tabs button').forEach(b=>b.classList.toggle('active', b.dataset.prov===prov));
  $('provNote').innerHTML=p.note;
  $('keyField').style.display = p.needsKey?'block':'block'; // always show, but optional for local
  if(!$('baseUrl').value || Object.values(PRESETS).some(x=>x.baseUrl===$('baseUrl').value)){
    $('baseUrl').value=p.baseUrl;
  }
}
document.querySelectorAll('.preset-tabs button').forEach(b=>{
  b.onclick=()=>{ $('baseUrl').value=PRESETS[b.dataset.prov].baseUrl; applyProviderUI(b.dataset.prov); $('modelSelect').innerHTML='<option value="">— skriv eller hämta —</option>'; };
});
$('temp').oninput=e=>$('tempVal').textContent=e.target.value;
$('modelSelect').onchange=e=>{if(e.target.value)$('model').value=e.target.value;};

function openSettings(){
  $('baseUrl').value=config.baseUrl;
  $('apiKey').value=config.apiKey;
  $('model').value=config.model;
  $('temp').value=config.temperature; $('tempVal').textContent=config.temperature;
  $('sysPrompt').value=config.sysPrompt;
  applyProviderUI(config.provider);
  $('testResult').className='test-result';
  $('overlay').classList.add('open');
}
function closeSettings(){$('overlay').classList.remove('open');}
$('settingsBtn').onclick=openSettings;
$('closeModal').onclick=closeSettings;
$('cancelBtn').onclick=closeSettings;
$('overlay').onclick=e=>{if(e.target===$('overlay'))closeSettings();};

$('saveBtn').onclick=async ()=>{
  config={
    provider:editProvider,
    baseUrl:$('baseUrl').value.trim(),
    apiKey:$('apiKey').value.trim(),
    model:$('model').value.trim(),
    temperature:parseFloat($('temp').value),
    sysPrompt:$('sysPrompt').value || DEFAULT_SYS
  };
  try{
    await idbSet('config', config);
    const back = await idbGet('config');         // verifiera att det faktiskt skrevs
    if(!back || back.model!==config.model || back.baseUrl!==config.baseUrl) throw new Error('verifiering misslyckades');
    updateStatus();
    closeSettings();
    toast('Inställningar sparade i webbläsaren (IndexedDB)');
  }catch(err){
    alert('Kunde inte spara inställningar i IndexedDB: '+err.message+'\n(Privat läge / blockerad lagring kan hindra detta.)');
  }
};

$('fetchModels').onclick=async ()=>{
  const base=$('baseUrl').value.trim().replace(/\/$/,'');
  const key=$('apiKey').value.trim();
  const sel=$('modelSelect');
  sel.innerHTML='<option>Hämtar…</option>';
  try{
    let names=[];
    if(editProvider==='ollama'){
      const r=await fetch(base+'/api/tags'); const j=await r.json();
      names=(j.models||[]).map(m=>m.name);
    }else{
      const h={}; if(key)h['Authorization']='Bearer '+key;
      const r=await fetch(base+'/models',{headers:h}); const j=await r.json();
      names=(j.data||[]).map(m=>m.id);
    }
    sel.innerHTML='<option value="">— välj modell —</option>'+names.map(n=>`<option value="${n}">${n}</option>`).join('');
    if(names.length===0)sel.innerHTML='<option value="">Inga modeller hittades</option>';
  }catch(err){
    sel.innerHTML='<option value="">Kunde inte hämta (CORS?)</option>';
  }
};

$('testBtn').onclick=async ()=>{
  const tr=$('testResult'); tr.className='test-result show'; tr.textContent='Testar…';
  const tmp={provider:editProvider, baseUrl:$('baseUrl').value.trim(), apiKey:$('apiKey').value.trim(), model:$('model').value.trim(), temperature:0};
  if(!tmp.model){tr.className='test-result show err'; tr.textContent='Ange en modell först.'; return;}
  try{
    let got='';
    await callLLM([{role:'user',content:'Svara kort med ordet OK.'}], t=>got+=t, null, tmp);
    tr.className='test-result show ok';
    tr.textContent='✓ Anslutning fungerar. Svar: '+(got.trim().slice(0,40)||'(tomt)');
  }catch(err){
    tr.className='test-result show err';
    tr.textContent='✗ '+err.message;
  }
};

function updateStatus(){
  const b=$('statusBadge');
  if(config.model){
    b.className='badge ok';
    $('statusText').textContent=PRESETS[config.provider].label+' · '+config.model;
    $('modelMeta').textContent=PRESETS[config.provider].label+' / '+config.model;
  }else{
    b.className='badge';
    $('statusText').textContent='Ingen modell';
    $('modelMeta').textContent='Ingen modell vald – öppna ⚙ för att ställa in';
  }
}

/* ---------- LLM call (streaming) ---------- */
async function readStream(res, onLine){
  const reader=res.body.getReader(); const dec=new TextDecoder(); let buf='';
  while(true){
    const {value,done}=await reader.read(); if(done)break;
    buf+=dec.decode(value,{stream:true});
    let idx;
    while((idx=buf.indexOf('\n'))>=0){ const line=buf.slice(0,idx); buf=buf.slice(idx+1); if(line.trim())onLine(line.trim()); }
  }
  if(buf.trim())onLine(buf.trim());
}

async function callLLM(messages, onToken, signal, cfgOverride){
  const cfg=cfgOverride||config;
  const base=cfg.baseUrl.replace(/\/$/,'');
  if(PRESETS[cfg.provider].api==='ollama'){
    const res=await fetch(base+'/api/chat',{
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({model:cfg.model, messages, stream:true, options:{temperature:cfg.temperature}}),
      signal
    });
    if(!res.ok) throw new Error('HTTP '+res.status+': '+(await res.text()).slice(0,160));
    await readStream(res, line=>{
      try{const j=JSON.parse(line); if(j.message&&j.message.content)onToken(j.message.content); if(j.error)throw new Error(j.error);}catch(e){if(e.message&&!/JSON/.test(e.message))throw e;}
    });
  }else{
    const headers={'Content-Type':'application/json'};
    if(cfg.apiKey)headers['Authorization']='Bearer '+cfg.apiKey;
    const res=await fetch(base+'/chat/completions',{
      method:'POST', headers,
      body:JSON.stringify({model:cfg.model, messages, stream:true, temperature:cfg.temperature}),
      signal
    });
    if(!res.ok) throw new Error('HTTP '+res.status+': '+(await res.text()).slice(0,160));
    await readStream(res, line=>{
      if(!line.startsWith('data:'))return;
      const data=line.slice(5).trim();
      if(data==='[DONE]')return;
      try{const j=JSON.parse(data); const d=j.choices&&j.choices[0]&&j.choices[0].delta; if(d&&d.content)onToken(d.content);}catch(e){}
    });
  }
}

/* ---------- Chat UI ---------- */
function renderMd(text){return DOMPurify.sanitize(marked.parse(text||''));}

/* ---------- Code blocks & Mermaid rendering ---------- */
let mmdCounter=0;
function makeCopyBtn(getText){
  const b=document.createElement('button'); b.className='cb-copy'; b.type='button'; b.textContent='Kopiera';
  b.onclick=async ()=>{ try{ await navigator.clipboard.writeText(getText()); b.textContent='Kopierat ✓'; b.classList.add('copied'); setTimeout(()=>{b.textContent='Kopiera'; b.classList.remove('copied');},1500);}catch(e){ b.textContent='Fel'; } };
  return b;
}
async function renderMermaid(el, source){
  if(!window.mermaid){ el.innerHTML='<div class="mmd-err">Mermaid kunde inte laddas (kontrollera nätverk/CDN).</div>'; return; }
  const id='mmd-'+(mmdCounter++);
  try{
    const {svg}=await window.mermaid.render(id, source);
    el.innerHTML=svg;
  }catch(err){
    const orphan=document.getElementById(id); if(orphan)orphan.remove();
    el.innerHTML='<div class="mmd-err">⚠️ Diagramfel: '+escapeHtml((err&&err.message)||String(err))+'</div>';
  }
}
function buildCodeBlock(pre, lang, source){
  const wrap=document.createElement('div'); wrap.className='cb-block';
  const bar=document.createElement('div'); bar.className='cb-bar';
  const l=document.createElement('span'); l.className='cb-lang'; l.textContent=lang||'kod';
  const sp=document.createElement('span'); sp.className='spacer';
  bar.append(l, sp, makeCopyBtn(()=>source));
  const npre=document.createElement('pre'); npre.className='cb-pre';
  const ncode=document.createElement('code'); if(lang)ncode.className='language-'+lang; ncode.textContent=source;
  npre.appendChild(ncode);
  wrap.append(bar, npre);
  pre.replaceWith(wrap);
}
function buildMermaidBlock(pre, source){
  const wrap=document.createElement('div'); wrap.className='mmd-block';
  const bar=document.createElement('div'); bar.className='cb-bar';
  const l=document.createElement('span'); l.className='cb-lang'; l.textContent='mermaid';
  const seg=document.createElement('div'); seg.className='cb-seg';
  const bD=document.createElement('button'); bD.type='button'; bD.textContent='Diagram'; bD.className='active';
  const bC=document.createElement('button'); bC.type='button'; bC.textContent='Kod';
  seg.append(bD, bC);
  const sp=document.createElement('span'); sp.className='spacer';
  bar.append(l, seg, sp, makeCopyBtn(()=>source));
  const dia=document.createElement('div'); dia.className='mmd-diagram';
  const npre=document.createElement('pre'); npre.className='cb-pre'; npre.style.display='none';
  const ncode=document.createElement('code'); ncode.className='language-mermaid'; ncode.textContent=source; npre.appendChild(ncode);
  bD.onclick=()=>{dia.style.display=''; npre.style.display='none'; bD.classList.add('active'); bC.classList.remove('active');};
  bC.onclick=()=>{dia.style.display='none'; npre.style.display=''; bC.classList.add('active'); bD.classList.remove('active');};
  wrap.append(bar, dia, npre);
  pre.replaceWith(wrap);
  renderMermaid(dia, source);
}
function enhanceCodeBlocks(container, opts){
  opts=opts||{}; const renderDiagrams=opts.renderDiagrams!==false;
  container.querySelectorAll('pre > code').forEach(code=>{
    const pre=code.parentElement;
    if(!pre || pre.closest('.cb-block, .mmd-block')) return;
    const m=(code.className||'').match(/language-([\w-]+)/);
    const lang=m?m[1]:'';
    const source=code.textContent;
    if(lang==='mermaid' && renderDiagrams){ buildMermaidBlock(pre, source); }
    else { buildCodeBlock(pre, lang, source); }
  });
}
function scrollBottom(){const s=$('chatScroll'); s.scrollTop=s.scrollHeight;}

function addMsg(role, text){
  $('emptyState').style.display='none';
  const wrap=document.createElement('div');
  wrap.className='msg '+role;
  wrap.innerHTML=`<div class="avatar">${role==='user'?'Du':'AI'}</div>
    <div class="body"><div class="name">${role==='user'?'Du':skillMeta.name||'Skill-assistent'}</div>
    <div class="bubble"></div></div>`;
  const bubble=wrap.querySelector('.bubble');
  if(role==='user'){bubble.textContent=text;}
  else{bubble.innerHTML=renderMd(text);}
  $('chatScroll').appendChild(wrap);
  scrollBottom();
  return bubble;
}

async function send(){
  if(streaming){ if(abortCtrl)abortCtrl.abort(); return; }
  const input=$('input'); const text=input.value.trim();
  if(!text)return;
  if(!config.model){openSettings(); return;}
  if(files.filter(f=>f.isText&&f.include).length===0){
    if(!confirm('Ingen skill-fil är laddad. Vill du fråga ändå (utan kontext)?'))return;
  }

  addMsg('user', text);
  history.push({role:'user', content:text});
  input.value=''; autoGrow();

  const sys=buildSystemPrompt();
  const messages=[{role:'system', content:sys}, ...history];

  const bubble=addMsg('assistant','');
  bubble.classList.add('cursor-blink');
  let acc='';
  streaming=true; abortCtrl=new AbortController();
  setSendState(true);
  try{
    await callLLM(messages, tok=>{ acc+=tok; bubble.innerHTML=renderMd(acc); scrollBottom(); }, abortCtrl.signal);
    if(!acc.trim())acc='*(tomt svar)*';
    history.push({role:'assistant', content:acc});
  }catch(err){
    if(err.name==='AbortError'){ acc+= (acc?'\n\n':'')+'*(avbrutet)*'; if(acc)history.push({role:'assistant',content:acc}); }
    else{ acc='⚠️ **Fel:** '+err.message+'\n\nKontrollera bas-URL, modell och att servern tillåter anrop (CORS).'; }
    bubble.innerHTML=renderMd(acc);
  }finally{
    bubble.classList.remove('cursor-blink');
    bubble.innerHTML=renderMd(acc);
    enhanceCodeBlocks(bubble, {renderDiagrams:true});
    streaming=false; abortCtrl=null; setSendState(false);
    scrollBottom();
  }
}
function setSendState(on){
  const b=$('sendBtn');
  b.classList.toggle('stop', on);
  b.innerHTML= on?'■':'➤';
  b.title= on?'Stoppa':'Skicka';
}

$('sendBtn').onclick=send;

/* ---------- Ladda ner chatt som Markdown ---------- */
function pad2(n){return String(n).padStart(2,'0');}
function downloadChatMd(){
  if(!history.length){ toast('Ingen chatt att ladda ner ännu'); return; }
  const d=new Date();
  const ymd=`${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
  const stamp=`${ymd} ${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
  let md='# SKILL Chat\n\n';
  md+=`- **Skill:** ${skillMeta.name||'—'}\n`;
  md+=`- **Modell:** ${config.model?PRESETS[config.provider].label+' / '+config.model:'—'}\n`;
  md+=`- **Exporterad:** ${stamp}\n\n---\n\n`;
  history.forEach(m=>{
    const who = m.role==='user' ? 'Du' : (skillMeta.name ? skillMeta.name+' (assistent)' : 'Skill-assistent');
    md+=`## ${who}\n\n${m.content}\n\n`;
  });
  const blob=new Blob([md],{type:'text/markdown;charset=utf-8'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a'); a.href=url; a.download=`skill-chat_${ymd}.md`;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(()=>URL.revokeObjectURL(url),1000);
  toast('Chatt nedladdad');
}
$('downloadBtn').onclick=downloadChatMd;

const input=$('input');
function autoGrow(){input.style.height='auto'; input.style.height=Math.min(input.scrollHeight,160)+'px';}
input.addEventListener('input', autoGrow);
input.addEventListener('keydown', e=>{ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault(); send();} });

/* ---------- File preview popup ---------- */
let fvState={file:null, isMd:false, raw:false};
function escapeHtml(s){return s.replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));}

function openFilePreview(i){
  const f=files[i]; if(!f)return;
  fvState.file=f;
  $('fvPath').textContent=f.path;
  $('fvPath').title=f.path;
  $('fvSize').textContent=fmtSize(f.size);
  const body=$('fvBody'); const seg=$('fvSeg'); const copy=$('fvCopy');
  if(f.isImage){
    seg.style.display='none'; copy.style.display='none';
    body.innerHTML=`<div class="fv-img"><img src="${f.dataUrl}" alt="${escapeHtml(f.path)}"></div>`;
  }else if(f.isText){
    copy.style.display='inline-block';
    fvState.isMd=/\.(md|markdown)$/i.test(f.path);
    fvState.raw=!fvState.isMd;
    seg.style.display=fvState.isMd?'flex':'none';
    renderFileView();
  }else{
    seg.style.display='none'; copy.style.display='none';
    body.innerHTML=`<div class="fv-empty">📦 Förhandsvisning är inte tillgänglig för den här filtypen.<br><strong>${escapeHtml(f.path)}</strong> · ${fmtSize(f.size)}</div>`;
  }
  $('fileOverlay').classList.add('open');
}
function renderFileView(){
  const f=fvState.file; const body=$('fvBody');
  if(fvState.isMd && !fvState.raw){
    body.innerHTML='<div class="fv-md">'+renderMd(f.content)+'</div>';
    enhanceCodeBlocks(body, {renderDiagrams:false});
  }else{
    body.innerHTML='<pre class="fv-pre">'+escapeHtml(f.content)+'</pre>';
  }
  $('fvRendered').classList.toggle('active', !fvState.raw);
  $('fvRaw').classList.toggle('active', fvState.raw);
}
$('fvRendered').onclick=()=>{fvState.raw=false; renderFileView();};
$('fvRaw').onclick=()=>{fvState.raw=true; renderFileView();};
$('fvCopy').onclick=async ()=>{
  if(!fvState.file)return;
  try{ await navigator.clipboard.writeText(fvState.file.content); const b=$('fvCopy'); b.textContent='Kopierat ✓'; b.classList.add('copied'); setTimeout(()=>{b.textContent='Kopiera'; b.classList.remove('copied');},1500); }
  catch(e){ alert('Kunde inte kopiera.'); }
};
function closeFilePreview(){$('fileOverlay').classList.remove('open');}
$('fvClose').onclick=closeFilePreview;
$('fileOverlay').onclick=e=>{if(e.target===$('fileOverlay'))closeFilePreview();};
document.addEventListener('keydown', e=>{ if(e.key==='Escape'){closeFilePreview(); closeSettings();} });

/* mobile sidebar */
$('menuBtn').onclick=()=>$('sidebar').classList.toggle('open');

/* ---------- Init ---------- */
(async function init(){
  if(!window.indexedDB){ toast('Varning: IndexedDB stöds inte – inställningar kan inte sparas'); }
  await initTheme();
  try{
    const saved=await idbGet('config');
    if(saved){
      config={...config, ...saved};
      // Uppgradera äldre systemprompt utan {input}
      if(config.sysPrompt && !String(config.sysPrompt).includes('{input}') && String(config.sysPrompt).includes('{context}')){
        config.sysPrompt = String(config.sysPrompt).replace(
          /\{context\}/,
          '{context}\n\n=== ARBETSFIL / INDATA ===\n{input}'
        );
      }
    }
  }catch(err){ console.warn('Kunde inte läsa sparade inställningar:', err); }
  updateStatus();
  wireInputUpload();
  marked.setOptions({breaks:true, gfm:true});
  if(window.mermaid){
    try{ window.mermaid.initialize({startOnLoad:false, securityLevel:'loose', theme:'base', flowchart:{htmlLabels:true, useMaxWidth:true}}); }
    catch(e){ console.warn('Mermaid init misslyckades:', e); }
  }
  if(window.SKILL_CHAT_LOAD_ERROR){
    alert(window.SKILL_CHAT_LOAD_ERROR);
  }else if(window.SKILL_CHAT_BOOTSTRAP){
    try{
      loadFromBootstrap(window.SKILL_CHAT_BOOTSTRAP);
      toast('Skill laddad');
    }catch(err){
      console.error(err);
      alert('Kunde inte ladda skill: '+err.message);
    }
  }
})();
</script>
</body>
</html>