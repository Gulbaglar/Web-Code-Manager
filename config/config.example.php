<?php
/**
 * Code Manager configuration.
 *
 * Copy this file to config/config.php and edit it. (If config.php does not exist, this example is used — it matches
 * the demo site in public/index.php and public/about.php, so you can try everything immediately.)
 *
 * Labels ('label' / route names) can be plain strings or ['en' => 'Home', 'tr' => 'Ana sayfa'] arrays.
 */
return [
    // Default UI language: 'en' or 'tr'. Whoever uses the module can also switch it at the top of every admin page.
    'language'    => 'en',
    'timezone'    => 'UTC',           // e.g. 'Europe/Istanbul'
    'environment' => 'prod',          // 'dev' shows technical error details in the UI

    // Where the module keeps its own SQLite database and password. MUST NOT be web-accessible.
    'storage'     => __DIR__ . '/../storage',

    // The pages of YOUR site that can receive code. key => label. (The key is what you pass to Frontend::footer('home').)
    // The special key "*" (all pages) is added automatically. For a sub-page use "route:sub", e.g. 'page:about-us'.
    'routes'      => [
        'home'  => ['en' => 'Home',  'tr' => 'Ana sayfa'],
        'about' => ['en' => 'About', 'tr' => 'Hakkında'],
    ],

    // Places where a Dynamic Section can be inserted: a CSS selector on your page + where to insert relative to it:
    //   beforebegin | afterbegin | beforeend | afterend
    'slots'       => [
        'top'             => ['selector' => 'main',      'position' => 'afterbegin',  'label' => ['en' => 'Start of page content', 'tr' => 'Sayfa içeriğinin başı']],
        'bottom'          => ['selector' => 'main',      'position' => 'beforeend',   'label' => ['en' => 'End of page content',   'tr' => 'Sayfa içeriğinin sonu']],
        'hero-before'     => ['selector' => '.hero',     'position' => 'beforebegin', 'label' => ['en' => 'Before the hero',       'tr' => 'Hero öncesi']],
        'hero-after'      => ['selector' => '.hero',     'position' => 'afterend',    'label' => ['en' => 'After the hero',        'tr' => 'Hero sonrası']],
        'features-before' => ['selector' => '#features', 'position' => 'beforebegin', 'label' => ['en' => 'Before Features',      'tr' => 'Özellikler öncesi']],
        'features-after'  => ['selector' => '#features', 'position' => 'afterend',    'label' => ['en' => 'After Features',       'tr' => 'Özellikler sonrası']],
        'footer-before'   => ['selector' => 'footer',    'position' => 'beforebegin', 'label' => ['en' => 'Before the footer',     'tr' => 'Footer öncesi']],
        'footer-after'    => ['selector' => 'footer',    'position' => 'afterend',    'label' => ['en' => 'After the footer',      'tr' => 'Footer sonrası']],
    ],

    // Element that contains your page content (used for the "top"/"bottom" slots and as the ctx.root of custom JS).
    'root_selector' => 'main',

    // URLs (as seen by browsers) of this module's public files. With the demo layout (web root = this module's public/):
    'urls'        => ['serve' => '/serve.php', 'runtime' => '/assets/cc-runtime.js'],

    // Add ?cm_safe=1 to any page URL to load that page WITHOUT any custom code (emergency switch).
    'safe_param'  => 'cm_safe',

    // Sandbox preview: show your real look. CSS files (absolute paths) are inlined; the skeleton is an HTML file that
    // contains the same selectors as your real pages (see the slots above). null = generic sample page.
    'preview'     => [
        'css_files'     => [__DIR__ . '/../public/assets/demo.css'],
        'skeleton_file' => null,
    ],

    // Access control.
    //  'builtin'  : one admin account; you create the password the first time you open /admin/.
    //  'callback' : use YOUR application's login (see src/Auth.php for the closures to provide).
    'auth'        => ['mode' => 'builtin', 'username' => 'admin'],
];
