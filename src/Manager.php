<?php
declare(strict_types=1);

namespace CodeManager;

use PDO;
use InvalidArgumentException;

/**
 * Code Manager core: custom HTML/CSS/JS kept in the database — draft → preview (sandbox) → publish, with full version
 * history and rollback. Published code is what visitors get; drafts never leave the admin.
 *
 * Kinds: global (css / js / head / body-end) · page (per route) · component (reusable) · section (placed in a slot).
 */
final class Manager
{
    /** Fixed global entries: slug => field that holds the code */
    public const GLOBALS = ['global-css' => 'css', 'global-js' => 'js', 'head' => 'html', 'body-end' => 'html'];

    public static function t(string $k, array $p = []): string { return I18n::t($k, $p); }
    private static function db(): PDO { return Db::pdo(); }

    /* ------------------------------------------------------------ meta / switches */

    private static function meta(string $k): string
    {
        try { $st = self::db()->prepare('SELECT value FROM cm_meta WHERE key = ?'); $st->execute([$k]); return (string) ($st->fetchColumn() ?: ''); }
        catch (\Throwable $e) { return ''; }
    }
    private static function setMeta(string $k, string $v): void
    {
        self::db()->prepare('INSERT INTO cm_meta(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$k, $v]);
    }
    public static function disabled(): bool { return self::meta('disabled') === '1'; }
    public static function rev(): int { return (int) self::meta('rev'); }
    public static function bumpRev(): void { self::setMeta('rev', (string) (self::rev() + 1)); }
    public static function setDisabled(bool $on): void { self::setMeta('disabled', $on ? '1' : '0'); self::bumpRev(); }

    public static function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ş' => 's', 'é' => 'e', 'è' => 'e', 'à' => 'a']);
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    }

    /** First use: the 4 fixed global entries and the starter snippets (named in the current UI language). */
    public static function ensureDefaults(): void
    {
        $now = time();
        $ins = self::db()->prepare("INSERT OR IGNORE INTO cm_items(kind,slug,name,target,created_at,updated_at) VALUES('global',?,?, '*',?,?)");
        foreach (array_keys(self::GLOBALS) as $slug) $ins->execute([$slug, self::t('global.' . $slug), $now, $now]);
        if ((int) self::db()->query('SELECT COUNT(*) FROM cm_snippets')->fetchColumn() === 0) {
            $s = self::db()->prepare('INSERT INTO cm_snippets(name,category,description,html,css,js,created_at) VALUES(?,?,?,?,?,?,?)');
            foreach (Snippets::all() as $x) {
                $s->execute([self::t('snip.' . $x['key'] . '.name'), self::t('snip.cat.' . $x['cat']), self::t('snip.' . $x['key'] . '.desc'), $x['html'] ?? '', $x['css'] ?? '', $x['js'] ?? '', $now]);
            }
        }
    }

    /* ------------------------------------------------------------ items */

