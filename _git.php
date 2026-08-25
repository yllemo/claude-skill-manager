<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/lib/GitRepoClient.php';

/** @return array<string, mixed> */
function skill_git_defaults(): array {
    return [
        'enabled'          => false,
        'provider'         => 'github', // github | gitlab
        'token'            => '',
        'owner'            => '',
        'repo'             => '',
        'branch'           => 'main',
        'gitlab_url'       => 'https://gitlab.com',
        'path_prefix'      => 'content',
        'commit_message'   => 'Sync skills from Skill Manager',
        'delete_missing'   => false,
        'last_sync_at'     => '',
        'last_sync_status' => '',
        'last_sync_summary'=> '',
    ];
}

function skill_git_config_path(): string {
    return __DIR__ . '/config/git.php';
}

/** @return array<string, mixed> */
function skill_git_config(): array {
    static $cfg = null;
    static $mtime = null;
    $f = skill_git_config_path();
    $fileMtime = is_file($f) ? (int)filemtime($f) : 0;
    if ($cfg === null || $mtime !== $fileMtime) {
        $defaults = skill_git_defaults();
        $loaded = is_file($f) ? (array)(require $f) : [];
        $cfg = array_replace($defaults, $loaded);
        $cfg['enabled'] = !empty($cfg['enabled']);
        $cfg['provider'] = strtolower(trim((string)($cfg['provider'] ?? 'github'))) === 'gitlab' ? 'gitlab' : 'github';
        $cfg['token'] = trim((string)($cfg['token'] ?? ''));
        $cfg['owner'] = trim((string)($cfg['owner'] ?? ''));
        $cfg['repo'] = trim((string)($cfg['repo'] ?? ''));
        $cfg['branch'] = trim((string)($cfg['branch'] ?? 'main')) ?: 'main';
        $cfg['gitlab_url'] = rtrim(trim((string)($cfg['gitlab_url'] ?? 'https://gitlab.com')), '/') ?: 'https://gitlab.com';
        $cfg['path_prefix'] = trim((string)($cfg['path_prefix'] ?? 'content'), "/ \t");
        $cfg['commit_message'] = trim((string)($cfg['commit_message'] ?? '')) ?: 'Sync skills from Skill Manager';
        $cfg['delete_missing'] = !empty($cfg['delete_missing']);
        $cfg['last_sync_at'] = (string)($cfg['last_sync_at'] ?? '');
        $cfg['last_sync_status'] = (string)($cfg['last_sync_status'] ?? '');
        $cfg['last_sync_summary'] = (string)($cfg['last_sync_summary'] ?? '');
        $mtime = $fileMtime;
    }
    return $cfg;
}

function skill_git_is_configured(): bool {
    $c = skill_git_config();
    return $c['token'] !== '' && $c['owner'] !== '' && $c['repo'] !== '';
}

/**
 * @param array<string, mixed> $input
 * @param array<string, mixed>|null $existing
 */
function skill_git_save_config(array $input, ?array $existing = null): bool {
    $existing ??= skill_git_config();
    $token = trim((string)($input['token'] ?? ''));
    if ($token === '') {
        $token = (string)($existing['token'] ?? '');
    }

    $data = [
        'enabled'          => !empty($input['enabled']),
        'provider'         => strtolower(trim((string)($input['provider'] ?? 'github'))) === 'gitlab' ? 'gitlab' : 'github',
        'token'            => $token,
        'owner'            => trim((string)($input['owner'] ?? '')),
        'repo'             => trim((string)($input['repo'] ?? '')),
        'branch'           => trim((string)($input['branch'] ?? 'main')) ?: 'main',
        'gitlab_url'       => rtrim(trim((string)($input['gitlab_url'] ?? 'https://gitlab.com')), '/') ?: 'https://gitlab.com',
        'path_prefix'      => trim((string)($input['path_prefix'] ?? 'content'), "/ \t"),
        'commit_message'   => trim((string)($input['commit_message'] ?? '')) ?: 'Sync skills from Skill Manager',
        'delete_missing'   => !empty($input['delete_missing']),
        'last_sync_at'     => (string)($existing['last_sync_at'] ?? ''),
        'last_sync_status' => (string)($existing['last_sync_status'] ?? ''),
        'last_sync_summary'=> (string)($existing['last_sync_summary'] ?? ''),
    ];

    return skill_write_php_config(skill_git_config_path(), $data);
}

/**
 * Uppdaterar endast sync-metadata utan att röra övriga fält.
 */
function skill_git_update_sync_meta(string $status, string $summary): bool {
    $data = skill_git_config();
    $data['last_sync_at'] = date('c');
    $data['last_sync_status'] = $status;
    $data['last_sync_summary'] = $summary;
    return skill_write_php_config(skill_git_config_path(), $data);
}

function skill_git_client(?array $cfg = null): GitRepoClient {
    $cfg ??= skill_git_config();
    if (trim((string)$cfg['token']) === '' || trim((string)$cfg['owner']) === '' || trim((string)$cfg['repo']) === '') {
        throw new RuntimeException(__('settings.git_err_incomplete'));
    }
    return new GitRepoClient(
        (string)$cfg['provider'],
        (string)$cfg['token'],
        (string)$cfg['owner'],
        (string)$cfg['repo'],
        (string)$cfg['branch'],
        (string)$cfg['gitlab_url']
    );
}

