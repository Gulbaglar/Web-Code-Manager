<?php
declare(strict_types=1);
/**
 * Single place that connects the admin UI to the module. If you copy `public/` somewhere else, change the path below
 * to where you put the module's `src/` folder, and optionally point to a custom config file:
 *
 *     define('CODE_MANAGER_CONFIG', '/path/to/my-config.php');
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';

use CodeManager\I18n;

function cm_e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function cm_lang_switcher(): string
{
    $out = '';
    foreach (I18n::LANGS as $code => $name) {
        $q = $_GET; $q['lang'] = $code;
        $out .= '<a class="lang' . (I18n::lang() === $code ? ' on' : '') . '" href="?' . cm_e(http_build_query($q)) . '">' . cm_e($name) . '</a>';
    }
    return '<span class="langs" title="' . cm_e(I18n::t('ui.language')) . '">' . $out . '</span>';
}

function cm_page(string $title, string $body, bool $showUser = true, bool $full = false): void
{
    ?><!doctype html>
<html lang="<?= cm_e(I18n::lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= cm_e($title) ?></title>
<link rel="stylesheet" href="cm.css?v=<?= (int) @filemtime(__DIR__ . '/cm.css') ?>">
</head>
<body<?= $full ? ' class="full"' : '' ?>>
<div class="topbar">
  <span class="brand"><b>Code</b> Manager</span>
  <span class="spacer"></span>
  <?= cm_lang_switcher() ?>
  <?php if ($showUser): ?><a class="small" href="logout.php"><?= cm_e(I18n::t('ui.logout')) ?></a><?php endif ?>
</div>
<?= $body ?>
</body>
</html>
<?php
}
