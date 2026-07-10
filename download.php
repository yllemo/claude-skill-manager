<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_common.php';

$inline = array_key_exists('inline', $_GET) && (string)($_GET['inline'] ?? '') !== '0';
$path   = validate_file_param((string)($_GET['file'] ?? ''));

if ($path === null) {
    if ($inline) {
        skill_inline_file_error(404, __('view.skill_not_found'));
    }
    header('Location: ' . (skill_app_web_root() ?: '/') . '/');
    exit;
}

if (!skill_user_can_view_file(basename($path))) {
    if ($inline) {
        skill_inline_file_error(skill_is_authed_safe() ? 403 : 401, __('view.access_denied'));
    }
    skill_require_skill_view(basename($path), 'login.php');
}

$filename = basename($path);
$ext      = strtolower((string)($_GET['ext'] ?? 'skill'));
if ($ext !== 'zip' && $ext !== 'skill') {
    $ext = 'skill';
}
if ($ext === 'zip') {
    $filename = pathinfo($filename, PATHINFO_FILENAME) . '.zip';
}

skill_send_skill_binary($path, $filename, $inline);
