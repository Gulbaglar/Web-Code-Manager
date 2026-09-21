<?php
declare(strict_types=1);
/** Code Manager — admin JSON API. Requires an authenticated admin + a CSRF header. */
require __DIR__ . '/_boot.php';

use CodeManager\Auth;
use CodeManager\Config;
use CodeManager\I18n;
use CodeManager\Manager;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function ok(array $x = []): never { out(['ok' => true] + $x); }
function bad(string $m, int $c = 422): never { out(['ok' => false, 'error' => $m], $c); }

if (!Auth::check()) bad(I18n::t('err.session_required'), 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad(I18n::t('err.post_required'), 405);
if (!Auth::csrfOk((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) bad(I18n::t('err.csrf'), 419);

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];
$action = (string) ($in['action'] ?? '');
$id = (int) ($in['id'] ?? 0);

try {
    Manager::ensureDefaults();
    switch ($action) {
        case 'list':
            ok(['items' => Manager::listItems(), 'snippets' => Manager::snippets(), 'routes' => Config::routes(), 'positions' => Config::slotLabels(),
                'globals' => Manager::GLOBALS, 'disabled' => Manager::disabled(), 'rev' => Manager::rev(), 'safeParam' => Config::get('safe_param', 'cm_safe')]);

        case 'get':
            $it = Manager::item($id); if (!$it) bad(I18n::t('err.not_found'), 404);
            ok(['item' => $it, 'versions' => Manager::versions($id)]);

        case 'page_open': {
            $target = (string) ($in['target'] ?? '');
            $st = \CodeManager\Db::pdo()->prepare("SELECT id FROM cm_items WHERE kind='page' AND slug = ?"); $st->execute([$target]);
            $pid = (int) $st->fetchColumn() ?: Manager::create('page', '', $target);
            ok(['id' => $pid]);
        }

        case 'create':
            ok(['id' => Manager::create((string) ($in['kind'] ?? ''), (string) ($in['name'] ?? ''), (string) ($in['target'] ?? '*'), (string) ($in['position'] ?? ''))]);

        case 'save':
            Manager::saveDraft($id, is_array($in['fields'] ?? null) ? $in['fields'] : []);
            ok(['item' => Manager::item($id), 'items' => Manager::listItems()]);

        case 'publish':
            $v = Manager::publish($id, (string) ($in['note'] ?? ''));
            ok(['version' => $v, 'items' => Manager::listItems(), 'versions' => Manager::versions($id), 'rev' => Manager::rev()]);

        case 'restore':
            $v = Manager::restore($id, (int) ($in['version'] ?? 0));
            ok(['version' => $v, 'item' => Manager::item($id), 'items' => Manager::listItems(), 'versions' => Manager::versions($id)]);

        case 'versions':
            ok(['versions' => Manager::versions($id)]);

        case 'version': {
            $v = Manager::version($id, (int) ($in['version'] ?? 0)); if (!$v) bad(I18n::t('err.version_not_found'), 404);
            ok(['version' => $v]);
        }

        case 'delete_version':
            Manager::deleteVersion($id, (int) ($in['version'] ?? 0));
            ok(['versions' => Manager::versions($id)]);

        case 'toggle':
            Manager::setActive($id, !empty($in['active']));
            ok(['items' => Manager::listItems()]);

        case 'delete':
            Manager::delete($id);
            ok(['items' => Manager::listItems()]);

        case 'reorder':
            Manager::reorder(is_array($in['ids'] ?? null) ? $in['ids'] : []);
            ok(['items' => Manager::listItems()]);

        case 'disable_all':
            Manager::setDisabled(!empty($in['on']));
            ok(['disabled' => Manager::disabled()]);

        case 'preview':
            ok(['doc' => Manager::previewDoc(is_array($in['draft'] ?? null) ? $in['draft'] : [], !empty($in['guard']), !empty($in['noJs']))]);

        case 'export':
            ok(['data' => $id > 0 ? Manager::export($id) : Manager::exportAll()]);

        case 'import': {
            $data = json_decode((string) ($in['json'] ?? ''), true);
            if (!is_array($data)) bad(I18n::t('err.bad_json'));
            ok(['ids' => Manager::import($data), 'items' => Manager::listItems()]);
        }

        case 'snip_save':
            ok(['id' => Manager::saveSnippet((int) ($in['id'] ?? 0), is_array($in['fields'] ?? null) ? $in['fields'] : []), 'snippets' => Manager::snippets()]);

        case 'snip_delete':
            Manager::deleteSnippet($id);
            ok(['snippets' => Manager::snippets()]);

        default:
            bad(I18n::t('err.unknown_action'), 400);
    }
} catch (InvalidArgumentException $e) {
    bad($e->getMessage());
} catch (\Throwable $e) {
    error_log('[CodeManager] ' . $e->getMessage());
    bad(I18n::t('err.server_error') . (Config::get('environment') === 'dev' ? ': ' . $e->getMessage() : ''), 500);
}
