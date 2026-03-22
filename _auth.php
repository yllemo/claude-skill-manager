<?php
declare(strict_types=1);

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

/* ── AUTH-KONTROLL ──────────────────────────────────── */
function skill_is_authed(): bool {
    $cfg = skill_config();
    $lifetime = (int)($cfg['session_lifetime'] ?? 28800);
    if (($_SESSION['skill_auth'] ?? false) !== true) return false;
    if ((time() - (int)($_SESSION['skill_auth_time'] ?? 0)) > $lifetime) {
        session_destroy();
        return false;
    }
    return true;
}

/**
 * Kräver inloggning — omdirigerar annars till login-sidan.
 * $login_path är relativ sökväg till login.php från anropande fil.
 */
function skill_require_auth(string $login_path = 'login.php'): void {
    if (!skill_is_authed()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $login_path . '?back=' . $back);
        exit;
    }
}

/* ── LÖSENORDSKONTROLL ──────────────────────────────── */
function skill_try_login(string $password): bool {
    $cfg    = skill_config();
    $stored = (string)($cfg['password'] ?? '');
    if ($stored === '' || $password === '') return false;

    // Stöd bcrypt-hash ($2y$...) eller klartext
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2b$')) {
        return password_verify($password, $stored);
    }
    return hash_equals($stored, $password);
}

/* ── LOGGA IN ───────────────────────────────────────── */
function skill_login(): void {
    session_regenerate_id(true);
    $_SESSION['skill_auth']      = true;
    $_SESSION['skill_auth_time'] = time();
}

/* ── LOGGA UT ───────────────────────────────────────── */
function skill_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