    public static function item(int $id): ?array
    {
        $st = self::db()->prepare('SELECT * FROM cm_items WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function listItems(): array
    {
        $rows = self::db()->query(
            'SELECT i.id,i.kind,i.slug,i.name,i.description,i.target,i.position,i.sort,i.active,i.published_version,i.updated_at,i.published_at,
                    (v.html = i.html AND v.css = i.css AND v.js = i.js AND v.target = i.target AND v.position = i.position) AS same
               FROM cm_items i LEFT JOIN cm_versions v ON v.item_id = i.id AND v.version = i.published_version
           ORDER BY i.kind, i.sort, i.id'
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id']; $r['active'] = (int) $r['active']; $r['published_version'] = (int) $r['published_version'];
            $r['status'] = $r['published_version'] === 0 ? 'draft' : ((int) $r['same'] === 1 ? 'published' : 'changed');
            unset($r['same']);
        }
        return $rows;
    }

    public static function create(string $kind, string $name, string $target = '*', string $position = ''): int
    {
        if (!in_array($kind, ['page', 'component', 'section'], true)) throw new InvalidArgumentException(self::t('err.invalid_kind'));
        $now = time(); $routes = Config::routes(); $slots = Config::slotLabels();
        if ($kind === 'page') {
            if (!array_key_exists($target, $routes) || $target === '*') throw new InvalidArgumentException(self::t('err.invalid_target'));
            $slug = $target;
            $name = $name !== '' ? $name : $routes[$target];
        } else {
            $name = trim($name);
            if ($name === '') throw new InvalidArgumentException(self::t('err.name_required'));
            $slug = self::uniqueSlug($kind, self::slugify($name));
        }
        if ($kind === 'section' && !isset($slots[$position])) $position = (string) (array_key_first($slots) ?? 'bottom');
        if ($kind === 'section' && !array_key_exists($target, $routes)) $target = '*';
        $sort = (int) self::db()->query('SELECT COALESCE(MAX(sort),0)+10 FROM cm_items WHERE kind = ' . self::db()->quote($kind))->fetchColumn();
        self::db()->prepare('INSERT INTO cm_items(kind,slug,name,target,position,sort,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$kind, $slug, $name, $kind === 'component' ? '*' : $target, $kind === 'section' ? $position : '', $sort, $now, $now]);
        return (int) self::db()->lastInsertId();
    }

    private static function uniqueSlug(string $kind, string $base): string
    {
        $base = $base !== '' ? substr($base, 0, 48) : 'item';
        $slug = $base; $n = 2;
        $st = self::db()->prepare('SELECT 1 FROM cm_items WHERE kind = ? AND slug = ?');
        while (true) { $st->execute([$kind, $slug]); if (!$st->fetchColumn()) return $slug; $slug = $base . '-' . $n++; }
    }

    /** Saves the DRAFT. $f: name, description, target, position, sort, html, css, js, slug (components only) */
    public static function saveDraft(int $id, array $f): void
    {
        $it = self::item($id);
        if (!$it) throw new InvalidArgumentException(self::t('err.not_found'));
        $set = []; $val = [];
        foreach (['html', 'css', 'js', 'name', 'description'] as $k) if (isset($f[$k]) && is_string($f[$k])) { $set[] = "$k = ?"; $val[] = $f[$k]; }
        if ($it['kind'] === 'component' && isset($f['slug']) && is_string($f['slug'])) {
            $slug = self::slugify($f['slug']);
            if ($slug !== '' && $slug !== $it['slug']) {
                $st = self::db()->prepare('SELECT 1 FROM cm_items WHERE kind = ? AND slug = ? AND id <> ?');
                $st->execute([$it['kind'], $slug, $id]);
                if ($st->fetchColumn()) throw new InvalidArgumentException(self::t('err.slug_taken'));
                $set[] = 'slug = ?'; $val[] = substr($slug, 0, 48);
            }
        }
        if ($it['kind'] === 'section') {
            if (isset($f['target']) && array_key_exists((string) $f['target'], Config::routes())) { $set[] = 'target = ?'; $val[] = (string) $f['target']; }
            if (isset($f['position']) && isset(Config::slotLabels()[(string) $f['position']])) { $set[] = 'position = ?'; $val[] = (string) $f['position']; }
        }
        if (isset($f['sort'])) { $set[] = 'sort = ?'; $val[] = (int) $f['sort']; }
        if (!$set) return;
        $set[] = 'updated_at = ?'; $val[] = time(); $val[] = $id;
        self::db()->prepare('UPDATE cm_items SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($val);
    }

    public static function setActive(int $id, bool $on): void
    {
        self::db()->prepare('UPDATE cm_items SET active = ? WHERE id = ?')->execute([$on ? 1 : 0, $id]);
        self::bumpRev();
    }

    public static function delete(int $id): void
    {
        $it = self::item($id);
        if (!$it) return;
        if ($it['kind'] === 'global') throw new InvalidArgumentException(self::t('err.global_undeletable'));
        self::db()->prepare('DELETE FROM cm_versions WHERE item_id = ?')->execute([$id]);
        self::db()->prepare('DELETE FROM cm_items WHERE id = ?')->execute([$id]);
        self::bumpRev();
    }

    public static function reorder(array $ids): void
    {
        $st = self::db()->prepare("UPDATE cm_items SET sort = ? WHERE id = ? AND kind = 'section'");
        $n = 10;
        foreach ($ids as $id) { $st->execute([$n, (int) $id]); $n += 10; }
        self::bumpRev();
    }

    /* ------------------------------------------------------------ versions */

    public static function versions(int $id): array
    {
        $st = self::db()->prepare('SELECT id,version,status,note,target,position,created_at,published_at, LENGTH(html)+LENGTH(css)+LENGTH(js) AS size FROM cm_versions WHERE item_id = ? ORDER BY version DESC');
        $st->execute([$id]);
        return $st->fetchAll();
    }

    public static function version(int $id, int $ver): ?array
    {
        $st = self::db()->prepare('SELECT * FROM cm_versions WHERE item_id = ? AND version = ?');
        $st->execute([$id, $ver]);
        return $st->fetch() ?: null;
    }

    /** Publishing NEVER overwrites: it creates a new version and archives the previous one. */
    public static function publish(int $id, string $note = ''): int
    {
        $it = self::item($id);
        if (!$it) throw new InvalidArgumentException(self::t('err.not_found'));
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT COALESCE(MAX(version),0) FROM cm_versions WHERE item_id = ?');
            $st->execute([$id]);
            $next = (int) $st->fetchColumn() + 1; $now = time();
            $pdo->prepare("UPDATE cm_versions SET status = 'archived' WHERE item_id = ? AND status = 'published'")->execute([$id]);
            $pdo->prepare("INSERT INTO cm_versions(item_id,version,html,css,js,target,position,status,note,created_at,published_at) VALUES(?,?,?,?,?,?,?,'published',?,?,?)")
                ->execute([$id, $next, $it['html'], $it['css'], $it['js'], $it['target'], $it['position'], mb_substr($note, 0, 200), $now, $now]);
            $pdo->prepare('UPDATE cm_items SET published_version = ?, published_at = ?, updated_at = ? WHERE id = ?')->execute([$next, $now, $now, $id]);
            $pdo->commit();
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
        self::bumpRev();
        return $next;
    }

    /** Publishes an old version as a NEW version (history is kept) and aligns the draft with it. */
    public static function restore(int $id, int $ver): int
    {
        $v = self::version($id, $ver);
        if (!$v) throw new InvalidArgumentException(self::t('err.version_not_found'));
        self::db()->prepare('UPDATE cm_items SET html=?, css=?, js=?, target=?, position=?, updated_at=? WHERE id=?')->execute([$v['html'], $v['css'], $v['js'], $v['target'], $v['position'], time(), $id]);
        return self::publish($id, self::t('note.restored', ['v' => $ver]));
    }

    public static function deleteVersion(int $id, int $ver): void
    {
        $it = self::item($id);
        if (!$it) return;
        if ((int) $it['published_version'] === $ver) throw new InvalidArgumentException(self::t('err.active_version'));
        self::db()->prepare('DELETE FROM cm_versions WHERE item_id = ? AND version = ?')->execute([$id, $ver]);
    }

    /* ------------------------------------------------------------ live data */

    /** What visitors get: the published version's content + the item's current meta. */
    public static function liveItems(): array
    {
        $rows = self::db()->query(
            "SELECT i.id,i.kind,i.slug,i.name,i.sort,v.html,v.css,v.js,v.target,v.position
               FROM cm_items i JOIN cm_versions v ON v.item_id = i.id AND v.version = i.published_version
              WHERE i.active = 1 AND i.published_version > 0 ORDER BY i.sort, i.id"
        )->fetchAll();
        foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['sort'] = (int) $r['sort']; }
        return $rows;
    }

    public static function globalCode(string $slug, ?array $items = null): string
    {
        foreach ($items ?? self::liveItems() as $it) {
            if ($it['kind'] === 'global' && $it['slug'] === $slug) return (string) $it[self::GLOBALS[$slug] ?? 'html'];
        }
        return '';
    }

    /** [component:slug] / {{component:slug}} → the component's HTML (recursive up to depth 4). */
    private static function resolve(string $html, array $comps, array &$used, int $depth = 0): string
    {
        if ($depth > 4 || $html === '') return $html;
        return (string) preg_replace_callback('/(?:\[component:([a-z0-9-]+)\]|\{\{\s*component:([a-z0-9-]+)\s*\}\})/i', function ($m) use ($comps, &$used, $depth) {
            $slug = strtolower($m[1] !== '' ? $m[1] : $m[2]);
            if (!isset($comps[$slug])) return '<!-- component "' . $slug . '" not found or disabled -->';
            $used[$slug] = true;
            return '<div class="cm-comp" data-cm-component="' . $slug . '">' . self::resolve($comps[$slug]['html'], $comps, $used, $depth + 1) . '</div>';
        }, $html);
    }

    private static function matches(string $t, string $route, string $sub): bool
    {
        return $t === '*' || $t === $route || ($sub !== '' && $t === $route . ':' . $sub);
    }

    /** The package for one route. Only pieces targeting that route are included (nothing leaks to other pages). */
    public static function buildBundle(array $items, string $route, string $sub = ''): array
    {
        $comps = [];
        foreach ($items as $it) if ($it['kind'] === 'component') $comps[$it['slug']] = $it;
        $used = []; $pages = []; $sections = [];
        foreach ($items as $it) {
            if ($it['kind'] === 'page' && $it['target'] !== '*' && self::matches($it['target'], $route, $sub)) {
                $pages[] = ['id' => $it['id'], 'html' => self::resolve($it['html'], $comps, $used), 'css' => $it['css'], 'js' => $it['js'], 'spec' => $it['target'] === $route ? 0 : 1];
            } elseif ($it['kind'] === 'section' && self::matches($it['target'], $route, $sub)) {
                $sections[] = ['id' => $it['id'], 'pos' => $it['position'], 'sort' => $it['sort'], 'html' => self::resolve($it['html'], $comps, $used), 'css' => $it['css'], 'js' => $it['js']];
            }
        }
        usort($pages, fn($a, $b) => $a['spec'] <=> $b['spec']);
        usort($sections, fn($a, $b) => [$a['sort'], $a['id']] <=> [$b['sort'], $b['id']]);
        $out = [];
        foreach (array_keys($used) as $slug) $out[] = ['slug' => $slug, 'css' => $comps[$slug]['css'], 'js' => $comps[$slug]['js']];
        return ['route' => $route, 'sub' => $sub, 'pages' => $pages, 'sections' => $sections, 'components' => $out];
    }

    /** Server state for the front-end helper. */
    public static function publicState(): array
    {
        $st = ['on' => false, 'rev' => 0, 'css' => false, 'js' => false, 'head' => '', 'body' => '', 'bundles' => false];
        try {
            if (self::disabled()) return $st;
            $st['rev'] = self::rev();
            foreach (self::liveItems() as $it) {
                if ($it['kind'] === 'global') {
                    if ($it['slug'] === 'global-css' && trim($it['css']) !== '') $st['css'] = true;
                    if ($it['slug'] === 'global-js' && trim($it['js']) !== '') $st['js'] = true;
                    if ($it['slug'] === 'head') $st['head'] = $it['html'];
                    if ($it['slug'] === 'body-end') $st['body'] = $it['html'];
                } elseif ($it['kind'] === 'page' || $it['kind'] === 'section') $st['bundles'] = true;
            }
            $st['on'] = $st['css'] || $st['js'] || $st['head'] !== '' || $st['body'] !== '' || $st['bundles'];
        } catch (\Throwable $e) { return ['on' => false, 'rev' => 0, 'css' => false, 'js' => false, 'head' => '', 'body' => '', 'bundles' => false]; }
        return $st;
    }

    /* ------------------------------------------------------------ preview */

    /** Adds a loop counter to for/while/do bodies (preview only): an infinite loop throws instead of freezing the tab. */
    public static function loopGuard(string $js): string
    {
        if (trim($js) === '') return $js;
        $pre = 'var __lg={n:0,t:Date.now()};function __lc(){if(++__lg.n%1000===0&&Date.now()-__lg.t>2500){throw new Error("Loop guard: infinite loop suspected, code stopped")}}';
        $paren = '\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)';
        $js = (string) preg_replace('/\b(for|while)\s*(' . $paren . ')\s*\{/', '$1$2{__lc();', $js);
        $js = (string) preg_replace('/\bdo\s*\{/', 'do{__lc();', $js);
        return $pre . "\n" . $js;
    }

    private static function skeleton(): string
    {
        $f = Config::get('preview')['skeleton_file'] ?? null;
        if ($f && is_file((string) $f)) return (string) file_get_contents((string) $f);
        return Skeleton::html();
    }

    private static function consoleHook(): string
    {
        return '(function(){var P=window.parent;function fmt(x){try{if(x instanceof Error)return x.stack||String(x);if(typeof x==="object")return JSON.stringify(x);return String(x)}catch(e){return String(x)}}'
            . 'function send(l,a){try{P.postMessage({cm:"console",level:l,args:a.map(fmt)},"*")}catch(e){}}'
            . '["log","info","warn","error","debug"].forEach(function(l){var o=console[l];console[l]=function(){send(l,[].slice.call(arguments));try{o.apply(console,arguments)}catch(e){}}});'
            . 'window.addEventListener("error",function(e){send("error",[(e.message||"Error")+(e.lineno?" (line "+e.lineno+")":"")])});'
            . 'window.addEventListener("unhandledrejection",function(e){send("error",["Unhandled promise rejection: "+fmt(e.reason)])})})();';
    }

    /**
     * Preview document for a sandboxed iframe (srcdoc). It uses the SAME runtime as production, so what you see is what
     * visitors get. $draft: id?, kind, slug?, target, position, html, css, js.
     */
    public static function previewDoc(array $draft, bool $guard, bool $noJs): string
    {
        $items = self::liveItems();
        $kind = (string) ($draft['kind'] ?? 'section'); $id = (int) ($draft['id'] ?? 0);
        $d = ['id' => $id ?: 999999, 'kind' => $kind, 'slug' => (string) ($draft['slug'] ?? 'preview'), 'name' => '', 'sort' => -1,
              'html' => (string) ($draft['html'] ?? ''), 'css' => (string) ($draft['css'] ?? ''), 'js' => (string) ($draft['js'] ?? ''),
              'target' => (string) ($draft['target'] ?? '*'), 'position' => (string) ($draft['position'] ?? 'bottom')];
        $items = array_values(array_filter($items, fn($it) => $it['id'] !== $id));
        $route = (string) (array_key_first(array_diff_key(Config::routes(), ['*' => 1])) ?? 'home'); $sub = '';
        if ($kind === 'component') {
            $items[] = $d;
            $items[] = ['id' => 999998, 'kind' => 'section', 'slug' => '_preview', 'name' => '', 'sort' => -2, 'html' => '[component:' . $d['slug'] . ']', 'css' => '', 'js' => '', 'target' => '*', 'position' => (string) (array_key_first(Config::slotLabels()) ?? 'bottom')];
        } elseif ($kind === 'global') {
            $items[] = $d;
        } else {
            $items[] = $d;
            if ($kind === 'page' || ($kind === 'section' && $d['target'] !== '*')) [$route, $sub] = array_pad(explode(':', $d['target'], 2), 2, '');
        }
        $bundle = self::buildBundle($items, $route, $sub);
        $glob = ['css' => self::globalCode('global-css', $items), 'js' => self::globalCode('global-js', $items), 'head' => self::globalCode('head', $items), 'body' => self::globalCode('body-end', $items)];
        $strip = function (callable $fn) use (&$glob, &$bundle) {
            $glob['js'] = $fn($glob['js']);
            foreach ($bundle['pages'] as &$p) $p['js'] = $fn($p['js']);
            foreach ($bundle['sections'] as &$s) $s['js'] = $fn($s['js']);
            foreach ($bundle['components'] as &$c) $c['js'] = $fn($c['js']);
        };
        if ($guard) $strip(fn($js) => self::loopGuard($js));
        if ($noJs) $strip(fn($js) => '');
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
        $env = ['route' => $route, 'sub' => $sub, 'lang' => I18n::lang(), 'slots' => Config::slotMap(), 'root' => Config::get('root_selector', 'body')];
        $rt = (string) @file_get_contents(dirname(__DIR__) . '/public/assets/cc-runtime.js');
        $css = '';
        foreach (Config::get('preview')['css_files'] as $cf) if (is_file((string) $cf)) $css .= "\n" . file_get_contents((string) $cf);
        $note = $noJs ? '<div style="position:fixed;bottom:8px;right:8px;z-index:99999;background:#7a4b00;color:#fff;font:12px system-ui;padding:6px 10px;border-radius:6px">' . self::t('preview.safe_note') . '</div>' : '';
        return '<!doctype html><html lang="' . I18n::lang() . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>' . str_replace('</style', '<\/style', $css) . '</style><script>' . self::consoleHook() . '</script></head>'
            . '<body data-route="' . htmlspecialchars($route, ENT_QUOTES) . '">' . self::skeleton() . $note
            . '<script>' . str_replace('</script', '<\/script', $rt) . '</script>'
            . '<script>(function(){var B=' . json_encode($bundle, $flags) . ',G=' . json_encode($glob, $flags) . ',E=' . json_encode($env, $flags) . ';'
            . 'E.root=document.querySelector(E.root)||document.body;'
            . 'try{CodeManagerRuntime.applyGlobal(G);CodeManagerRuntime.apply(B,E);console.info("' . self::t('preview.ready') . '");}catch(err){console.error(err&&err.stack||String(err))}})();</script>'
            . '</body></html>';
    }

    /* ------------------------------------------------------------ import / export */

    public const FORMAT = 'php-code-manager';

    public static function export(int $id): array
    {
        $it = self::item($id);
        if (!$it) throw new InvalidArgumentException(self::t('err.not_found'));
        return ['format' => self::FORMAT, 'version' => 1, 'type' => $it['kind'], 'name' => $it['name'], 'slug' => $it['slug'], 'description' => $it['description'],
                'target' => $it['target'], 'position' => $it['position'], 'html' => $it['html'], 'css' => $it['css'], 'js' => $it['js']];
    }

    public static function exportAll(): array
    {
        $out = [];
        foreach (self::db()->query('SELECT id FROM cm_items ORDER BY kind, sort, id') as $r) $out[] = self::export((int) $r['id']);
        return ['format' => self::FORMAT, 'version' => 1, 'items' => $out];
    }

    /** Imports always arrive as DRAFTS; publishing stays your decision. @return int[] ids */
    public static function import(array $data): array
    {
        if (($data['format'] ?? '') !== self::FORMAT) throw new InvalidArgumentException(self::t('err.bad_format'));
        $list = isset($data['items']) && is_array($data['items']) ? $data['items'] : [$data];
        $ids = [];
        foreach ($list as $x) {
            if (!is_array($x)) continue;
            $type = (string) ($x['type'] ?? '');
            $fields = ['html' => (string) ($x['html'] ?? ''), 'css' => (string) ($x['css'] ?? ''), 'js' => (string) ($x['js'] ?? ''), 'description' => (string) ($x['description'] ?? '')];
            if ($type === 'global') {
                $slug = (string) ($x['slug'] ?? ''); if (!isset(self::GLOBALS[$slug])) continue;
                $st = self::db()->prepare("SELECT id FROM cm_items WHERE kind='global' AND slug = ?"); $st->execute([$slug]);
                if ($id = (int) $st->fetchColumn()) { self::saveDraft($id, $fields); $ids[] = $id; }
            } elseif (in_array($type, ['component', 'section', 'page'], true)) {
                $name = trim((string) ($x['name'] ?? '')) ?: self::t('imported');
                if ($type === 'page') {
                    $t = (string) ($x['target'] ?? ''); if (!array_key_exists($t, Config::routes()) || $t === '*') continue;
                    $st = self::db()->prepare("SELECT id FROM cm_items WHERE kind='page' AND slug = ?"); $st->execute([$t]);
                    $id = (int) $st->fetchColumn() ?: self::create('page', $name, $t);
                } else $id = self::create($type, $name, (string) ($x['target'] ?? '*'), (string) ($x['position'] ?? ''));
                self::saveDraft($id, $fields + ['name' => $name]);
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /* ------------------------------------------------------------ snippets */

    public static function snippets(): array { return self::db()->query('SELECT * FROM cm_snippets ORDER BY category, name')->fetchAll(); }

    public static function saveSnippet(int $id, array $f): int
    {
        $vals = [trim((string) ($f['name'] ?? '')) ?: self::t('unnamed'), trim((string) ($f['category'] ?? '')), (string) ($f['description'] ?? ''), (string) ($f['html'] ?? ''), (string) ($f['css'] ?? ''), (string) ($f['js'] ?? '')];
        if ($id > 0) { self::db()->prepare('UPDATE cm_snippets SET name=?,category=?,description=?,html=?,css=?,js=? WHERE id=?')->execute([...$vals, $id]); return $id; }
        self::db()->prepare('INSERT INTO cm_snippets(name,category,description,html,css,js,created_at) VALUES(?,?,?,?,?,?,?)')->execute([...$vals, time()]);
        return (int) self::db()->lastInsertId();
    }
    public static function deleteSnippet(int $id): void { self::db()->prepare('DELETE FROM cm_snippets WHERE id = ?')->execute([$id]); }
}
