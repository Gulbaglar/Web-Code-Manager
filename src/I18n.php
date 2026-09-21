<?php
declare(strict_types=1);

namespace CodeManager;

/**
 * Minimal i18n: PHP arrays in lang/<code>.php. The UI language is chosen by whoever installs/uses the module:
 * language switcher at the top of every admin page (?lang=tr|en → cookie), otherwise config 'language', otherwise English.
 * To add a language: copy lang/en.php to lang/<code>.php, translate it, and add the code to I18n::LANGS.
 */
final class I18n
{
    public const LANGS = ['en' => 'English', 'tr' => 'Türkçe'];
    private static string $lang = 'en';
    private static array $dict = [];
    private static array $fallback = [];

    public static function init(string $default = 'en'): void
    {
        $lang = isset(self::LANGS[$default]) ? $default : 'en';
        if (PHP_SAPI !== 'cli') {
            $cookie = (string) ($_COOKIE['cm_lang'] ?? '');
            if (isset(self::LANGS[$cookie])) $lang = $cookie;
            $q = (string) ($_GET['lang'] ?? '');
            if (isset(self::LANGS[$q])) {
                $lang = $q;
                if (!headers_sent()) setcookie('cm_lang', $q, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
            }
        }
        self::set($lang);
    }

    public static function set(string $lang): void
    {
        if (!isset(self::LANGS[$lang])) $lang = 'en';
        self::$lang = $lang;
        self::$fallback = self::$fallback ?: (require dirname(__DIR__) . '/lang/en.php');
        self::$dict = $lang === 'en' ? self::$fallback : (array) (require dirname(__DIR__) . '/lang/' . $lang . '.php');
    }

    public static function lang(): string { return self::$lang; }

    /** {name} placeholders are replaced from $p. Missing keys fall back to English, then to the key itself. */
    public static function t(string $key, array $p = []): string
    {
        if (!self::$fallback) self::set(self::$lang);
        $s = self::$dict[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($p as $k => $v) $s = str_replace('{' . $k . '}', (string) $v, $s);
        return $s;
    }

    /** Picks the current language from a string or an ['en' => …, 'tr' => …] array (used for labels in config). */
    public static function pick($v): string
    {
        if (is_array($v)) return (string) ($v[self::$lang] ?? $v['en'] ?? reset($v) ?: '');
        return (string) $v;
    }

    /** Dictionary for the browser (only "ui." keys). */
    public static function forJs(): array
    {
        if (!self::$fallback) self::set(self::$lang);
        $out = [];
        foreach (self::$fallback as $k => $v) if (str_starts_with($k, 'ui.')) $out[$k] = self::$dict[$k] ?? $v;
        return $out;
    }
}
