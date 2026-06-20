<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = (int)((require __DIR__ . '/config/config.php')['session_lifetime'] ?? 2592000);
    session_set_cookie_params(['lifetime' => $lifetime, 'samesite' => 'Lax']);
    session_start();
}

/* ── LADDA CONFIG ───────────────────────────────────── */
function skill_config(): array {
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config/config.php';
        $c = file_exists($f) ? (array)(require $f) : [];
    }
    return $c;
}

function skill_users_file_path(): string {
    return __DIR__ . '/config/users.php';
}

/** @return array{users: array<string, array{password:string, role:string}>} */
function skill_users_defaults_from_legacy(): array {
    $cfg = skill_config();
    $pw  = (string)($cfg['password'] ?? 'admin123');
    if (str_starts_with($pw, '$2y$') || str_starts_with($pw, '$2b$')) {
        $hash = $pw;
    } else {
        $hash = password_hash($pw, PASSWORD_BCRYPT);
    }
    return [
        'users' => [
            'admin' => [
                'password' => $hash,
                'role'     => 'admin',
            ],
        ],
    ];
}

/** @return array{users: array<string, array{password:string, role:string}>} */
function skill_users_config(): array {
    static $cfg = null;
    static $mtime = null;
    $f = skill_users_file_path();
    if (!is_file($f)) {
        $cfg = skill_users_defaults_from_legacy();
        skill_write_php_config($f, $cfg);
        $mtime = (int)filemtime($f);
        return $cfg;
    }
    $fileMtime = (int)filemtime($f);
    if ($cfg === null || $mtime !== $fileMtime) {
        $loaded = (array)(require $f);
        $users  = [];
        foreach ((array)($loaded['users'] ?? []) as $name => $rec) {
            $norm = skill_normalize_username((string)$name);
            if ($norm === null || !is_array($rec)) {
                continue;
            }
            $role = skill_normalize_role((string)($rec['role'] ?? 'user'));
            $hash = (string)($rec['password'] ?? '');
            if ($hash === '') {
                continue;
            }
            $users[$norm] = ['password' => $hash, 'role' => $role];
        }
        if ($users === []) {
            $users = skill_users_defaults_from_legacy()['users'];
        }
        $cfg   = ['users' => $users];
        $mtime = $fileMtime;
    }
    return $cfg;
}

/** @return array<string, array{password:string, role:string}> */
function skill_users_list(): array {
    return skill_users_config()['users'];
}

function skill_normalize_username(string $username): ?string {
    $username = strtolower(trim($username));
    if ($username === '' || strlen($username) > 32) {
        return null;
    }
    if (!preg_match('/^[a-z][a-z0-9_\-]{2,31}$/', $username)) {
        return null;
    }
    return $username;
}

function skill_normalize_role(string $role): string {
    return strtolower(trim($role)) === 'admin' ? 'admin' : 'user';
}

/** @return array{username:string,password:string,role:string}|null */
function skill_user_record(string $username): ?array {
    $norm = skill_normalize_username($username);
    if ($norm === null) {
        return null;
    }
    $users = skill_users_list();
    if (!isset($users[$norm])) {
        foreach ($users as $name => $rec) {
            if (strcasecmp($name, $norm) === 0) {
                return ['username' => $name, 'password' => $rec['password'], 'role' => $rec['role']];
            }
        }
        return null;
    }
    $rec = $users[$norm];
    return ['username' => $norm, 'password' => $rec['password'], 'role' => $rec['role']];
}

