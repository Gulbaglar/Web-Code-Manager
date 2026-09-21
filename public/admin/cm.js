/* Code Manager — admin UI (Monaco editor + sandbox preview + version history). Texts come from lang/*.php ("ui.*" keys). */
(function () {
  'use strict';
  var CFG = window.CM_BOOT;
  var LANGS = { html: 'html', css: 'css', js: 'javascript' };
  var LABEL = { html: 'HTML', css: 'CSS', js: 'JavaScript' };
  var S = {
    items: [], snips: [], routes: {}, positions: {}, globals: {}, disabled: false, safeParam: 'cm_safe',
    cur: null, tab: 'html', dirty: false, previewedKey: null, errCount: 0
  };

  var $ = function (s, r) { return (r || document).querySelector(s); };
  /* T('key', {name: value}) */
  function T(k, p) {
    var s = (CFG.i18n && CFG.i18n[k]) || k;
    if (p) for (var n in p) s = s.split('{' + n + '}').join(p[n]);
    return s;
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]; }); }
  function fmtDate(ts) { if (!ts) return '—'; var d = new Date(ts * 1000); return d.toLocaleDateString(CFG.lang) + ' ' + d.toLocaleTimeString(CFG.lang, { hour: '2-digit', minute: '2-digit' }); }

  function api(action, data) {
    return fetch(CFG.api, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf },
      body: JSON.stringify(Object.assign({ action: action }, data || {}))
    }).then(function (r) { return r.json().catch(function () { return { ok: false, error: T('ui.err.bad_response') }; }); })
      .then(function (j) { if (!j.ok) throw new Error(j.error || 'Error'); return j; });
  }
  var toastT;
  function toast(msg, type) {
    var t = $('.cm-toast'); if (t) t.remove();
    t = document.createElement('div'); t.className = 'cm-toast ' + (type || 'ok'); t.textContent = msg;
    document.body.appendChild(t); clearTimeout(toastT); toastT = setTimeout(function () { t.remove(); }, type === 'err' ? 6000 : 2600);
  }
  function fail(e) { toast(e && e.message ? e.message : String(e), 'err'); }

  /* ================= EDITOR (Monaco; plain textarea fallback) ================= */
  var Ed = {
    mon: null, editor: null, models: {}, vals: { html: '', css: '', js: '' }, cur: 'html', loading: false, ta: null, onChange: function () {},
    init: function () {
      var box = $('#cmEditor');
      return new Promise(function (res, rej) {
        var s = document.createElement('script');
        s.src = CFG.cdn + '/vs/loader.js'; s.onerror = function () { rej(new Error('loader')); };
        var to = setTimeout(function () { rej(new Error('timeout')); }, 12000);
        s.onload = function () {
          try {
            window.require.config({ paths: { vs: CFG.cdn + '/vs' } });
            window.MonacoEnvironment = { getWorkerUrl: function () {
              return 'data:text/javascript;charset=utf-8,' + encodeURIComponent("self.MonacoEnvironment={baseUrl:'" + CFG.cdn + "/'};importScripts('" + CFG.cdn + "/vs/base/worker/workerMain.js');");
            } };
            window.require(['vs/editor/editor.main'], function () { clearTimeout(to); res(window.monaco); }, function (e) { rej(e); });
          } catch (e) { rej(e); }
        };
        document.head.appendChild(s);
      }).then(function (monaco) {
        Ed.mon = monaco;
        monaco.languages.typescript.javascriptDefaults.setDiagnosticsOptions({ diagnosticCodesToIgnore: [1108] });
        monaco.languages.typescript.javascriptDefaults.addExtraLib(
          'declare const ctx:{route:string;sub:string;lang:string;root:HTMLElement;el:HTMLElement|null;' +
          'qs(sel:string,base?:ParentNode):Element|null;qsa(sel:string,base?:ParentNode):Element[];' +
          'on(target:EventTarget,type:string,fn:(e:any)=>void,opts?:any):any;onLeave(fn:()=>void):void;' +
          'setTimeout(fn:()=>void,ms:number):number;setInterval(fn:()=>void,ms:number):number};', 'ts:cm-ctx.d.ts');
        Ed.editor = monaco.editor.create(box, {
          theme: 'vs-dark', automaticLayout: true, fontSize: 13, tabSize: 2, insertSpaces: true, autoIndent: 'full',
          minimap: { enabled: true }, scrollBeyondLastLine: false, folding: true, bracketPairColorization: { enabled: true },
          formatOnPaste: true, renderWhitespace: 'selection', smoothScrolling: true, model: null
        });
        monaco.editor.onDidChangeMarkers(Ed.countErrors);
        Ed.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function () { saveDraft(); });
        Ed.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, function () { preview(); });
        Ed.mode = 'monaco';
      }).catch(function () {
        Ed.mode = 'textarea';
        var ta = Ed.ta = document.createElement('textarea');
        ta.spellcheck = false;
        ta.addEventListener('input', function () { Ed.vals[Ed.cur] = ta.value; if (!Ed.loading) Ed.onChange(); });
        ta.addEventListener('keydown', function (e) {
          if (e.key === 'Tab') { e.preventDefault(); var s = ta.selectionStart; ta.setRangeText('  ', s, ta.selectionEnd, 'end'); ta.dispatchEvent(new Event('input')); }
          if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveDraft(); }
          if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); preview(); }
        });
        box.appendChild(ta);
        $('#cmEdInfo').textContent = T('ui.ed.fallback');
      });
    },
    load: function (vals) {
      Ed.loading = true;
      Ed.vals = { html: vals.html || '', css: vals.css || '', js: vals.js || '' };
      if (Ed.mode === 'monaco') {
        Object.keys(Ed.models).forEach(function (k) { Ed.models[k].dispose(); });
        Ed.models = {};
        ['html', 'css', 'js'].forEach(function (l) {
          var m = Ed.mon.editor.createModel(Ed.vals[l], LANGS[l]);
          m.onDidChangeContent(function () { if (!Ed.loading) Ed.onChange(); });
          Ed.models[l] = m;
        });
        Ed.editor.setModel(Ed.models[Ed.cur]);
      } else if (Ed.ta) { Ed.ta.value = Ed.vals[Ed.cur]; }
      Ed.loading = false;
    },
    show: function (lang) {
      if (Ed.mode === 'monaco') { Ed.cur = lang; if (Ed.models[lang]) Ed.editor.setModel(Ed.models[lang]); Ed.editor.focus(); }
      else if (Ed.ta) { Ed.vals[Ed.cur] = Ed.ta.value; Ed.cur = lang; Ed.ta.value = Ed.vals[lang]; Ed.ta.focus(); }
      else Ed.cur = lang;
    },
    get: function (lang) {
      if (Ed.mode === 'monaco') return Ed.models[lang] ? Ed.models[lang].getValue() : '';
      if (Ed.ta && Ed.cur === lang) return Ed.ta.value;
      return Ed.vals[lang] || '';
    },
    all: function () { return { html: Ed.get('html'), css: Ed.get('css'), js: Ed.get('js') }; },
    append: function (lang, text) {
      if (!text) return;
      var cur = Ed.get(lang), val = cur + (cur && !/\n$/.test(cur) ? '\n\n' : (cur ? '\n' : '')) + text;
      if (Ed.mode === 'monaco' && Ed.models[lang]) Ed.models[lang].setValue(val);
      else { Ed.vals[lang] = val; if (Ed.ta && Ed.cur === lang) Ed.ta.value = val; }
      if (Ed.mode !== 'monaco') Ed.onChange();
    },
    countErrors: function () {
      if (Ed.mode !== 'monaco') return;
      var n = 0;
      Object.keys(Ed.models).forEach(function (k) {
        n += Ed.mon.editor.getModelMarkers({ resource: Ed.models[k].uri }).filter(function (m) { return m.severity === 8; }).length;
      });
      $('#cmErrs').textContent = n ? '⚠ ' + T('ui.ed.errors', { n: n }) : T('ui.ed.no_errors');
      $('#cmErrs').style.color = n ? 'var(--danger)' : '';
    }
  };

  /* ================= HELPERS ================= */
  function curItem() { return S.cur && S.cur.type === 'item' ? S.cur.item : null; }
  function listEntry(id) { for (var i = 0; i < S.items.length; i++) if (S.items[i].id === id) return S.items[i]; return null; }
  function tabsFor() {
    var it = curItem();
    if (it && it.kind === 'global') return [S.globals[it.slug]];
    return ['html', 'css', 'js'];
  }
  function markDirty() { S.dirty = true; renderTopState(); }
  function itemName(i) { return i.kind === 'global' ? T('ui.global.' + i.slug) : i.name; }

  function draftPayload() {
    var it = curItem(), a = Ed.all(), m = readMeta();
    if (S.cur && S.cur.type === 'snip') return { kind: 'section', slug: 'snippet', target: '*', position: Object.keys(S.positions)[0] || 'bottom', html: a.html, css: a.css, js: a.js };
    return { id: it.id, kind: it.kind, slug: m.slug || it.slug, target: m.target || it.target, position: m.position || it.position, html: a.html, css: a.css, js: a.js };
  }
  function readMeta() {
    var g = function (id) { var e = $('#' + id); return e ? e.value : undefined; };
    return { name: g('mName'), slug: g('mSlug'), target: g('mTarget'), position: g('mPos'), sort: g('mSort'), description: g('mDesc'), category: g('mCat') };
  }

  /* ================= LEFT NAV ================= */
  function dotFor(it) { return '<span class="dot ' + (it && it.active ? it.status : '') + '"></span>'; }
  function renderNav() {
    var cur = S.cur && S.cur.type === 'item' ? S.cur.id : 0, curSn = S.cur && S.cur.type === 'snip' ? S.cur.id : 0;
    var h = '<div class="cm-grp"><div class="cm-grp-h">' + T('ui.nav.global') + '</div>';
    S.items.filter(function (i) { return i.kind === 'global'; }).forEach(function (i) {
      h += '<div class="cm-row' + (cur === i.id ? ' on' : '') + (i.active ? '' : ' off') + '" data-act="item" data-id="' + i.id + '">' + dotFor(i) + '<span class="nm">' + esc(itemName(i)) + '</span></div>';
    });
    h += '</div><div class="cm-grp"><div class="cm-grp-h">' + T('ui.nav.pages') + '</div>';
    Object.keys(S.routes).forEach(function (t) {
      if (t === '*') return;
      var it = S.items.filter(function (i) { return i.kind === 'page' && i.slug === t; })[0];
      h += '<div class="cm-row' + (it && cur === it.id ? ' on' : '') + (it && !it.active ? ' off' : '') + '" data-act="page" data-target="' + esc(t) + '">' + dotFor(it) + '<span class="nm">' + esc(S.routes[t]) + '</span></div>';
    });
    h += '</div><div class="cm-grp"><div class="cm-grp-h">' + T('ui.nav.components') + ' <button data-act="new-comp">' + T('ui.nav.new') + '</button></div>';
    S.items.filter(function (i) { return i.kind === 'component'; }).forEach(function (i) {
      h += '<div class="cm-row' + (cur === i.id ? ' on' : '') + (i.active ? '' : ' off') + '" data-act="item" data-id="' + i.id + '">' + dotFor(i) + '<span class="nm">' + esc(i.name) + '</span></div>';
    });
    h += '</div><div class="cm-grp"><div class="cm-grp-h">' + T('ui.nav.sections') + ' <button data-act="new-sec">' + T('ui.nav.new') + '</button></div>';
    S.items.filter(function (i) { return i.kind === 'section'; }).forEach(function (i) {
      h += '<div class="cm-row' + (cur === i.id ? ' on' : '') + (i.active ? '' : ' off') + '" draggable="true" data-act="item" data-drag="' + i.id + '" data-id="' + i.id + '" title="' + esc(T('ui.nav.drag')) + '">' + dotFor(i) + '<span class="nm">' + esc(i.name) + '</span></div>';
    });
    h += '</div><div class="cm-grp"><div class="cm-grp-h">' + T('ui.nav.snippets') + ' <button data-act="new-snip">' + T('ui.nav.new') + '</button></div>';
    S.snips.forEach(function (s) {
      h += '<div class="cm-row' + (curSn === s.id ? ' on' : '') + '" data-act="snip" data-id="' + s.id + '"><span class="nm" title="' + esc(s.category) + '">' + esc(s.name) + '</span><button class="ins" data-act="ins" data-id="' + s.id + '" title="Insert into editor">' + T('ui.nav.insert') + '</button></div>';
    });
    h += '</div><div class="cm-grp"><div class="cm-grp-h">' + T('ui.nav.versions') + '</div><div class="cm-row" data-act="versions"><span class="nm">' + T('ui.nav.versions_current') + '</span></div></div>';
    h += '<div class="cm-navfoot"><button class="cm-btn cm-sm" data-act="import">' + T('ui.nav.import') + '</button><button class="cm-btn cm-sm" data-act="export-all">' + T('ui.nav.export_all') + '</button>' +
         '<span class="cm-hint">' + T('ui.nav.safe_hint') + ' <code>?' + esc(S.safeParam) + '=1</code></span></div>';
    $('#cmNav').innerHTML = h;
  }

  /* ================= RIGHT PANEL ================= */
  function optList(map, sel) {
    return Object.keys(map).map(function (k) { return '<option value="' + esc(k) + '"' + (k === sel ? ' selected' : '') + '>' + esc(map[k]) + '</option>'; }).join('');
  }
  function statusText(e) {
    if (!e.active) return T('ui.status.disabled');
    if (e.status === 'draft') return T('ui.status.draft');
    if (e.status === 'changed') return T('ui.status.changed', { v: e.published_version });
    return T('ui.status.published', { v: e.published_version });
  }
  function renderMeta() {
    var box = $('#cmMeta'), h = '';
    if (!S.cur) { box.innerHTML = '<p class="cm-hint">' + T('ui.meta.select_hint') + '</p>'; return; }
    if (S.cur.type === 'snip') {
      var sn = S.cur.snip;
      h = '<div class="cm-h">' + T('ui.meta.snippet') + '</div>' +
        '<div class="f"><label>' + T('ui.meta.name') + '</label><input type="text" id="mName" value="' + esc(sn.name) + '"></div>' +
        '<div class="f"><label>' + T('ui.meta.category') + '</label><input type="text" id="mCat" value="' + esc(sn.category) + '"></div>' +
        '<div class="f"><label>' + T('ui.meta.description') + '</label><textarea id="mDesc">' + esc(sn.description) + '</textarea></div>' +
        '<div class="row"><button class="cm-btn cm-danger" data-act="del-snip">' + T('ui.act.delete') + '</button></div>' +
        '<p class="cm-hint" style="margin-top:12px">' + T('ui.meta.snippet_hint') + '</p>';
      box.innerHTML = h; box.oninput = markDirty; return;
    }
    var it = S.cur.item, e = listEntry(it.id) || it, isG = it.kind === 'global';
    h += '<div class="cm-h">' + T('ui.kind.' + it.kind) + '</div>';
    h += '<div class="f"><label>' + T('ui.meta.name') + '</label><input type="text" id="mName" value="' + esc(isG ? itemName(it) : it.name) + '"' + ((isG || it.kind === 'page') ? ' disabled' : '') + '></div>';
    if (it.kind === 'component') h += '<div class="f"><label>' + T('ui.meta.slug') + '</label><input type="text" id="mSlug" value="' + esc(it.slug) + '"><span class="cm-hint">' + T('ui.meta.slug_hint', { slug: esc(it.slug) }) + '</span></div>';
    if (it.kind === 'section') {
      h += '<div class="f"><label>' + T('ui.meta.target') + '</label><select id="mTarget">' + optList(S.routes, it.target) + '</select></div>';
      h += '<div class="f"><label>' + T('ui.meta.position') + '</label><select id="mPos">' + optList(S.positions, it.position) + '</select></div>';
      h += '<div class="f"><label>' + T('ui.meta.order') + '</label><input type="number" id="mSort" value="' + esc(it.sort) + '"><span class="cm-hint">' + T('ui.meta.order_hint') + '</span></div>';
    }
    if (it.kind === 'page') h += '<div class="f"><label>' + T('ui.meta.target') + '</label><input type="text" disabled value="' + esc(S.routes[it.slug] || it.slug) + '"><span class="cm-hint">' + T('ui.meta.page_hint') + '</span></div>';
    if (!isG) h += '<div class="f"><label>' + T('ui.meta.description') + '</label><textarea id="mDesc">' + esc(it.description) + '</textarea></div>';
    h += '<div class="f"><label>' + T('ui.meta.status') + '</label><div>' + statusText(e) + '</div><span class="cm-hint">' + T('ui.meta.published') + ': ' + (it.published_version ? 'v' + it.published_version + ' · ' + fmtDate(+it.published_at) : T('ui.meta.none_yet')) + '</span></div>';
    if (isG) h += '<p class="cm-hint">' + T('ui.global.hint.' + it.slug) + '</p>';
    h += '<div class="row"><button class="cm-btn cm-sm" data-act="export">' + T('ui.meta.export') + '</button>' +
         '<button class="cm-btn cm-sm" data-act="as-snip">' + T('ui.meta.as_snippet') + '</button>' +
         (isG ? '' : '<button class="cm-btn cm-sm cm-danger" data-act="del-item">' + T('ui.act.delete') + '</button>') + '</div>';
    box.innerHTML = h; box.oninput = markDirty; box.onchange = markDirty;
  }
  function renderTopState() {
    var it = curItem(), sn = S.cur && S.cur.type === 'snip';
    var title = !S.cur ? T('ui.title') : (sn ? T('ui.meta.snippet') + ': ' + S.cur.snip.name : itemName(it));
    $('#cmTitle').textContent = title + (S.dirty ? ' •' : '');
    var b = $('#cmStatus'), e = it ? (listEntry(it.id) || it) : null;
    if (!S.cur) { b.textContent = ''; b.className = 'cm-badge'; }
    else if (sn) { b.textContent = T('ui.meta.snippet'); b.className = 'cm-badge'; }
    else if (!e.active) { b.textContent = T('ui.badge.disabled'); b.className = 'cm-badge s-off'; }
    else if (e.status === 'draft') { b.textContent = T('ui.badge.draft'); b.className = 'cm-badge'; }
    else if (e.status === 'changed') { b.textContent = T('ui.badge.changed', { v: e.published_version }); b.className = 'cm-badge s-changed'; }
    else { b.textContent = T('ui.badge.published', { v: e.published_version }); b.className = 'cm-badge s-published'; }
    $('#btnSave').textContent = sn ? T('ui.btn.save_snippet') : T('ui.btn.save');
    $('#btnSave').disabled = !S.cur; $('#btnPreview').disabled = !S.cur;
    $('#btnPublish').disabled = !it; $('#btnVersions').disabled = !it; $('#btnToggle').disabled = !it;
    $('#btnToggle').textContent = it && !e.active ? T('ui.btn.enable') : T('ui.btn.disable');
    $('#btnKill').textContent = S.disabled ? T('ui.btn.enable_all') : T('ui.btn.kill');
    $('#cmSafeBanner').hidden = !S.disabled;
  }
  function renderTabs() {
    var langs = tabsFor(), h = '';
    langs.forEach(function (l) { h += '<button class="cm-tab' + (l === S.tab ? ' on' : '') + '" data-tab="' + l + '">' + LABEL[l] + '</button>'; });
    $('#cmTabs').innerHTML = h;
  }

  /* ================= OPEN / SAVE ================= */
  function guard() { return !S.dirty || confirm(T('ui.confirm.unsaved')); }
  function openItem(id) {
    if (!guard()) return;
    api('get', { id: id }).then(function (j) {
      S.cur = { type: 'item', id: id, item: j.item, versions: j.versions };
      var langs = tabsFor(); S.tab = langs[0]; Ed.cur = S.tab;
      Ed.load(j.item); renderTabs(); Ed.show(S.tab);
      S.dirty = false; renderAll();
    }).catch(fail);
  }
  function openSnip(id) {
    if (!guard()) return;
    var sn = S.snips.filter(function (s) { return s.id == id; })[0]; if (!sn) return;
    S.cur = { type: 'snip', id: +id, snip: sn };
    S.tab = 'html'; Ed.cur = 'html'; Ed.load(sn); renderTabs(); Ed.show('html');
    S.dirty = false; renderAll();
  }
  function renderAll() { renderNav(); renderMeta(); renderTopState(); Ed.countErrors(); }

  function saveDraft() { return doSave().catch(fail); }
  function doSave() {
    if (!S.cur) return Promise.resolve();
    var a = Ed.all(), m = readMeta();
    if (S.cur.type === 'snip') {
      return api('snip_save', { id: S.cur.id, fields: { name: m.name, category: m.category, description: m.description, html: a.html, css: a.css, js: a.js } })
        .then(function (j) { S.snips = j.snippets; S.cur.snip = S.snips.filter(function (s) { return s.id == S.cur.id; })[0]; S.dirty = false; renderAll(); toast(T('ui.toast.snippet_saved')); });
    }
    var f = { html: a.html, css: a.css, js: a.js };
    ['name', 'description', 'slug', 'target', 'position', 'sort'].forEach(function (k) { if (m[k] !== undefined) f[k] = m[k]; });
    if (curItem().kind === 'global') delete f.name;
    return api('save', { id: S.cur.id, fields: f }).then(function (j) {
      S.cur.item = j.item; S.items = j.items; S.dirty = false; renderAll(); toast(T('ui.toast.draft_saved'));
    });
  }

  function publish() {
    var it = curItem(); if (!it) return;
    var d = draftPayload(), key = JSON.stringify([d.html, d.css, d.js, d.target, d.position]);
    if (S.previewedKey !== key && !confirm(T('ui.confirm.not_previewed'))) return;
    if (S.previewedKey === key && S.errCount > 0 && !confirm(T('ui.confirm.preview_errors', { n: S.errCount }))) return;
    var note = prompt(T('ui.prompt.note'), ''); if (note === null) return;
    doSave().then(function () {
      return api('publish', { id: it.id, note: note }).then(function (j) {
        S.items = j.items; S.cur.item = Object.assign(S.cur.item, { published_version: j.version, published_at: Math.floor(Date.now() / 1000) });
        S.dirty = false; renderAll(); toast(T('ui.toast.published', { v: j.version }));
      });
    }).catch(fail);
  }

  function toggleItem() {
    var it = curItem(); if (!it) return; var e = listEntry(it.id);
    api('toggle', { id: it.id, active: !e.active }).then(function (j) { S.items = j.items; renderAll(); toast(T(e.active ? 'ui.toast.item_off' : 'ui.toast.item_on')); }).catch(fail);
  }
  function killSwitch() {
    var on = !S.disabled;
    if (on && !confirm(T('ui.confirm.kill'))) return;
    api('disable_all', { on: on }).then(function (j) { S.disabled = j.disabled; renderAll(); toast(T(j.disabled ? 'ui.toast.killed' : 'ui.toast.unkilled')); }).catch(fail);
  }

  /* ================= PREVIEW ================= */
  function preview(override) {
    if (!S.cur) return;
    var d = override || draftPayload();
    var box = $('#cmPreviewBox'); box.classList.remove('min'); $('#btnPrevToggle').textContent = T('ui.preview.minimize');
    api('preview', { draft: d, guard: $('#cmGuard').checked, noJs: CFG.safeMode }).then(function (j) {
      S.errCount = 0; conClear();
      S.previewedKey = JSON.stringify([d.html, d.css, d.js, d.target, d.position]);
      $('#cmFrame').srcdoc = j.doc;
    }).catch(fail);
  }
  function conClear() { $('#cmCon').innerHTML = ''; }
  function conAdd(level, args) {
    var c = $('#cmCon'), d = document.createElement('div');
    d.className = 'ln ' + level; d.textContent = '[' + level + '] ' + args.join(' ');
    c.appendChild(d); c.scrollTop = c.scrollHeight;
    if (level === 'error') S.errCount++;
  }
  window.addEventListener('message', function (e) {
    if (e.source !== $('#cmFrame').contentWindow || !e.data || e.data.cm !== 'console') return;
    conAdd(e.data.level, e.data.args || []);
  });

  /* ================= MODAL ================= */
  function modal(html) { $('#cmModalBox').innerHTML = html; $('#cmModal').hidden = false; }
  function closeModal() { $('#cmModal').hidden = true; }
  $('#cmModal').addEventListener('mousedown', function (e) { if (e.target.id === 'cmModal') closeModal(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { if (!$('#cmModal').hidden) closeModal(); else if ($('#cm').classList.contains('full')) $('#cm').classList.remove('full'); }
  });
  var FIELD = 'width:100%;padding:9px;background:var(--surface2);color:var(--text);border:1px solid var(--line);border-radius:7px';

  function newComponentDlg() {
    modal('<h3>' + T('ui.dlg.new_component') + '</h3><div class="f" style="margin-bottom:12px"><label class="cm-hint">' + T('ui.meta.name') + '</label><input type="text" id="dName" style="' + FIELD + '" placeholder="video-player"></div>' +
      '<button class="cm-btn cm-primary" id="dOk">' + T('ui.dlg.create') + '</button> <button class="cm-btn" data-close>' + T('ui.cancel') + '</button>');
    $('#dName').focus();
    $('#dOk').onclick = function () { create('component', $('#dName').value, '*', ''); };
  }
  function newSectionDlg() {
    modal('<h3>' + T('ui.dlg.new_section') + '</h3>' +
      '<div class="f" style="margin-bottom:10px"><label class="cm-hint">' + T('ui.meta.name') + '</label><input type="text" id="dName" style="' + FIELD + '"></div>' +
      '<div class="f" style="margin-bottom:10px"><label class="cm-hint">' + T('ui.meta.target') + '</label><select id="dTarget" style="' + FIELD + '">' + optList(S.routes, '*') + '</select></div>' +
      '<div class="f" style="margin-bottom:14px"><label class="cm-hint">' + T('ui.meta.position') + '</label><select id="dPos" style="' + FIELD + '">' + optList(S.positions, Object.keys(S.positions)[0]) + '</select></div>' +
      '<button class="cm-btn cm-primary" id="dOk">' + T('ui.dlg.create') + '</button> <button class="cm-btn" data-close>' + T('ui.cancel') + '</button>');
    $('#dName').focus();
    $('#dOk').onclick = function () { create('section', $('#dName').value, $('#dTarget').value, $('#dPos').value); };
  }
  function create(kind, name, target, position) {
    api('create', { kind: kind, name: name, target: target, position: position }).then(function (j) {
      return api('list').then(function (l) { S.items = l.items; closeModal(); S.dirty = false; openItem(j.id); });
    }).catch(fail);
  }

  /* ================= VERSIONS + COMPARE ================= */
  function openVersions() {
    var it = curItem(); if (!it) return toast(T('ui.toast.open_first'), 'err');
    api('versions', { id: it.id }).then(function (j) {
      var h = '<h3>' + T('ui.ver.title', { name: esc(itemName(it)) }) + '</h3>';
      if (!j.versions.length) h += '<p class="cm-hint">' + T('ui.ver.none') + '</p>';
      j.versions.forEach(function (v) {
        var active = v.version === S.cur.item.published_version;
        h += '<div class="cm-vrow"><div><b>' + T('ui.ver.version', { v: v.version }) + '</b><br>' + (active ? '<span class="cm-active">' + T('ui.ver.active') + '</span>' : '<span class="cm-dim">' + T('ui.ver.archived') + '</span>') + '</div>' +
          '<div class="cm-dim">' + T('ui.ver.created') + ': ' + fmtDate(+v.created_at) + '<br>' + T('ui.ver.published') + ': ' + fmtDate(+v.published_at) + (v.note ? '<br>' + T('ui.ver.note') + ': ' + esc(v.note) : '') + ' · ' + v.size + ' ' + T('ui.ver.chars') + '</div>' +
          '<div class="acts"><button class="cm-btn cm-sm" data-vact="preview" data-v="' + v.version + '">' + T('ui.btn.preview') + '</button>' +
          (active ? '' : '<button class="cm-btn cm-sm" data-vact="restore" data-v="' + v.version + '">' + T('ui.ver.restore') + '</button>') +
          '<button class="cm-btn cm-sm" data-vact="compare" data-v="' + v.version + '">' + T('ui.ver.compare') + '</button>' +
          (active ? '' : '<button class="cm-btn cm-sm cm-danger" data-vact="delete" data-v="' + v.version + '">' + T('ui.act.delete') + '</button>') + '</div></div>';
      });
      modal(h + '<p style="margin-top:14px"><button class="cm-btn" data-close>' + T('ui.close') + '</button></p>');
    }).catch(fail);
  }
  function fetchVersion(v) { return api('version', { id: curItem().id, version: v }).then(function (j) { return j.version; }); }

  function diffLines(a, b) {
    var A = a.split('\n'), B = b.split('\n'), n = A.length, m = B.length, out = [], i, j;
    if (n * m > 4000000) { return A.map(function (s) { return { t: '-', s: s }; }).concat(B.map(function (s) { return { t: '+', s: s }; })); }
    var L = new Uint32Array((n + 1) * (m + 1)), W = m + 1;
    for (i = n - 1; i >= 0; i--) for (j = m - 1; j >= 0; j--)
      L[i * W + j] = A[i] === B[j] ? L[(i + 1) * W + j + 1] + 1 : Math.max(L[(i + 1) * W + j], L[i * W + j + 1]);
    i = 0; j = 0;
    while (i < n && j < m) {
      if (A[i] === B[j]) { out.push({ t: '=', s: A[i] }); i++; j++; }
      else if (L[(i + 1) * W + j] >= L[i * W + j + 1]) out.push({ t: '-', s: A[i++] });
      else out.push({ t: '+', s: B[j++] });
    }
    while (i < n) out.push({ t: '-', s: A[i++] });
    while (j < m) out.push({ t: '+', s: B[j++] });
    return out;
  }
  function diffHtml(a, b) {
    var d = diffLines(a || '', b || ''), changed = d.some(function (x) { return x.t !== '='; });
    if (!changed) return '<div class="cm-dim" style="margin:4px 0 12px">' + T('ui.ver.no_diff') + '</div>';
    var keep = new Array(d.length).fill(false);
    d.forEach(function (x, k) { if (x.t !== '=') for (var q = Math.max(0, k - 2); q <= Math.min(d.length - 1, k + 2); q++) keep[q] = true; });
    var html = '', gap = false;
    d.forEach(function (x, k) {
      if (!keep[k]) { gap = true; return; }
      if (gap) { html += '<div class="cm-dim">…</div>'; gap = false; }
      html += '<div class="' + (x.t === '+' ? 'add' : x.t === '-' ? 'del' : '') + '">' + (x.t === '=' ? '  ' : x.t + ' ') + esc(x.s) + '</div>';
    });
    return '<div class="cm-diff">' + html + '</div>';
  }
  function compareVersion(v) {
    var it = S.cur.item, active = it.published_version;
    var pA = fetchVersion(v);
    var pB = (v === active) ? Promise.resolve({ label: T('ui.ver.draft_label'), html: Ed.get('html'), css: Ed.get('css'), js: Ed.get('js') })
                            : fetchVersion(active).then(function (x) { x.label = T('ui.ver.active_label', { v: active }); return x; });
    Promise.all([pA, pB]).then(function (r) {
      var a = r[0], b = r[1];
      var h = '<h3>' + T('ui.ver.compare_title', { a: 'v' + v, b: esc(b.label) }) + '</h3>';
      ['html', 'css', 'js'].forEach(function (l) { h += '<b>' + LABEL[l] + '</b>' + diffHtml(a[l], b[l]); });
      h += '<button class="cm-btn" id="dBack">' + T('ui.ver.back') + '</button> <button class="cm-btn" data-close>' + T('ui.close') + '</button>';
      modal(h); $('#dBack').onclick = openVersions;
    }).catch(fail);
  }

  function download(name, obj) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json' }));
    a.download = name; document.body.appendChild(a); a.click(); a.remove();
  }

  /* ================= EVENTS ================= */
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.closest('[data-close]')) return closeModal();
    var tab = t.closest('[data-tab]');
    if (tab) { S.tab = tab.dataset.tab; Ed.show(S.tab); renderTabs(); return; }
    var va = t.closest('[data-vact]');
    if (va) return versionAction(va.dataset.vact, +va.dataset.v);
    var el = t.closest('[data-act]'); if (!el || !$('#cm').contains(el)) return;
    var act = el.dataset.act, id = +el.dataset.id;
    switch (act) {
      case 'item': openItem(id); break;
      case 'snip': openSnip(id); break;
      case 'page':
        if (!guard()) break;
        api('page_open', { target: el.dataset.target }).then(function (j) { return api('list').then(function (l) { S.items = l.items; S.dirty = false; openItem(j.id); }); }).catch(fail);
        break;
      case 'new-comp': newComponentDlg(); break;
      case 'new-sec': newSectionDlg(); break;
      case 'new-snip':
        api('snip_save', { id: 0, fields: { name: T('ui.new_snippet'), category: T('ui.custom') } }).then(function (j) { S.snips = j.snippets; S.dirty = false; openSnip(j.id); }).catch(fail); break;
      case 'ins': ev.stopPropagation(); insertSnippet(id); break;
      case 'versions': openVersions(); break;
      case 'import': $('#cmImportFile').click(); break;
      case 'export-all': api('export', { id: 0 }).then(function (j) { download('code-manager-backup.json', j.data); }).catch(fail); break;
      case 'export': api('export', { id: curItem().id }).then(function (j) { download((curItem().slug || 'code').replace(/[^a-z0-9-]/gi, '_') + '.cm.json', j.data); }).catch(fail); break;
      case 'as-snip': {
        var nm = prompt(T('ui.prompt.snip_name'), itemName(curItem())); if (!nm) break; var a = Ed.all();
        api('snip_save', { id: 0, fields: { name: nm, category: T('ui.custom'), html: a.html, css: a.css, js: a.js } }).then(function (j) { S.snips = j.snippets; renderNav(); toast(T('ui.toast.snippet_saved')); }).catch(fail); break;
      }
      case 'del-item':
        if (!confirm(T('ui.confirm.delete_item', { name: itemName(curItem()) }))) break;
        api('delete', { id: curItem().id }).then(function (j) { S.items = j.items; S.cur = null; S.dirty = false; Ed.load({}); renderTabs(); renderAll(); toast(T('ui.toast.deleted')); }).catch(fail); break;
      case 'del-snip':
        if (!confirm(T('ui.confirm.delete_snip'))) break;
        api('snip_delete', { id: S.cur.id }).then(function (j) { S.snips = j.snippets; S.cur = null; S.dirty = false; Ed.load({}); renderTabs(); renderAll(); }).catch(fail); break;
    }
  });

  function versionAction(a, v) {
    var it = curItem();
    if (a === 'preview') fetchVersion(v).then(function (x) { closeModal(); preview({ id: it.id, kind: it.kind, slug: it.slug, target: x.target, position: x.position, html: x.html, css: x.css, js: x.js }); }).catch(fail);
    else if (a === 'compare') compareVersion(v);
    else if (a === 'restore') {
      if (!confirm(T('ui.confirm.restore', { v: v }))) return;
      api('restore', { id: it.id, version: v }).then(function (j) {
        S.items = j.items; S.cur.item = j.item; Ed.load(j.item); S.dirty = false; renderAll(); closeModal(); toast(T('ui.toast.restored', { v: v, n: j.version }));
      }).catch(fail);
    } else if (a === 'delete') {
      if (!confirm(T('ui.confirm.delete_version', { v: v }))) return;
      api('delete_version', { id: it.id, version: v }).then(openVersions).catch(fail);
    }
  }

  function insertSnippet(id) {
    var it = curItem(); if (!it) return toast(T('ui.toast.open_first'), 'err');
    var sn = S.snips.filter(function (s) { return s.id == id; })[0]; if (!sn) return;
    var langs = it.kind === 'global' ? [S.globals[it.slug]] : ['html', 'css', 'js'], used = 0;
    langs.forEach(function (l) { if (sn[l]) { Ed.append(l, sn[l]); used++; } });
    if (!used) toast(T('ui.toast.snip_none'), 'err');
    else { toast(T('ui.toast.inserted', { name: sn.name })); markDirty(); }
  }

  $('#cmImportFile').addEventListener('change', function (e) {
    var f = e.target.files[0]; e.target.value = ''; if (!f) return;
    var r = new FileReader();
    r.onload = function () {
      api('import', { json: String(r.result) }).then(function (j) {
        S.items = j.items; renderNav(); toast(T('ui.toast.imported', { n: j.ids.length }));
        if (j.ids[0]) openItem(j.ids[0]);
      }).catch(fail);
    };
    r.readAsText(f);
  });

  /* drag & drop ordering (sections) */
  var dragId = null;
  document.addEventListener('dragstart', function (e) { var r = e.target.closest && e.target.closest('[data-drag]'); if (r) { dragId = r.dataset.drag; e.dataTransfer.effectAllowed = 'move'; } });
  document.addEventListener('dragover', function (e) { var r = e.target.closest && e.target.closest('[data-drag]'); if (r && dragId) { e.preventDefault(); r.classList.add('over'); } });
  document.addEventListener('dragleave', function (e) { var r = e.target.closest && e.target.closest('[data-drag]'); if (r) r.classList.remove('over'); });
  document.addEventListener('drop', function (e) {
    var r = e.target.closest && e.target.closest('[data-drag]'); if (!r || !dragId) return;
    e.preventDefault();
    var ids = S.items.filter(function (i) { return i.kind === 'section'; }).map(function (i) { return String(i.id); });
    var from = ids.indexOf(dragId), to = ids.indexOf(r.dataset.drag);
    if (from < 0 || to < 0 || from === to) { dragId = null; return renderNav(); }
    ids.splice(to, 0, ids.splice(from, 1)[0]); dragId = null;
    api('reorder', { ids: ids }).then(function (j) { S.items = j.items; renderNav(); toast(T('ui.toast.order')); }).catch(fail);
  });

  $('#btnSave').onclick = function () { saveDraft(); };
  $('#btnPreview').onclick = function () { preview(); };
  $('#btnPublish').onclick = publish;
  $('#btnVersions').onclick = openVersions;
  $('#btnToggle').onclick = toggleItem;
  $('#btnKill').onclick = killSwitch;
  $('#cmSafeOff').onclick = killSwitch;
  $('#btnFull').onclick = function () { $('#cm').classList.toggle('full'); };
  $('#btnConClear').onclick = conClear;
  $('#cmDevice').onchange = function () { $('#cmFrame').style.width = this.value; };
  $('#btnPrevToggle').onclick = function () {
    var b = $('#cmPreviewBox'); b.classList.toggle('min'); this.textContent = b.classList.contains('min') ? T('ui.preview.maximize') : T('ui.preview.minimize');
  };
  document.addEventListener('keydown', function (e) {
    if (e.defaultPrevented) return;
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveDraft(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); preview(); }
  });
  window.addEventListener('beforeunload', function (e) { if (S.dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ================= START ================= */
  Ed.onChange = markDirty;
  Promise.all([api('list'), Ed.init()]).then(function (r) {
    var j = r[0];
    S.items = j.items; S.snips = j.snippets; S.routes = j.routes; S.positions = j.positions; S.globals = j.globals; S.disabled = j.disabled; S.safeParam = j.safeParam;
    renderTabs(); renderAll();
    var first = S.items.filter(function (i) { return i.slug === 'global-css'; })[0];
    if (first) openItem(first.id);
  }).catch(fail);
})();
