<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

use CodeManager\Auth;
use CodeManager\Config;
use CodeManager\I18n;
use CodeManager\Manager;

Auth::require();
Manager::ensureDefaults();
$safeMode = isset($_GET['safeMode']) && $_GET['safeMode'] !== '0';

ob_start(); ?>
<div class="cm" id="cm">
  <div class="cm-safe" id="cmSafeBanner" hidden>
    <b><?= cm_e(I18n::t('ui.safe.title')) ?></b> — <?= cm_e(I18n::t('ui.safe.text')) ?>
    <button class="cm-btn" id="cmSafeOff"><?= cm_e(I18n::t('ui.safe.enable')) ?></button>
  </div>
  <?php if ($safeMode): ?><div class="cm-safe cm-safe-soft"><b>?safeMode=1</b> — <?= cm_e(I18n::t('ui.safe.preview_nojs')) ?></div><?php endif ?>

  <div class="cm-top">
    <div class="cm-title" id="cmTitle"><?= cm_e(I18n::t('ui.title')) ?></div>
    <span class="cm-badge" id="cmStatus"></span>
    <span class="cm-spacer"></span>
    <button class="cm-btn" id="btnSave" title="Ctrl+S"><?= cm_e(I18n::t('ui.btn.save')) ?></button>
    <button class="cm-btn" id="btnPreview" title="Ctrl+Enter"><?= cm_e(I18n::t('ui.btn.preview')) ?></button>
    <button class="cm-btn cm-primary" id="btnPublish"><?= cm_e(I18n::t('ui.btn.publish')) ?></button>
    <button class="cm-btn" id="btnVersions"><?= cm_e(I18n::t('ui.btn.versions')) ?></button>
    <button class="cm-btn" id="btnToggle"><?= cm_e(I18n::t('ui.btn.disable')) ?></button>
    <span class="cm-sep"></span>
    <button class="cm-btn cm-danger" id="btnKill" title="<?= cm_e(I18n::t('ui.btn.kill_hint')) ?>"><?= cm_e(I18n::t('ui.btn.kill')) ?></button>
    <button class="cm-btn" id="btnFull" title="<?= cm_e(I18n::t('ui.btn.fullscreen')) ?>">⛶</button>
  </div>

  <div class="cm-body">
    <aside class="cm-left" id="cmNav"></aside>
    <main class="cm-main">
      <div class="cm-tabs" id="cmTabs"></div>
      <div class="cm-editor" id="cmEditor"></div>
      <div class="cm-editbar"><span id="cmEdInfo" class="cm-dim"></span><span class="cm-spacer"></span><span id="cmErrs" class="cm-dim"></span></div>
      <div class="cm-preview" id="cmPreviewBox">
        <div class="cm-prevhead">
          <b><?= cm_e(I18n::t('ui.preview.title')) ?></b><span class="cm-dim"><?= cm_e(I18n::t('ui.preview.sandbox')) ?></span>
          <span class="cm-spacer"></span>
          <label class="cm-dim" title="<?= cm_e(I18n::t('ui.preview.guard_hint')) ?>"><input type="checkbox" id="cmGuard" checked> <?= cm_e(I18n::t('ui.preview.guard')) ?></label>
          <select id="cmDevice"><option value="100%"><?= cm_e(I18n::t('ui.preview.desktop')) ?></option><option value="820px"><?= cm_e(I18n::t('ui.preview.tablet')) ?></option><option value="390px"><?= cm_e(I18n::t('ui.preview.mobile')) ?></option></select>
          <button class="cm-btn cm-sm" id="btnPrevToggle"><?= cm_e(I18n::t('ui.preview.minimize')) ?></button>
        </div>
        <div class="cm-prevbody" id="cmPrevBody">
          <div class="cm-frame"><iframe id="cmFrame" sandbox="allow-scripts allow-forms allow-modals allow-popups" title="Preview"></iframe></div>
          <div class="cm-console">
            <div class="cm-conhead"><b>Console</b><span class="cm-spacer"></span><button class="cm-btn cm-sm" id="btnConClear"><?= cm_e(I18n::t('ui.preview.clear')) ?></button></div>
            <div class="cm-conbody" id="cmCon"><div class="cm-dim"><?= cm_e(I18n::t('ui.preview.console_hint')) ?></div></div>
          </div>
        </div>
      </div>
    </main>
    <aside class="cm-right" id="cmMeta"></aside>
  </div>

  <div class="cm-modal" id="cmModal" hidden><div class="cm-modal-box" id="cmModalBox"></div></div>
  <input type="file" id="cmImportFile" accept=".json,application/json" hidden>
</div>
<script>
window.CM_BOOT = <?= json_encode([
    'api'      => 'api.php',
    'csrf'     => Auth::csrf(),
    'lang'     => I18n::lang(),
    'i18n'     => I18n::forJs(),
    'safeMode' => $safeMode,
    'cdn'      => 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min',
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="cm.js?v=<?= (int) @filemtime(__DIR__ . '/cm.js') ?>"></script>
<?php cm_page(I18n::t('ui.title'), (string) ob_get_clean(), true, true);
