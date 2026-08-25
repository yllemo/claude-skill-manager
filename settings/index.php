<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../_git.php';
require_once __DIR__ . '/../_links.php';

skill_require_admin('../login.php');

$msg     = '';
$msgType = 'success';
$syncLog = [];
$current = skill_settings();
$users   = skill_users_list();
$git     = skill_git_config();
$linksFilterType = (string)($_GET['type'] ?? '');
$linksFilterStatus = (string)($_GET['status'] ?? '');
$editLinkId = (string)($_GET['edit'] ?? '');
$tab     = (string)($_GET['tab'] ?? 'access');
if (!in_array($tab, ['access', 'git', 'links'], true)) {
    $tab = 'access';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'access');

    if ($action === 'access') {
        $tab = 'access';
        if (skill_save_settings($_POST)) {
            $current = skill_settings();
            $msg     = __('settings.msg_saved');
        } else {
            $msg     = __('settings.msg_save_fail');
            $msgType = 'error';
        }
    } elseif ($action === 'add_user') {
        $tab = 'access';
        $err = skill_users_add(
            (string)($_POST['new_username'] ?? ''),
            (string)($_POST['new_password'] ?? ''),
            (string)($_POST['new_role'] ?? 'user')
        );
        if ($err === null) {
            $users = skill_users_list();
            $msg   = __('settings.user_added');
        } else {
            $msg     = $err;
            $msgType = 'error';
        }
    } elseif ($action === 'update_user') {
        $tab = 'access';
        $err = skill_users_update(
            (string)($_POST['username'] ?? ''),
            (string)($_POST['role'] ?? 'user'),
            (string)($_POST['new_password'] ?? '')
        );
        if ($err === null) {
            $users = skill_users_list();
            $msg   = __('settings.user_updated');
        } else {
            $msg     = $err;
            $msgType = 'error';
        }
    } elseif ($action === 'delete_user') {
        $tab = 'access';
        $err = skill_users_delete((string)($_POST['username'] ?? ''));
        if ($err === null) {
            $users = skill_users_list();
            $msg   = __('settings.user_deleted');
        } else {
            $msg     = $err;
            $msgType = 'error';
        }
    } elseif ($action === 'save_git') {
        $tab = 'git';
        if (skill_git_save_config($_POST)) {
            $git = skill_git_config();
            $msg = __('settings.git_saved');
        } else {
            $msg     = __('settings.msg_save_fail');
            $msgType = 'error';
        }
    } elseif ($action === 'test_git') {
        $tab = 'git';
        // Använd ev. osparade formulärvärden för test (token tom = behåll sparad)
        $probe = skill_git_config();
        foreach (['provider', 'owner', 'repo', 'branch', 'gitlab_url', 'path_prefix'] as $k) {
            if (isset($_POST[$k])) {
                $probe[$k] = trim((string)$_POST[$k]);
            }
        }
        $token = trim((string)($_POST['token'] ?? ''));
        if ($token !== '') {
            $probe['token'] = $token;
        }
        $probe['provider'] = strtolower((string)$probe['provider']) === 'gitlab' ? 'gitlab' : 'github';
        $test = skill_git_test_connection($probe);
        $msg = $test['message'];
        $msgType = $test['ok'] ? 'success' : 'error';
    } elseif ($action === 'sync_git') {
        $tab = 'git';
        @set_time_limit(300);
        $result = skill_git_sync_content();
        $git = skill_git_config();
        $syncLog = $result['log'];
        if ($result['ok']) {
            $msg = __('settings.git_sync_ok');
        } else {
            $msg = __('settings.git_sync_partial');
            $msgType = $result['created'] + $result['updated'] + $result['deleted'] > 0 ? 'success' : 'error';
            if ($result['errors'] !== []) {
                $syncLog = array_merge($syncLog, array_map(
                    static fn(string $e): string => '⚠ ' . $e,
                    $result['errors']
                ));
                if ($result['created'] + $result['updated'] + $result['deleted'] === 0) {
                    $msgType = 'error';
                    $msg = __('settings.git_sync_fail');
                }
            }
        }
    } elseif ($action === 'add_link') {
        $tab = 'links';
        $err = skill_links_add($_POST);
        if ($err === null) {
            $msg = __('settings.links_added');
            $editLinkId = '';
        } else {
            $msg = $err;
            $msgType = 'error';
        }
    } elseif ($action === 'update_link') {
        $tab = 'links';
        $err = skill_links_update($_POST);
        if ($err === null) {
            $msg = __('settings.links_updated');
            $editLinkId = '';
        } else {
            $msg = $err;
            $msgType = 'error';
            $editLinkId = (string)($_POST['id'] ?? '');
        }
    } elseif ($action === 'delete_link') {
        $tab = 'links';
        $err = skill_links_delete((string)($_POST['id'] ?? ''));
        if ($err === null) {
            $msg = __('settings.links_deleted');
            $editLinkId = '';
        } else {
            $msg = $err;
            $msgType = 'error';
        }
    }
}

