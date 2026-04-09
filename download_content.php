<?php
declare(strict_types=1);
/**
 * Laddar ner allt innehåll i /content som en .zip (kräver inloggning).
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_common.php';

skill_require_auth('login.php');

if (!class_exists('ZipArchive')) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=UTF-8');
    echo __('dlc.err_zip');
    exit;
}

$base = realpath(CONTENT_DIR);
if ($base === false || !is_dir($base)) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=UTF-8');
    echo __('dlc.err_no_content');
    exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'skcontent');
if ($tmp === false) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=UTF-8');
    echo __('dlc.err_tmp');
    exit;
}

$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=UTF-8');
    echo __('dlc.err_zip_create');
    exit;
}

$base = rtrim(str_replace('\\', '/', $base), '/');
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $full = $file->getRealPath();
    if ($full === false) {
        continue;
    }
    $fullNorm = str_replace('\\', '/', $full);
    if (str_starts_with($fullNorm, $base . '/')) {
        $rel = substr($fullNorm, strlen($base) + 1);
    } else {
        $rel = $file->getBasename();
    }
    $rel = str_replace('\\', '/', $rel);
    if ($rel === '') {
        continue;
    }
    $zip->addFile($full, $rel);
}

$zip->close();

$downloadName = 'skill-content-' . date('Y-m-d-His') . '.zip';
$size = filesize($tmp);
if ($size === false) {
    @unlink($tmp);
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=UTF-8');
    echo __('dlc.err_read');
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');

readfile($tmp);
@unlink($tmp);
exit;