/** Relativ sökväg i git-repot för en lokal .skill-fil. */
function skill_git_remote_path(string $basename, ?array $cfg = null): string {
    $cfg ??= skill_git_config();
    $base = basename($basename);
    $prefix = trim((string)($cfg['path_prefix'] ?? ''), "/ \t");
    return $prefix === '' ? $base : $prefix . '/' . $base;
}

/**
 * Testar anslutning mot GitHub/GitLab.
 *
 * @return array{ok:bool, message:string, info?:array<string,mixed>}
 */
function skill_git_test_connection(?array $cfg = null): array {
    try {
        $client = skill_git_client($cfg);
        $info = $client->getRepoInfo();
        $name = (string)($info['full_name'] ?? $info['path_with_namespace'] ?? ($cfg['owner'] ?? '') . '/' . ($cfg['repo'] ?? ''));
        return [
            'ok'      => true,
            'message' => __('settings.git_test_ok', ['repo' => $name, 'branch' => $client->getBranch()]),
            'info'    => $info,
        ];
    } catch (Throwable $e) {
        return [
            'ok'      => false,
            'message' => __('settings.git_test_fail', ['error' => $e->getMessage()]),
        ];
    }
}

/**
 * Synkar alla .skill under /content till git-repot.
 *
 * @return array{ok:bool, created:int, updated:int, skipped:int, deleted:int, errors:list<string>, log:list<string>}
 */
function skill_git_sync_content(?array $cfg = null): array {
    $cfg = $cfg ?? skill_git_config();
    $result = [
        'ok'      => false,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'deleted' => 0,
        'errors'  => [],
        'log'     => [],
    ];

    if (empty($cfg['enabled'])) {
        $result['errors'][] = __('settings.git_err_disabled');
        return $result;
    }

    try {
        $client = skill_git_client($cfg);
    } catch (Throwable $e) {
        $result['errors'][] = $e->getMessage();
        return $result;
    }

    $prefix = trim((string)$cfg['path_prefix'], "/ \t");
    $msgTpl = (string)$cfg['commit_message'];

    $localFiles = glob(CONTENT_DIR . '*.skill') ?: [];
    $localNames = [];
    foreach ($localFiles as $abs) {
        $name = basename($abs);
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.skill$/', $name)) {
            continue;
        }
        $localNames[$name] = true;
        $remotePath = skill_git_remote_path($name, $cfg);
        $binary = (string)file_get_contents($abs);
        if ($binary === '') {
            $result['errors'][] = __('settings.git_err_empty', ['file' => $name]);
            continue;
        }

        $sha = null;
        $exists = false;
        try {
            $remote = $client->getFile($remotePath);
            $sha = (string)($remote['sha'] ?? '');
            $exists = true;
            if (hash('sha256', $binary) === hash('sha256', (string)($remote['content'] ?? ''))) {
                $result['skipped']++;
                $result['log'][] = __('settings.git_log_skip', ['file' => $remotePath]);
                continue;
            }
        } catch (Throwable) {
            $exists = false;
            $sha = null;
        }

        $commitMsg = $msgTpl . ' — ' . ($exists ? 'update' : 'add') . ' ' . $name;
        try {
            $client->putFile($remotePath, $binary, $commitMsg, $sha, true);
            if ($exists) {
                $result['updated']++;
                $result['log'][] = __('settings.git_log_update', ['file' => $remotePath]);
            } else {
                $result['created']++;
                $result['log'][] = __('settings.git_log_create', ['file' => $remotePath]);
            }
        } catch (Throwable $e) {
            $result['errors'][] = $name . ': ' . $e->getMessage();
        }
    }

    if (!empty($cfg['delete_missing'])) {
        try {
            $remoteList = $client->listFiles($prefix);
            foreach ($remoteList as $item) {
                if (($item['type'] ?? '') !== 'file') {
                    continue;
                }
                $rname = basename((string)$item['name']);
                if (!preg_match('/\.skill$/i', $rname)) {
                    continue;
                }
                if (isset($localNames[$rname])) {
                    continue;
                }
                $rpath = (string)$item['path'];
                try {
                    $sha = (string)($item['sha'] ?? '');
                    if ($sha === '' && $client->getProvider() === 'github') {
                        $info = $client->getFile($rpath);
                        $sha = (string)($info['sha'] ?? '');
                    }
                    $client->deleteFile($rpath, $msgTpl . ' — delete ' . $rname, $sha);
                    $result['deleted']++;
                    $result['log'][] = __('settings.git_log_delete', ['file' => $rpath]);
                } catch (Throwable $e) {
                    $result['errors'][] = $rname . ': ' . $e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = __('settings.git_err_list_remote', ['error' => $e->getMessage()]);
        }
    }

    $result['ok'] = $result['errors'] === [];
    $summary = __('settings.git_sync_summary', [
        'created' => (string)$result['created'],
        'updated' => (string)$result['updated'],
        'skipped' => (string)$result['skipped'],
        'deleted' => (string)$result['deleted'],
        'errors'  => (string)count($result['errors']),
    ]);
    skill_git_update_sync_meta($result['ok'] ? 'ok' : 'error', $summary);
    $result['log'][] = $summary;

    return $result;
}