$currentUser = skill_current_username();
$tokenSet = trim((string)($git['token'] ?? '')) !== '';
$localSkillCount = count(glob(CONTENT_DIR . '*.skill') ?: []);
$linkItems = skill_links_list(
    $linksFilterType !== '' ? $linksFilterType : null,
    $linksFilterStatus !== '' ? $linksFilterStatus : null
);
$editLink = null;
if ($editLinkId !== '') {
    foreach (skill_links_list() as $row) {
        if (($row['id'] ?? '') === $editLinkId) {
            $editLink = $row;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= h(skill_lang_html_lang()) ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php favicon_link('../'); ?>
<title><?= h(__('settings.page_title')) ?> — <?= h(APP_NAME) ?></title>
<?php theme_script(); ?>
<?php common_css(); ?>
<style>
html, body { height: auto; overflow: auto; }
.main { flex: 1; padding: 22px 24px; max-width: 860px; margin: 0 auto; width: 100%; }
.msg { padding: 10px 16px; border-radius: var(--r); margin-bottom: 18px; font-size: .85rem; font-weight: 500; }
.msg.success { background: #e6f4ec; color: #2d6a3f; border: 1px solid #a3d4b0; }
.msg.error   { background: #fdecea; color: #8b1a1a; border: 1px solid #f5bbb8; }
[data-theme="dark"] .msg.success { background: #1a3526; color: #6fcf97; border-color: #2a5438; }
[data-theme="dark"] .msg.error   { background: #3b1111; color: #f28b82; border-color: #5c2020; }
.settings-card { background: var(--bg); border: 1px solid var(--border-l); border-radius: var(--r-lg); padding: 20px 22px; margin-bottom: 18px; }
.settings-card h2 { font-size: .95rem; color: var(--accent); margin: 0 0 14px; }
.settings-intro { font-size: .84rem; color: var(--text-2); line-height: 1.55; margin-bottom: 18px; }
.field { margin-bottom: 16px; }
.field label { display: flex; align-items: flex-start; gap: 10px; font-size: .86rem; cursor: pointer; }
.field label input[type=checkbox] { margin-top: 3px; flex-shrink: 0; }
.field .field-hint { font-size: .78rem; color: var(--text-2); margin: 6px 0 0 26px; line-height: 1.45; }
.field-select label { display: block; font-size: .82rem; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.field-select select, .field-select input[type=text], .field-select input[type=password], .field-select input[type=url], .field-select input[type=number] {
  width: 100%; max-width: 420px; padding: 8px 10px; border: 1px solid var(--border-l); border-radius: var(--r);
  font: inherit; font-size: .84rem; background: var(--bg); color: var(--text);
}
.field-select .field-hint { margin-left: 0; margin-top: 6px; }
.settings-meta { font-size: .78rem; color: var(--text-2); margin-top: 8px; }
.form-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.user-table { width: 100%; border-collapse: collapse; font-size: .82rem; margin-bottom: 14px; }
.user-table th, .user-table td { padding: 8px 10px; border-bottom: 1px solid var(--border-l); text-align: left; vertical-align: middle; }
.user-table th { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: var(--text-2); }
.user-table input[type=password], .user-table select { max-width: 160px; padding: 5px 8px; font-size: .8rem; border: 1px solid var(--border-l); border-radius: var(--r); background: var(--bg); color: var(--text); }
.user-row-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.user-you { font-size: .72rem; color: var(--text-2); margin-left: 4px; }
.add-user-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; align-items: end; max-width: 100%; }
.settings-tabs { display: flex; gap: 4px; margin-bottom: 18px; border-bottom: 1px solid var(--border-l); padding-bottom: 0; }
.settings-tab {
  padding: 8px 14px; font-size: .84rem; font-weight: 600; text-decoration: none; color: var(--text-2);
  border: 1px solid transparent; border-bottom: none; border-radius: var(--r) var(--r) 0 0; margin-bottom: -1px;
  background: transparent;
}
.settings-tab:hover { color: var(--accent); }
.settings-tab.active {
  color: var(--accent); background: var(--bg); border-color: var(--border-l); border-bottom-color: var(--bg);
}
.git-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
.git-grid .full { grid-column: 1 / -1; }
.sync-log {
  margin-top: 12px; max-height: 240px; overflow: auto; font-size: .78rem; line-height: 1.45;
  background: var(--bg-nav); border: 1px solid var(--border-l); border-radius: var(--r); padding: 10px 12px;
  white-space: pre-wrap; font-family: ui-monospace, Consolas, monospace;
}
.field-select textarea {
  width: 100%; max-width: 100%; min-height: 72px; padding: 8px 10px; border: 1px solid var(--border-l);
  border-radius: var(--r); font: inherit; font-size: .84rem; background: var(--bg); color: var(--text); resize: vertical;
}
.links-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; align-items: end; }
.links-filters .field-select { margin: 0; }
.links-filters select { max-width: 160px; }
.link-list { display: flex; flex-direction: column; gap: 10px; }
.link-card {
  border: 1px solid var(--border-l); border-radius: var(--r); padding: 12px 14px; background: var(--bg-nav);
}
.link-card-top { display: flex; flex-wrap: wrap; gap: 8px; align-items: baseline; justify-content: space-between; }
.link-card-title { font-size: .9rem; font-weight: 700; color: var(--text); margin: 0; }
.link-card-title a { color: var(--accent); text-decoration: none; }
.link-card-title a:hover { text-decoration: underline; }
.link-badges { display: flex; flex-wrap: wrap; gap: 5px; }
.link-badge {
  font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em;
  padding: 2px 7px; border-radius: 999px; border: 1px solid var(--border-l); color: var(--text-2); background: var(--bg);
}
.link-badge.type-repo { color: #005799; border-color: #9ec9e8; }
.link-badge.type-collection { color: #008391; border-color: #8fd0d6; }
.link-badge.type-page { color: #3f5564; border-color: #c5ced4; }
.link-badge.type-idea { color: #8a6d00; border-color: #e6d48a; background: #fff8e0; }
.link-badge.status-planned { color: #005fa3; }
.link-badge.status-done { color: #2d6a3f; }
.link-badge.status-archived { color: #888; }
.link-notes { font-size: .8rem; color: var(--text-2); margin: 8px 0 0; white-space: pre-wrap; line-height: 1.45; }
.link-meta { font-size: .72rem; color: var(--text-2); margin-top: 6px; }
.link-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
@media(max-width:720px){
  .add-user-grid, .git-grid { grid-template-columns: 1fr; }
  .user-table { font-size: .78rem; }
  .user-table input, .user-table select { max-width: 100%; width: 100%; }
}
</style>
</head>
<body>

<header class="header">
  <a href="../" style="display:flex;align-items:center;gap:10px;text-decoration:none">
    <div class="logo-mark">📘</div>
    <div class="logo-text"><?= h(APP_NAME) ?><span class="logo-sub"><?= h(__('settings.logo_sub')) ?></span></div>
  </a>
  <div class="hdr-sep"></div>
  <div class="hdr-title"><?= h(__('settings.page_title')) ?> · <?= h($currentUser) ?></div>
  <div class="hdr-actions">
    <a href="../" class="btn btn-white btn-sm">← <?= h(__('common.back')) ?></a>
    <a href="../logout.php" class="btn btn-white btn-sm" onclick="return confirm(<?= json_encode(__('common.confirm_logout'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)">🔓 <?= h(__('common.logout')) ?></a>
    <button class="theme-btn" onclick="toggleTheme()" title="<?= h(__('common.theme_toggle')) ?>">🌓</button>
  </div>
</header>

<div class="main">

  <?php if ($msg): ?>
  <div class="msg <?= h($msgType) ?>"><?= h((string)$msg) ?></div>
  <?php endif; ?>

  <nav class="settings-tabs" aria-label="<?= h(__('settings.tabs_aria')) ?>">
    <a class="settings-tab <?= $tab === 'access' ? 'active' : '' ?>" href="?tab=access"><?= h(__('settings.tab_access')) ?></a>
    <a class="settings-tab <?= $tab === 'links' ? 'active' : '' ?>" href="?tab=links"><?= h(__('settings.tab_links')) ?></a>
    <a class="settings-tab <?= $tab === 'git' ? 'active' : '' ?>" href="?tab=git"><?= h(__('settings.tab_git')) ?></a>
  </nav>

  <?php if ($tab === 'access'): ?>

  <p class="settings-intro"><?= __('settings.intro') ?></p>

  <form method="POST">
    <input type="hidden" name="action" value="access">
    <div class="settings-card">
      <h2><?= h(__('settings.section_access')) ?></h2>

      <div class="field">
        <label>
          <input type="checkbox" name="allow_guest_access" value="1"
            <?= !empty($current['allow_guest_access']) ? 'checked' : '' ?>>
          <span><?= h(__('settings.allow_guest_access')) ?></span>
        </label>
        <p class="field-hint"><?= __('settings.allow_guest_access_hint') ?></p>
      </div>

      <div class="field">
        <label>
          <input type="checkbox" name="use_skill_visibility" value="1"
            <?= !empty($current['use_skill_visibility']) ? 'checked' : '' ?>>
          <span><?= h(__('settings.use_skill_visibility')) ?></span>
        </label>
        <p class="field-hint"><?= __('settings.use_skill_visibility_hint') ?></p>
      </div>

      <div class="field field-select">
        <label for="default_skill_visibility"><?= h(__('settings.default_visibility')) ?></label>
        <select name="default_skill_visibility" id="default_skill_visibility">
          <option value="public" <?= ($current['default_skill_visibility'] ?? '') === 'public' ? 'selected' : '' ?>>
            <?= h(__('settings.visibility_public')) ?>
          </option>
          <option value="internal" <?= ($current['default_skill_visibility'] ?? '') === 'internal' ? 'selected' : '' ?>>
            <?= h(__('settings.visibility_internal')) ?>
          </option>
        </select>
        <p class="field-hint"><?= __('settings.default_visibility_hint') ?></p>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-success btn-sm">💾 <?= h(__('settings.save_access')) ?></button>
      </div>
    </div>
  </form>

  <div class="settings-card">
    <h2><?= h(__('settings.section_users')) ?></h2>
    <p class="field-hint" style="margin-left:0;margin-bottom:12px"><?= __('settings.users_intro') ?></p>

    <table class="user-table">
      <thead>
        <tr>
          <th><?= h(__('settings.user_col_name')) ?></th>
          <th><?= h(__('settings.user_col_manage')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $uname => $urec): ?>
        <tr>
          <td>
            <strong><?= h($uname) ?></strong>
            <?php if (strcasecmp($uname, $currentUser) === 0): ?>
            <span class="user-you">(<?= h(__('settings.user_you')) ?>)</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="user-row-actions">
            <form method="POST" class="user-row-actions" style="display:contents">
              <input type="hidden" name="action" value="update_user">
              <input type="hidden" name="username" value="<?= h($uname) ?>">
              <select name="role" aria-label="<?= h(__('settings.user_col_role')) ?>">
                <option value="admin" <?= $urec['role'] === 'admin' ? 'selected' : '' ?>><?= h(__('settings.role_admin')) ?></option>
                <option value="user" <?= $urec['role'] !== 'admin' ? 'selected' : '' ?>><?= h(__('settings.role_user')) ?></option>
              </select>
              <input type="password" name="new_password" autocomplete="new-password"
                     placeholder="<?= h(__('settings.user_password_placeholder')) ?>">
              <button type="submit" class="btn btn-xs btn-success">💾 <?= h(__('common.save')) ?></button>
            </form>
            <?php if (strcasecmp($uname, $currentUser) !== 0): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm(<?= json_encode(__('settings.user_confirm_delete', ['name' => $uname]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="username" value="<?= h($uname) ?>">
              <button type="submit" class="btn btn-xs btn-danger">🗑 <?= h(__('common.delete')) ?></button>
            </form>
            <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <h3 style="font-size:.85rem;margin:16px 0 10px;color:var(--text)"><?= h(__('settings.add_user')) ?></h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="add-user-grid">
        <div class="field-select">
          <label for="new_username"><?= h(__('settings.user_col_name')) ?></label>
          <input type="text" id="new_username" name="new_username" required pattern="[A-Za-z][A-Za-z0-9_\-]{2,31}"
                 autocomplete="username" placeholder="editor">
        </div>
        <div class="field-select">
          <label for="new_password"><?= h(__('settings.user_col_password')) ?></label>
          <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
        </div>
        <div class="field-select">
          <label for="new_role"><?= h(__('settings.user_col_role')) ?></label>
          <select id="new_role" name="new_role">
            <option value="user"><?= h(__('settings.role_user')) ?></option>
            <option value="admin"><?= h(__('settings.role_admin')) ?></option>
          </select>
        </div>
        <div>
          <button type="submit" class="btn btn-sm btn-primary">➕ <?= h(__('settings.add_user_btn')) ?></button>
        </div>
      </div>
    </form>
    <p class="settings-meta"><?= __('settings.config_path', ['path' => 'config/users.php']) ?></p>
  </div>

  <div class="settings-card">
    <h2><?= h(__('settings.section_frontmatter')) ?></h2>
    <p class="field-hint" style="margin-left:0"><?= __('settings.frontmatter_help') ?></p>
    <pre class="settings-meta" style="background:var(--bg-nav);padding:12px;border-radius:var(--r);border:1px solid var(--border-l);overflow-x:auto;margin-top:10px"><code>---
title: My Skill
visibility: public
---</code></pre>
    <p class="settings-meta"><?= __('settings.config_path', ['path' => 'config/settings.php']) ?></p>
  </div>

  <?php elseif ($tab === 'links'): ?>

  <p class="settings-intro"><?= __('settings.links_intro') ?></p>

  <div class="settings-card">
    <h2><?= h($editLink ? __('settings.links_edit') : __('settings.links_add')) ?></h2>
    <form method="POST">
      <input type="hidden" name="action" value="<?= $editLink ? 'update_link' : 'add_link' ?>">
      <?php if ($editLink): ?>
      <input type="hidden" name="id" value="<?= h((string)$editLink['id']) ?>">
      <?php endif; ?>
      <div class="git-grid">
        <div class="field-select full">
          <label for="link_title"><?= h(__('settings.links_col_title')) ?></label>
          <input type="text" id="link_title" name="title" required maxlength="200"
                 value="<?= h((string)($editLink['title'] ?? '')) ?>"
                 placeholder="<?= h(__('settings.links_title_placeholder')) ?>">
        </div>
        <div class="field-select full">
          <label for="link_url"><?= h(__('settings.links_col_url')) ?></label>
          <input type="url" id="link_url" name="url" maxlength="2000"
                 value="<?= h((string)($editLink['url'] ?? '')) ?>"
                 placeholder="https://…">
          <p class="field-hint"><?= __('settings.links_url_hint') ?></p>
        </div>
        <div class="field-select">
          <label for="link_type"><?= h(__('settings.links_col_type')) ?></label>
          <select id="link_type" name="type">
            <?php foreach (skill_link_types() as $t): ?>
            <option value="<?= h($t) ?>" <?= (($editLink['type'] ?? 'idea') === $t) ? 'selected' : '' ?>>
              <?= h(skill_link_type_label($t)) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-select">
          <label for="link_status"><?= h(__('settings.links_col_status')) ?></label>
          <select id="link_status" name="status">
            <?php foreach (skill_link_statuses() as $s): ?>
            <option value="<?= h($s) ?>" <?= (($editLink['status'] ?? 'planned') === $s) ? 'selected' : '' ?>>
              <?= h(skill_link_status_label($s)) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-select">
          <label for="link_tags"><?= h(__('settings.links_col_tags')) ?></label>
          <input type="text" id="link_tags" name="tags" maxlength="200"
                 value="<?= h((string)($editLink['tags'] ?? '')) ?>"
                 placeholder="docs, mermaid, idea">
        </div>
        <div class="field-select">
          <label for="link_sort"><?= h(__('settings.links_col_sort')) ?></label>
          <input type="number" id="link_sort" name="sort" value="<?= h((string)($editLink['sort'] ?? '0')) ?>">
        </div>
        <div class="field-select full">
          <label for="link_notes"><?= h(__('settings.links_col_notes')) ?></label>
          <textarea id="link_notes" name="notes" maxlength="4000"
                    placeholder="<?= h(__('settings.links_notes_placeholder')) ?>"><?= h((string)($editLink['notes'] ?? '')) ?></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-success btn-sm">💾 <?= h($editLink ? __('common.save') : __('settings.links_add_btn')) ?></button>
        <?php if ($editLink): ?>
        <a class="btn btn-white btn-sm" href="?tab=links"><?= h(__('common.cancel')) ?></a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="settings-card">
    <h2><?= h(__('settings.links_section_list')) ?></h2>

    <form method="GET" class="links-filters">
      <input type="hidden" name="tab" value="links">
      <div class="field-select">
        <label for="filter_type"><?= h(__('settings.links_col_type')) ?></label>
        <select id="filter_type" name="type" onchange="this.form.submit()">
          <option value=""><?= h(__('settings.links_filter_all')) ?></option>
          <?php foreach (skill_link_types() as $t): ?>
          <option value="<?= h($t) ?>" <?= $linksFilterType === $t ? 'selected' : '' ?>><?= h(skill_link_type_label($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field-select">
        <label for="filter_status"><?= h(__('settings.links_col_status')) ?></label>
        <select id="filter_status" name="status" onchange="this.form.submit()">
          <option value=""><?= h(__('settings.links_filter_all')) ?></option>
          <?php foreach (skill_link_statuses() as $s): ?>
          <option value="<?= h($s) ?>" <?= $linksFilterStatus === $s ? 'selected' : '' ?>><?= h(skill_link_status_label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <?php if ($linkItems === []): ?>
    <p class="settings-meta"><?= h(__('settings.links_empty')) ?></p>
    <?php else: ?>
    <div class="link-list">
      <?php foreach ($linkItems as $item): ?>
      <article class="link-card">
        <div class="link-card-top">
          <h3 class="link-card-title">
            <?php if (!empty($item['url'])): ?>
            <a href="<?= h((string)$item['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$item['title']) ?></a>
            <?php else: ?>
            <?= h((string)$item['title']) ?>
            <?php endif; ?>
          </h3>
          <div class="link-badges">
            <span class="link-badge type-<?= h((string)$item['type']) ?>"><?= h(skill_link_type_label((string)$item['type'])) ?></span>
            <span class="link-badge status-<?= h((string)$item['status']) ?>"><?= h(skill_link_status_label((string)$item['status'])) ?></span>
          </div>
        </div>
        <?php if (!empty($item['notes'])): ?>
        <p class="link-notes"><?= h((string)$item['notes']) ?></p>
        <?php endif; ?>
        <p class="link-meta">
          <?php if (!empty($item['tags'])): ?>
          <?= h(__('settings.links_col_tags')) ?>: <?= h((string)$item['tags']) ?> ·
          <?php endif; ?>
          <?php if (!empty($item['url'])): ?>
          <span title="<?= h((string)$item['url']) ?>"><?= h(preg_replace('#^https?://#i', '', (string)$item['url'])) ?></span>
          <?php endif; ?>
        </p>
        <div class="link-actions">
          <a class="btn btn-xs btn-white" href="?tab=links&amp;edit=<?= h(urlencode((string)$item['id'])) ?>"><?= h(__('common.edit')) ?></a>
          <form method="POST" style="display:inline" onsubmit="return confirm(<?= json_encode(__('settings.links_confirm_delete', ['title' => (string)$item['title']]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)">
            <input type="hidden" name="action" value="delete_link">
            <input type="hidden" name="id" value="<?= h((string)$item['id']) ?>">
            <button type="submit" class="btn btn-xs btn-danger">🗑 <?= h(__('common.delete')) ?></button>
          </form>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="settings-meta"><?= __('settings.config_path', ['path' => 'config/links.php']) ?></p>
  </div>

  <?php else: /* tab=git */ ?>

  <p class="settings-intro"><?= __('settings.git_intro') ?></p>

  <form method="POST" id="git-form">
    <div class="settings-card">
      <h2><?= h(__('settings.git_section_connection')) ?></h2>

      <div class="field">
        <label>
          <input type="checkbox" name="enabled" value="1" <?= !empty($git['enabled']) ? 'checked' : '' ?>>
          <span><?= h(__('settings.git_enabled')) ?></span>
        </label>
        <p class="field-hint"><?= __('settings.git_enabled_hint') ?></p>
      </div>

      <div class="git-grid">
        <div class="field-select">
          <label for="provider"><?= h(__('settings.git_provider')) ?></label>
          <select name="provider" id="provider">
            <option value="github" <?= ($git['provider'] ?? '') === 'github' ? 'selected' : '' ?>>GitHub</option>
            <option value="gitlab" <?= ($git['provider'] ?? '') === 'gitlab' ? 'selected' : '' ?>>GitLab</option>
          </select>
        </div>
        <div class="field-select" id="gitlab-url-wrap">
          <label for="gitlab_url"><?= h(__('settings.git_gitlab_url')) ?></label>
          <input type="text" name="gitlab_url" id="gitlab_url" value="<?= h((string)($git['gitlab_url'] ?? 'https://gitlab.com')) ?>"
                 placeholder="https://gitlab.com">
          <p class="field-hint"><?= __('settings.git_gitlab_url_hint') ?></p>
        </div>
        <div class="field-select">
          <label for="owner"><?= h(__('settings.git_owner')) ?></label>
          <input type="text" name="owner" id="owner" value="<?= h((string)($git['owner'] ?? '')) ?>"
                 placeholder="org-or-user" autocomplete="off">
        </div>
        <div class="field-select">
          <label for="repo"><?= h(__('settings.git_repo')) ?></label>
          <input type="text" name="repo" id="repo" value="<?= h((string)($git['repo'] ?? '')) ?>"
                 placeholder="my-skills-repo" autocomplete="off">
        </div>
        <div class="field-select">
          <label for="branch"><?= h(__('settings.git_branch')) ?></label>
          <input type="text" name="branch" id="branch" value="<?= h((string)($git['branch'] ?? 'main')) ?>"
                 placeholder="main" autocomplete="off">
        </div>
        <div class="field-select">
          <label for="path_prefix"><?= h(__('settings.git_path_prefix')) ?></label>
          <input type="text" name="path_prefix" id="path_prefix" value="<?= h((string)($git['path_prefix'] ?? 'content')) ?>"
                 placeholder="content" autocomplete="off">
          <p class="field-hint"><?= __('settings.git_path_prefix_hint') ?></p>
        </div>
        <div class="field-select full">
          <label for="token"><?= h(__('settings.git_token')) ?></label>
          <input type="password" name="token" id="token" value="" autocomplete="new-password"
                 placeholder="<?= h($tokenSet ? __('settings.git_token_keep') : __('settings.git_token_placeholder')) ?>">
          <p class="field-hint"><?= __('settings.git_token_hint') ?></p>
        </div>
        <div class="field-select full">
          <label for="commit_message"><?= h(__('settings.git_commit_message')) ?></label>
          <input type="text" name="commit_message" id="commit_message"
                 value="<?= h((string)($git['commit_message'] ?? '')) ?>" autocomplete="off">
        </div>
      </div>

      <div class="field" style="margin-top:8px">
        <label>
          <input type="checkbox" name="delete_missing" value="1" <?= !empty($git['delete_missing']) ? 'checked' : '' ?>>
          <span><?= h(__('settings.git_delete_missing')) ?></span>
        </label>
        <p class="field-hint"><?= __('settings.git_delete_missing_hint') ?></p>
      </div>

      <div class="form-actions">
        <button type="submit" name="action" value="save_git" class="btn btn-success btn-sm">💾 <?= h(__('settings.git_save')) ?></button>
        <button type="submit" name="action" value="test_git" class="btn btn-white btn-sm">🔌 <?= h(__('settings.git_test')) ?></button>
      </div>
      <p class="settings-meta"><?= __('settings.config_path', ['path' => 'config/git.php']) ?></p>
    </div>
  </form>

  <div class="settings-card">
    <h2><?= h(__('settings.git_section_sync')) ?></h2>
    <p class="field-hint" style="margin-left:0;margin-bottom:12px">
      <?= __('settings.git_sync_intro', ['n' => (string)$localSkillCount]) ?>
    </p>
    <?php if (!empty($git['last_sync_at'])): ?>
    <p class="settings-meta" style="margin-bottom:12px">
      <?= h(__('settings.git_last_sync', [
          'when' => (string)$git['last_sync_at'],
          'status' => (string)$git['last_sync_status'],
          'summary' => (string)$git['last_sync_summary'],
      ])) ?>
    </p>
    <?php endif; ?>
    <form method="POST" onsubmit="return confirm(<?= json_encode(__('settings.git_sync_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)">
      <input type="hidden" name="action" value="sync_git">
      <button type="submit" class="btn btn-primary btn-sm"
        <?= (empty($git['enabled']) || !skill_git_is_configured()) ? 'disabled title="' . h(__('settings.git_sync_disabled_title')) . '"' : '' ?>>
        🔄 <?= h(__('settings.git_sync_btn')) ?>
      </button>
    </form>
    <?php if ($syncLog !== []): ?>
    <div class="sync-log"><?php foreach ($syncLog as $line): ?><?= h($line) . "\n" ?><?php endforeach; ?></div>
    <?php endif; ?>
  </div>

  <script>
  (function(){
    var provider = document.getElementById('provider');
    var wrap = document.getElementById('gitlab-url-wrap');
    function sync(){ wrap.style.display = provider.value === 'gitlab' ? '' : 'none'; }
    provider.addEventListener('change', sync);
    sync();
  })();
  </script>

  <?php endif; ?>

</div>

<footer><?= h(APP_NAME) ?> · <?= h(__('settings.page_title')) ?></footer>
</body>
</html>
