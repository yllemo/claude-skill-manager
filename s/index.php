<?php
declare(strict_types=1);

/**
 * Kort publik URL till en .skill-fil (inline, för Skill Canvas m.fl.).
 * /s/markitdown.skill  eller  /s/index.php?file=markitdown.skill
 */
require_once dirname(__DIR__) . '/_auth.php';
require_once dirname(__DIR__) . '/_common.php';

$filename = skill_resolve_skill_filename_from_request();
$path     = $filename !== '' ? validate_file_param($filename) : null;

if ($path === null) {
    skill_inline_file_error(404, __('view.skill_not_found'));
}

if (!skill_user_can_view_file($filename)) {
    skill_inline_file_error(skill_is_authed_safe() ? 403 : 401, __('view.access_denied'));
}

skill_send_skill_binary($path, $filename, true);
