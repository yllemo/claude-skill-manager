<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

/** Tillåtna typer för länkar / idéer. */
function skill_link_types(): array {
    return ['repo', 'collection', 'page', 'idea'];
}

/** Tillåtna statusar (särskilt för idéer / planerade skills). */
function skill_link_statuses(): array {
    return ['active', 'planned', 'done', 'archived'];
}

function skill_links_config_path(): string {
    return __DIR__ . '/config/links.php';
}

/**
 * @return array{items: list<array<string, mixed>>}
 */
function skill_links_defaults(): array {
    return ['items' => []];
}

/**
 * @return array{items: list<array<string, mixed>>}
 */
function skill_links_config(): array {
    static $cfg = null;
    static $mtime = null;
    $f = skill_links_config_path();
    $fileMtime = is_file($f) ? (int)filemtime($f) : 0;
    if ($cfg === null || $mtime !== $fileMtime) {
        $defaults = skill_links_defaults();
        $loaded = is_file($f) ? (array)(require $f) : [];
        $items = $loaded['items'] ?? $loaded;
        if (!is_array($items)) {
            $items = [];
        }
        $normalized = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = skill_link_normalize($row);
            if ($item !== null) {
                $normalized[] = $item;
            }
        }
        $cfg = ['items' => $normalized];
        $mtime = $fileMtime;
    }
    return $cfg;
}

/** @return list<array<string, mixed>> */
function skill_links_list(?string $filterType = null, ?string $filterStatus = null): array {
    $items = skill_links_config()['items'];
    if ($filterType !== null && $filterType !== '') {
        $items = array_values(array_filter(
            $items,
            static fn(array $i): bool => ($i['type'] ?? '') === $filterType
        ));
    }
    if ($filterStatus !== null && $filterStatus !== '') {
        $items = array_values(array_filter(
            $items,
            static fn(array $i): bool => ($i['status'] ?? '') === $filterStatus
        ));
    }
    usort($items, static function (array $a, array $b): int {
        $oa = (int)($a['sort'] ?? 0);
        $ob = (int)($b['sort'] ?? 0);
        if ($oa !== $ob) {
            return $oa <=> $ob;
        }
        return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });
    return $items;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>|null
 */
function skill_link_normalize(array $row): ?array {
    $title = trim((string)($row['title'] ?? ''));
    if ($title === '') {
        return null;
    }
    $type = strtolower(trim((string)($row['type'] ?? 'page')));
    if (!in_array($type, skill_link_types(), true)) {
        $type = 'page';
    }
    $status = strtolower(trim((string)($row['status'] ?? 'active')));
    if (!in_array($status, skill_link_statuses(), true)) {
        $status = 'active';
    }
    $url = trim((string)($row['url'] ?? ''));
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $id = trim((string)($row['id'] ?? ''));
    if ($id === '' || !preg_match('/^[a-zA-Z0-9_\-]{4,64}$/', $id)) {
        $id = 'lnk_' . bin2hex(random_bytes(6));
    }
    return [
        'id'         => $id,
        'title'      => mb_substr($title, 0, 200),
        'url'        => mb_substr($url, 0, 2000),
        'type'       => $type,
        'status'     => $status,
        'notes'      => mb_substr(trim((string)($row['notes'] ?? '')), 0, 4000),
        'tags'       => mb_substr(trim((string)($row['tags'] ?? '')), 0, 200),
        'sort'       => (int)($row['sort'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? date('c')),
        'updated_at' => (string)($row['updated_at'] ?? date('c')),
    ];
}

/** @param list<array<string, mixed>> $items */
function skill_links_save_all(array $items): bool {
    $normalized = [];
    foreach ($items as $row) {
        $item = skill_link_normalize($row);
        if ($item !== null) {
            $normalized[] = $item;
        }
    }
    return skill_write_php_config(skill_links_config_path(), ['items' => $normalized]);
}

/**
 * @param array<string, mixed> $input
 * @return string|null Felmeddelande eller null vid OK
 */
function skill_links_add(array $input): ?string {
    $item = skill_link_normalize([
        'id'         => '',
        'title'      => $input['title'] ?? '',
        'url'        => $input['url'] ?? '',
        'type'       => $input['type'] ?? 'idea',
        'status'     => $input['status'] ?? 'planned',
        'notes'      => $input['notes'] ?? '',
        'tags'       => $input['tags'] ?? '',
        'sort'       => $input['sort'] ?? 0,
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ]);
    if ($item === null) {
        return __('settings.links_err_title');
    }
    if (($item['type'] !== 'idea') && $item['url'] === '') {
        return __('settings.links_err_url');
    }
    $items = skill_links_list();
    $items[] = $item;
    if (!skill_links_save_all($items)) {
        return __('settings.msg_save_fail');
    }
    return null;
}

/**
 * @param array<string, mixed> $input
 * @return string|null
 */
function skill_links_update(array $input): ?string {
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') {
        return __('settings.links_err_not_found');
    }
    $items = skill_links_list();
    $found = false;
    foreach ($items as $i => $row) {
        if (($row['id'] ?? '') !== $id) {
            continue;
        }
        $merged = skill_link_normalize([
            'id'         => $id,
            'title'      => $input['title'] ?? $row['title'],
            'url'        => $input['url'] ?? $row['url'],
            'type'       => $input['type'] ?? $row['type'],
            'status'     => $input['status'] ?? $row['status'],
            'notes'      => $input['notes'] ?? $row['notes'],
            'tags'       => $input['tags'] ?? $row['tags'],
            'sort'       => $input['sort'] ?? $row['sort'],
            'created_at' => $row['created_at'] ?? date('c'),
            'updated_at' => date('c'),
        ]);
        if ($merged === null) {
            return __('settings.links_err_title');
        }
        if (($merged['type'] !== 'idea') && $merged['url'] === '') {
            return __('settings.links_err_url');
        }
        $items[$i] = $merged;
        $found = true;
        break;
    }
    if (!$found) {
        return __('settings.links_err_not_found');
    }
    if (!skill_links_save_all($items)) {
        return __('settings.msg_save_fail');
    }
    return null;
}

function skill_links_delete(string $id): ?string {
    $id = trim($id);
    $items = skill_links_list();
    $before = count($items);
    $items = array_values(array_filter(
        $items,
        static fn(array $row): bool => ($row['id'] ?? '') !== $id
    ));
    if (count($items) === $before) {
        return __('settings.links_err_not_found');
    }
    if (!skill_links_save_all($items)) {
        return __('settings.msg_save_fail');
    }
    return null;
}

function skill_link_type_label(string $type): string {
    $key = 'settings.links_type_' . $type;
    $label = __($key);
    return $label === $key ? $type : $label;
}

function skill_link_status_label(string $status): string {
    $key = 'settings.links_status_' . $status;
    $label = __($key);
    return $label === $key ? $status : $label;
}
