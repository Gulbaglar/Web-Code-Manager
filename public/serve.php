<?php
declare(strict_types=1);
/**
 * Public, READ-ONLY endpoint. Serves only PUBLISHED + active code; returns nothing when custom code is disabled.
 *   ?g=css | ?g=js               published global CSS / JS
 *   ?route=home&sub=&v=<rev>     JSON bundle for a route (for single-page apps)
 * When v matches the current revision the response is cached by browsers for a year (each publish bumps the revision).
 */
require dirname(__DIR__) . '/src/bootstrap.php';

use CodeManager\Manager;

$rev = 0; $off = true;
try { $off = Manager::disabled(); $rev = Manager::rev(); } catch (\Throwable $e) {}

header('Cache-Control: ' . (((int) ($_GET['v'] ?? -1) === $rev) ? 'public, max-age=31536000, immutable' : 'no-store'));
header('X-Content-Type-Options: nosniff');

$g = (string) ($_GET['g'] ?? '');
if ($g === 'css' || $g === 'js') {
    header('Content-Type: ' . ($g === 'css' ? 'text/css' : 'application/javascript') . '; charset=utf-8');
    if ($off) { echo '/* Code Manager: disabled */'; exit; }
    try { echo Manager::globalCode($g === 'css' ? 'global-css' : 'global-js'); } catch (\Throwable $e) { echo '/* Code Manager: error */'; }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$route = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($_GET['route'] ?? '')));
$sub   = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($_GET['sub'] ?? '')));
$empty = ['pages' => [], 'sections' => [], 'components' => []];
if ($off || $route === '') { echo json_encode($empty); exit; }
try { echo json_encode(Manager::buildBundle(Manager::liveItems(), $route, $sub), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
catch (\Throwable $e) { echo json_encode($empty); }
