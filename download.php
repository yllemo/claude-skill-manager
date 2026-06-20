<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_common.php';

$path = validate_file_param((string)($_GET['file'] ?? ''));
if (!$path) {
    header('Location: ./');
    exit;
}

skill_require_skill_view(basename($path), 'login.php');

$filename = basename($path);
$ext = strtolower((string)($_GET['ext'] ?? 'skill'));
if ($ext !== 'zip' && $ext !== 'skill') {
    $ext = 'skill';
}
if ($ext === 'zip') {
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $filename = $stem . '.zip';
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache');
readfile($path);
exit;
