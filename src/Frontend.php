<?php
declare(strict_types=1);

namespace CodeManager;

/**
 * Host-site integration. Call these from your page template(s) — that is all your site needs:
 *
 *     <head> ... <?php CodeManager\Frontend::head(); ?> </head>
 *     <body> ... <?php CodeManager\Frontend::footer('home'); ?> </body>      // 'home' = the route key of THIS page
 *
 * Route keys are the ones you list in config 'routes' ('*' means "all pages"). For sub-pages pass a second argument,
 * e.g. footer('page', 'about-us') so items targeting "page:about-us" apply too.
 *
 * Safe mode: add ?cm_safe=1 to any URL (param name: config 'safe_param') and NO custom code is output for that request.
 * The admin can also switch everything off with one button ("Disable All Custom Code").
 */
final class Frontend
{
    private static ?array $state = null;

    private static function state(): array
    {
        if (self::$state !== null) return self::$state;
        $p = (string) Config::get('safe_param', 'cm_safe');
        if ($p !== '' && isset($_GET[$p]) && $_GET[$p] !== '0') return self::$state = ['on' => false, 'rev' => 0, 'css' => false, 'js' => false, 'head' => '', 'body' => '', 'bundles' => false];
        return self::$state = Manager::publicState();
    }

    private static function url(string $qs): string
    {
        $u = (string) Config::get('urls')['serve'];
        return htmlspecialchars($u . (str_contains($u, '?') ? '&' : '?') . $qs, ENT_QUOTES);
    }

    /** Inside <head>: global CSS link + Head Code. */
    public static function head(): void
    {
        $s = self::state();
        if (!$s['on']) return;
        if ($s['css']) echo '<link rel="stylesheet" href="' . self::url('g=css&v=' . $s['rev']) . '">' . "\n";
        echo $s['head'], "\n";
    }

    /** Before </body>: runtime + this page's bundle (inline) + global JS + Body End Code. */
    public static function footer(string $route = '', string $sub = ''): void
    {
        $s = self::state();
        if (!$s['on']) return;
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
        if ($s['bundles']) {
            try { $bundle = Manager::buildBundle(Manager::liveItems(), $route, $sub); } catch (\Throwable $e) { $bundle = null; }
            $cfg = ['slots' => Config::slotMap(), 'root' => Config::get('root_selector', 'body'), 'route' => $route, 'sub' => $sub, 'lang' => I18n::lang(),
                    'serve' => Config::get('urls')['serve'], 'rev' => $s['rev'], 'bundle' => $bundle];
            echo '<script>window.CodeManagerConfig=' . json_encode($cfg, $flags) . ';</script>' . "\n";
            echo '<script src="' . htmlspecialchars((string) Config::get('urls')['runtime'], ENT_QUOTES) . '?v=' . (int) @filemtime(dirname(__DIR__) . '/public/assets/cc-runtime.js') . '"></script>' . "\n";
        }
        if ($s['js']) echo '<script src="' . self::url('g=js&v=' . $s['rev']) . '"></script>' . "\n";
        echo $s['body'], "\n";
    }
}
