<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$path = validate_file_param((string)($_GET['file'] ?? ''));
if (!$path) {
    header('Location: ./');
    exit;
}

$filename = basename($path);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache');
readfile($path);
exit;
