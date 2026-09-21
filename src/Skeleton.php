<?php
declare(strict_types=1);

namespace CodeManager;

/**
 * Default sample page used by the sandbox preview. It matches the slot selectors of config/config.example.php
 * (main, .hero, #features, #about, footer). For your own site, point config 'preview' => 'skeleton_file' at an HTML
 * file that contains the same selectors as your real pages, and list your CSS files in 'css_files'.
 */
final class Skeleton
{
    public static function html(): string
    {
        return <<<'HTML'
<header style="padding:14px 24px;border-bottom:1px solid #ddd;font:600 15px system-ui">Sample site · Home · About · Contact</header>
<main>
  <section class="hero" style="padding:64px 24px;background:#eef2ff;font-family:system-ui"><h1 style="margin:0 0 8px">Sample hero</h1><p style="margin:0;color:#555">Preview skeleton — replace it with your real page markup in the config.</p></section>
  <section id="features" style="padding:48px 24px;font-family:system-ui"><h2 style="margin:0 0 8px">Features</h2><p style="margin:0;color:#555">Sample section.</p></section>
  <section id="about" style="padding:48px 24px;background:#fafafa;font-family:system-ui"><h2 style="margin:0 0 8px">About</h2><p style="margin:0;color:#555">Sample section.</p></section>
</main>
<footer style="padding:24px;border-top:1px solid #ddd;font:14px system-ui;color:#666">© Sample footer</footer>
HTML;
    }
}
