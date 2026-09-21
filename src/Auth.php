<?php
declare(strict_types=1);

namespace CodeManager;

/**
 * Access control for the admin UI. Two modes (config 'auth'):
 *  - builtin  (default): a single admin account. The first time you open the UI you create the password; it is stored
 *                        hashed in storage/auth.json. Login attempts are throttled.
 *  - callback: use YOUR app's login:
 *        'auth' => ['mode' => 'callback',
 *                   'is_admin'  => fn(): bool => ...,     // true only for administrators
 *                   'username'  => fn(): string => ...,
 *                   'login_url' => '/admin/login.php']
 * CSRF protection is always handled here (session token, sent as X-CSRF-Token).
 */
final class Auth
{
    public static function session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if ((self::cfg()['mode'] ?? 'builtin') === 'builtin') {
            session_name('cm_sid');
            session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        }
        session_start();
    }

    private static function cfg(): array { return Config::get('auth', ['mode' => 'builtin']); }
    private static function isCallback(): bool { return (self::cfg()['mode'] ?? 'builtin') === 'callback'; }
    private static function authFile(): string { return Config::storage() . '/auth.json'; }

    public static function needsSetup(): bool
    {
        return !self::isCallback() && empty(self::cfg()['password_hash']) && !is_file(self::authFile());
    }

    private static function hash(): string
    {
        $c = self::cfg();
        if (!empty($c['password_hash'])) return (string) $c['password_hash'];
        $j = is_file(self::authFile()) ? json_decode((string) file_get_contents(self::authFile()), true) : null;
        return is_array($j) ? (string) ($j['password_hash'] ?? '') : '';
    }

    public static function setPassword(string $pw): void
    {
        if (strlen($pw) < 8) throw new \InvalidArgumentException(I18n::t('err.password_short'));
        Db::ensureStorage();
        file_put_contents(self::authFile(), json_encode(['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'created' => time()]));
        @chmod(self::authFile(), 0600);
    }

    public static function check(): bool
    {
        self::session();
        if (self::isCallback()) { $f = self::cfg()['is_admin'] ?? null; return is_callable($f) && (bool) $f(); }
        return !empty($_SESSION['cm_admin']);
    }

    public static function user(): string
    {
        if (self::isCallback()) { $f = self::cfg()['username'] ?? null; return is_callable($f) ? (string) $f() : 'admin'; }
        return (string) (self::cfg()['username'] ?? 'admin');
    }

    public static function require(): void
    {
        if (self::check()) return;
        $url = self::isCallback() ? (string) (self::cfg()['login_url'] ?? '/') : 'login.php';
        header('Location: ' . $url, true, 302);
        exit;
    }

    public static function login(string $pw): bool
    {
        self::session();
        $f = Config::storage() . '/login-attempts.json';
        $a = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $a = array_values(array_filter($a, fn($x) => $x['t'] > time() - 900));
        if (count(array_filter($a, fn($x) => $x['ip'] === $ip)) >= 5) throw new \RuntimeException(I18n::t('err.too_many_attempts'));
        $h = self::hash();
        if ($h !== '' && password_verify($pw, $h)) {
            session_regenerate_id(true);
            $_SESSION['cm_admin'] = true;
            @unlink($f);
            return true;
        }
        $a[] = ['ip' => $ip, 't' => time()];
        Db::ensureStorage(); file_put_contents($f, json_encode($a));
        return false;
    }

    public static function logout(): void { self::session(); $_SESSION = []; session_destroy(); }

    public static function csrf(): string
    {
        self::session();
        if (empty($_SESSION['cm_csrf'])) $_SESSION['cm_csrf'] = bin2hex(random_bytes(32));
        return (string) $_SESSION['cm_csrf'];
    }
    public static function csrfOk(string $sent): bool { self::session(); return $sent !== '' && hash_equals((string) ($_SESSION['cm_csrf'] ?? ''), $sent); }
}
