<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../_common.php';

skill_require_admin('../login.php');

$msg     = '';
$msgType = 'success';
$current = skill_settings();
$users   = skill_users_list();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'access');

    if ($action === 'access') {
        if (skill_save_settings($_POST)) {
            $current = skill_settings();
            $msg     = __('settings.msg_saved');
        } else {
            $msg     = __('settings.msg_save_fail');
            $msgType = 'error';
        }
    } elseif ($action === 'add_user') {
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
        $err = skill_users_delete((string)($_POST['username'] ?? ''));
        if ($err === null) {
            $users = skill_users_list();
            $msg   = __('settings.user_deleted');
        } else {
            $msg     = $err;
            $msgType = 'error';
        }
    }
}

$currentUser = skill_current_username();
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
.field-select select, .field-select input[type=text], .field-select input[type=password] {
  width: 100%; max-width: 280px; padding: 8px 10px; border: 1px solid var(--border-l); border-radius: var(--r);
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
@media(max-width:720px){ .add-user-grid { grid-template-columns: 1fr; } .user-table { font-size: .78rem; } .user-table input, .user-table select { max-width: 100%; width: 100%; } }
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

</div>

<footer><?= h(APP_NAME) ?> · <?= h(__('settings.page_title')) ?></footer>
</body>
</html>
