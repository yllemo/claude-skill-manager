<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_common.php';

// Redan inloggad → gå till startsidan
if (skill_is_authed()) {
    header('Location: ./');
    exit;
}

$error = '';
$back  = preg_replace('/[^\w\-\/\.\?\=\&\%]/', '', $_GET['back'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (skill_try_login($password)) {
        skill_login();
        $redirect = $back ?: './';
        header('Location: ' . $redirect);
        exit;
    }
    $error = __('login.error');
}

$cfg = skill_config();
$appName = $cfg['app_name'] ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="<?= h(skill_lang_html_lang()) ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php favicon_link(); ?>
<title><?= h(__('login.title')) ?> — <?= h($appName) ?></title>
<?php theme_script(); ?>
<?php common_css(); ?>
<style>
html,body{height:100%;overflow:hidden}
body{justify-content:center;align-items:center}

.login-card{
  background:var(--bg-card);
  border:1px solid var(--border-l);
  border-radius:var(--r-lg);
  box-shadow:var(--shadow);
  width:100%;
  max-width:360px;
  padding:32px 36px;
  display:flex;
  flex-direction:column;
  gap:20px;
}
.login-logo{
  display:flex;
  align-items:center;
  gap:12px;
  justify-content:center;
}
.login-logo-mark{
  width:42px;height:42px;
  background:var(--accent);
  border-radius:var(--r-lg);
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;
  flex-shrink:0;
}
.login-logo-text{
  font-weight:700;
  font-size:1.1rem;
  color:var(--text);
  line-height:1.2;
}
.login-logo-sub{
  font-size:.72rem;
  color:var(--text-2);
  font-weight:400;
}
.login-title{
  text-align:center;
  font-size:.85rem;
  color:var(--text-2);
  margin-top:-8px;
}
.login-field label{
  display:block;
  font-size:.7rem;
  font-weight:700;
  color:var(--text-2);
  text-transform:uppercase;
  letter-spacing:.05em;
  margin-bottom:5px;
}
.login-field input{
  width:100%;
  padding:9px 12px;
  border:1px solid var(--border-l);
  border-radius:var(--r);
  font:inherit;
  font-size:.88rem;
  background:var(--bg);
  color:var(--text);
  transition:outline .12s;
}
.login-field input:focus{
  outline:2px solid var(--accent);
  border-color:transparent;
}
.login-error{
  background:#fdecea;
  color:#8b1a1a;
  border:1px solid #f5bbb8;
  border-radius:var(--r);
  padding:8px 12px;
  font-size:.8rem;
  text-align:center;
}
[data-theme="dark"] .login-error{
  background:#3b1111;
  color:#f28b82;
  border-color:#5c2020;
}
.login-submit{
  width:100%;
  padding:10px;
  font-size:.9rem;
  font-weight:600;
}
.login-theme{
  position:fixed;
  top:12px;
  right:14px;
}
</style>
</head>
<body>

<button class="btn theme-btn login-theme" onclick="toggleTheme()" title="<?= h(__('common.theme_toggle')) ?>">🌓</button>

<div class="login-card">
  <div class="login-logo">
    <div class="login-logo-mark">📘</div>
    <div class="login-logo-text">
      <?= h($appName) ?>
      <div class="login-logo-sub"><?= h(__('common.logo_sub')) ?></div>
    </div>
  </div>

  <div class="login-title"><?= h(__('login.prompt')) ?></div>

  <?php if ($error): ?>
  <div class="login-error">⚠ <?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?php if ($back): ?>
    <input type="hidden" name="back" value="<?= h($back) ?>">
    <?php endif; ?>
    <div class="login-field">
      <label><?= h(__('login.password_label')) ?></label>
      <input type="password" name="password" autofocus autocomplete="current-password"
             placeholder="<?= h(__('login.password_placeholder')) ?>" required>
    </div>
    <div style="margin-top:16px">
      <button type="submit" class="btn btn-primary login-submit">🔑 <?= h(__('login.submit')) ?></button>
    </div>
  </form>
</div>

</body>
</html>
