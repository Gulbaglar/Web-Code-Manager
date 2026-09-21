<?php
declare(strict_types=1);

namespace CodeManager;

/** Loads config/config.php (falls back to config.example.php). */
final class Config
{
    private static ?array $c = null;

    public static function moduleDir(): string { return dirname(__DIR__); }

    public static function load(?string $file = null): array
    {
        if (self::$c !== null && $file === null) return self::$c;
        $file = $file ?? (defined('CODE_MANAGER_CONFIG') ? CODE_MANAGER_CONFIG : (getenv('CODE_MANAGER_CONFIG') ?: ''));
        if ($file === '' || !is_file($file)) {
            $file = self::moduleDir() . '/config/config.php';
            if (!is_file($file)) $file = self::moduleDir() . '/config/config.example.php';
        }
        $c = require $file;
        if (!is_array($c)) throw new \RuntimeException('config must return an array: ' . $file);
        $c += [
            'language' => 'en', 'timezone' => 'UTC', 'environment' => 'prod',
            'storage' => self::moduleDir() . '/storage',
            'routes' => ['*' => 'All pages'],
            'slots' => [],
            'root_selector' => 'body',
            'urls' => ['serve' => '/serve.php', 'runtime' => '/assets/cc-runtime.js'],
            'safe_param' => 'cm_safe',
            'preview' => [],
            'auth' => ['mode' => 'builtin', 'username' => 'admin'],
        ];
        $c['preview'] += ['css_files' => [], 'skeleton_file' => null];
        $c['storage'] = rtrim(str_replace('\\', '/', (string) $c['storage']), '/');
        return self::$c = $c;
    }

    public static function get(string $key, $default = null) { return self::load()[$key] ?? $default; }
    public static function storage(): string { return (string) self::load()['storage']; }

    /** target key => label (current language). '*' (all pages) is always present. */
    public static function routes(): array
    {
        $r = [];
        foreach (self::load()['routes'] as $k => $label) $r[(string) $k] = I18n::pick($label);
        return ['*' => I18n::t('route.all')] + $r;
    }

    /** slot key => label. */
    public static function slotLabels(): array
    {
        $r = [];
        foreach (self::load()['slots'] as $k => $s) $r[(string) $k] = I18n::pick($s['label'] ?? $k);
        return $r;
    }

    /** slot key => [css selector, insert position] for the runtime. */
    public static function slotMap(): array
    {
        $r = [];
        foreach (self::load()['slots'] as $k => $s) $r[(string) $k] = [(string) $s['selector'], (string) ($s['position'] ?? 'beforeend')];
        return $r;
    }
}
