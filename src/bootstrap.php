<?php
declare(strict_types=1);

/**
 * Loads the module: autoloader, config, language. Include from any entry point:
 *     require '/path/to/php-code-manager/src/bootstrap.php';
 */
if (!defined('CODE_MANAGER_LOADED')) {
    define('CODE_MANAGER_LOADED', true);
    spl_autoload_register(function (string $class): void {
        $prefix = 'CodeManager\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $file = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) require_once $file;
    });
    $cfg = \CodeManager\Config::load();
    date_default_timezone_set((string) ($cfg['timezone'] ?? 'UTC'));
    \CodeManager\I18n::init((string) $cfg['language']);
}