function skill_hash_password(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

/** @param array<string, array{password:string, role:string}> $users */
function skill_save_users(array $users): bool {
    $clean = [];
    foreach ($users as $name => $rec) {
        $norm = skill_normalize_username((string)$name);
        if ($norm === null || !is_array($rec)) {
            continue;
        }
        $hash = (string)($rec['password'] ?? '');
        if ($hash === '') {
            continue;
        }
        $clean[$norm] = [
            'password' => $hash,
            'role'     => skill_normalize_role((string)($rec['role'] ?? 'user')),
        ];
    }
    if ($clean === []) {
        return false;
    }
    return skill_write_php_config(skill_users_file_path(), ['users' => $clean]);
}

function skill_count_admins(array $users): int {
    $n = 0;
    foreach ($users as $rec) {
        if (skill_normalize_role((string)($rec['role'] ?? '')) === 'admin') {
            $n++;
        }
    }
    return $n;
}

/* ── AUTH-KONTROLL ──────────────────────────────────── */
function skill_is_authed(): bool {
    $cfg = skill_config();
    $lifetime = (int)($cfg['session_lifetime'] ?? 28800);
    if (($_SESSION['skill_auth'] ?? false) !== true) {
        return false;
    }
    if ((time() - (int)($_SESSION['skill_auth_time'] ?? 0)) > $lifetime) {
        session_destroy();
        return false;
    }
    $user = (string)($_SESSION['skill_username'] ?? '');
    if ($user === '' || skill_user_record($user) === null) {
        return false;
    }
    return true;
}

function skill_current_username(): string {
    return skill_is_authed() ? (string)($_SESSION['skill_username'] ?? '') : '';
}

function skill_current_role(): string {
    if (!skill_is_authed()) {
        return '';
    }
    $rec = skill_user_record((string)($_SESSION['skill_username'] ?? ''));
    return $rec ? skill_normalize_role($rec['role']) : '';
}

function skill_is_admin(): bool {
    return skill_is_authed() && skill_current_role() === 'admin';
}

function skill_require_auth(string $login_path = 'login.php'): void {
    if (!skill_is_authed()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $login_path . '?back=' . $back);
        exit;
    }
}

function skill_require_admin(string $login_path = '../login.php'): void {
    skill_require_auth($login_path);
    if (!skill_is_admin()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo __('settings.admin_required');
        exit;
    }
}

function skill_try_login(string $username, string $password): bool {
    if ($password === '') {
        return false;
    }
    $rec = skill_user_record($username);
    if ($rec === null) {
        return false;
    }
    return password_verify($password, $rec['password']);
}

function skill_login(string $username): void {
    $rec = skill_user_record($username);
    if ($rec === null) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['skill_auth']      = true;
    $_SESSION['skill_auth_time'] = time();
    $_SESSION['skill_username']  = $rec['username'];
    $_SESSION['skill_role']      = $rec['role'];
}

function skill_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * @return null|string Felmeddelande eller null vid OK
 */
function skill_users_add(string $username, string $password, string $role): ?string {
    $norm = skill_normalize_username($username);
    if ($norm === null) {
        return __('settings.user_err_username');
    }
    if (strlen($password) < 6) {
        return __('settings.user_err_password_short');
    }
    $users = skill_users_list();
    if (isset($users[$norm])) {
        return __('settings.user_err_exists');
    }
    $users[$norm] = ['password' => skill_hash_password($password), 'role' => skill_normalize_role($role)];
    return skill_save_users($users) ? null : __('settings.msg_save_fail');
}

/**
 * @return null|string Felmeddelande eller null vid OK
 */
function skill_users_update(string $username, string $role, string $newPassword = ''): ?string {
    $norm = skill_normalize_username($username);
    if ($norm === null || !isset(skill_users_list()[$norm])) {
        return __('settings.user_err_not_found');
    }
    $users = skill_users_list();
    $oldRole = skill_normalize_role($users[$norm]['role']);
    $newRole = skill_normalize_role($role);
    if ($oldRole === 'admin' && $newRole !== 'admin' && skill_count_admins($users) <= 1) {
        return __('settings.user_err_last_admin');
    }
    if ($newPassword !== '' && strlen($newPassword) < 6) {
        return __('settings.user_err_password_short');
    }
    $users[$norm]['role'] = $newRole;
    if ($newPassword !== '') {
        $users[$norm]['password'] = skill_hash_password($newPassword);
    }
    return skill_save_users($users) ? null : __('settings.msg_save_fail');
}

/**
 * @return null|string Felmeddelande eller null vid OK
 */
function skill_users_delete(string $username): ?string {
    $norm = skill_normalize_username($username);
    if ($norm === null || !isset(skill_users_list()[$norm])) {
        return __('settings.user_err_not_found');
    }
    if (strcasecmp($norm, skill_current_username()) === 0) {
        return __('settings.user_err_delete_self');
    }
    $users = skill_users_list();
    if (skill_normalize_role($users[$norm]['role']) === 'admin' && skill_count_admins($users) <= 1) {
        return __('settings.user_err_last_admin');
    }
    unset($users[$norm]);
    return skill_save_users($users) ? null : __('settings.msg_save_fail');
}
