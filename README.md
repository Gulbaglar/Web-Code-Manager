# PHP Code Manager

**Write custom HTML, CSS and JavaScript from an admin panel — with draft → sandbox preview → publish, full version history, one-click rollback and an emergency kill switch. English and Turkish UI.**

🇹🇷 Türkçe: [README.tr.md](README.tr.md)

> *Restrict the damage, not the developer.* You write real code with a Monaco (VS Code) editor; Preview, Versions, Rollback and Safe Mode limit what a mistake can do.

A self-contained, framework-free PHP module. Your site needs **two lines** of PHP (one in `<head>`, one before `</body>`); everything else — code, versions, components — is managed in the admin UI and stored in the module's own SQLite file (it never touches your application's database).

## Features

- **Global Code** — Global CSS, Global JavaScript, Head Code, Body End Code.
- **Page Code** — HTML/CSS/JS per page. Loaded **only on that page**; in single-page apps it is removed again when you leave (no leaking).
- **Components** — reusable HTML/CSS/JS blocks. Use `[component:video-player]` in any page/section HTML; change the component once, every place updates.
- **Dynamic Sections** — insert code at named slots (before/after the hero, footer, …) that *you* define with CSS selectors. Drag to reorder.
- **Sandbox preview** — an isolated iframe with the *same runtime as production*, live console (`console.log`, errors) and an infinite-loop guard so a bad loop can't freeze the admin.
- **Draft → Preview → Publish** — publishing never overwrites: it creates a new version. **Restore** any old version (as a new one), **compare** (diff), delete old ones.
- **Emergency Safe Mode** — *Disable All Custom Code* button, and `?cm_safe=1` on any URL loads a page without custom code. Custom code can never run inside the admin.
- **Snippet library** — 7 starter snippets (modal, lightbox, scroll animation, before/after slider, …) + your own. **Import / Export** JSON.
- **Fast** — global CSS/JS are separate cacheable files (`immutable` cache keyed by revision); page bundles are inlined only for the page that uses them.
- **Two UI languages (English / Türkçe)** — switch at the top of every page; default in the config.

## Requirements

PHP 8.1+ with `pdo_sqlite`. No Composer packages. The Monaco editor is loaded from jsDelivr (a plain textarea fallback is used when offline).

## Quick start (demo)

```bash
git clone <this repo> php-code-manager && cd php-code-manager
php -S localhost:8080 -t public
```

- `http://localhost:8080/` and `/about.php` — a tiny demo site.
- `http://localhost:8080/admin/` — the Code Manager (first visit: create your password).

Try it: open **Global CSS**, write `header{outline:2px solid tomato}`, **Preview**, **Publish**, reload the demo site. Then create a **Dynamic Section** at *After the hero*.

## Add it to your site

1. Copy the module next to your site and expose **only `public/`** (or copy its files into a sub-folder — then edit the `require` in `public/admin/_boot.php` and the `urls` in the config).
2. Copy `config/config.example.php` → `config/config.php` and describe your site:
   - `routes` — your pages (`home`, `contact`, `page:about-us` …)
   - `slots` — where sections may be inserted: a CSS selector on your page + `beforebegin | afterbegin | beforeend | afterend`
   - `urls` — where `serve.php` and `assets/cc-runtime.js` are reachable from browsers
   - `preview` — your CSS files (inlined) and an HTML skeleton with the same selectors as your pages, so previews look like the real site
3. In every page template:

```php
<?php require '/path/to/php-code-manager/src/bootstrap.php'; ?>
<head> … <?php CodeManager\Frontend::head(); ?> </head>
<body> … <?php CodeManager\Frontend::footer('home'); ?> </body>   <!-- 'home' = this page's route key -->
```

### Single-page apps

Skip `footer()`'s auto-start: on every route change fetch `serve.php?route=…&sub=…&v=<rev>` and call
`CodeManagerRuntime.apply(bundle, { route, sub, lang, root: element, slots: CodeManagerConfig.slots })`
(`apply()` cleans up the previous route first). See the comments at the top of `public/assets/cc-runtime.js`.

## Writing custom JavaScript

Page/section/component JS runs in a function with a `ctx` helper (auto-cleaned when leaving the page):
`ctx.el` (the wrapper element), `ctx.root`, `ctx.route`, `ctx.qs()`, `ctx.qsa()`, `ctx.on(target, type, fn)`, `ctx.onLeave(fn)`, `ctx.setTimeout()`, `ctx.setInterval()`. Errors are caught and logged — they never break your site.

## Security

- The admin is protected by a password (`builtin`) or **your own login** (`auth.mode = 'callback'`); every API call needs the session + a CSRF header.
- Published code is data in the database served by `serve.php` (read-only, published + active only).
- Custom code runs only on your public pages and inside the sandboxed preview — never in the admin.
- Only administrators can publish code, and published code runs for **all visitors**: treat the admin password like a deploy key.
- Keep `storage/` (SQLite file + password hash) outside the web root or protected (`.htaccess` is created automatically on Apache).

## Languages

`lang/en.php` is the reference, `lang/tr.php` the Turkish translation. Route and slot labels in the config can be `['en' => …, 'tr' => …]`. To add a language copy `en.php`, translate it and add the code to `I18n::LANGS`. `php tools/check-i18n.php` verifies the files.

## Layout

```
src/     Manager (items, versions, bundles, preview), Frontend (two-line integration), Config, Auth, Db, I18n, Snippets
public/  serve.php (public read-only endpoint), assets/cc-runtime.js, admin/ (UI), index.php + about.php (demo)
lang/    en.php, tr.php       config/  config.example.php       tools/  i18n checker
```

## ☕ Support the Project

This project is free and open source.

If it saved you time, helped your project, or you simply want to support future development, you can buy me a coffee. ❤️

[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-Support-orange?style=for-the-badge&logo=buymeacoffee)](https://www.buymeacoffee.com/Gulbaglar)

Thank you for supporting open-source development!

## Author

**Kahraman Gülbağlar**  
https://www.gulbaglar.com

## License

MIT — see [LICENSE](LICENSE).
