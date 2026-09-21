/* Code Manager runtime — used by your site AND by the admin sandbox preview (so preview == production).
   Every custom piece runs inside try/catch: an error in custom code never breaks your site.
   Page CSS/JS is removed/cleaned up when you leave the page (no leaking between pages of a single-page app).

   Multi-page site : Frontend::footer() embeds window.CodeManagerConfig (with this page's bundle) → applied automatically.
   Single-page app : on each route change fetch the bundle from serve.php?route=…&sub=…&v=<rev> and call
                       CodeManagerRuntime.apply(bundle, { route, sub, lang, root: <element>, slots: CodeManagerConfig.slots })
                     (call CodeManagerRuntime.leave() first; apply() also does it for you). */
(function () {
  'use strict';
  var run = null;

  function warn(label, e) { try { console.error('[Code Manager] ' + label + ':', e); } catch (x) {} }

  function style(r, css, label) {
    if (!css || !css.trim()) return;
    var s = document.createElement('style');
    s.setAttribute('data-cm', label);
    s.textContent = css;
    document.head.appendChild(s);
    r.nodes.push(s);
  }

  function activate(root) {   // innerHTML does not run <script>; recreate them so they execute
    root.querySelectorAll('script').forEach(function (old) {
      var s = document.createElement('script');
      for (var i = 0; i < old.attributes.length; i++) s.setAttribute(old.attributes[i].name, old.attributes[i].value);
      s.textContent = old.textContent;
      old.parentNode.replaceChild(s, old);
    });
  }

  function wrapper(html, cls, attrs) {
    var w = document.createElement('div');
    w.className = cls;
    for (var k in attrs) w.setAttribute(k, attrs[k]);
    w.innerHTML = html;
    return w;
  }

  function makeCtx(r, el, label) {
    var env = r.env;
    return {
      route: env.route, sub: env.sub || '', lang: env.lang || '', root: env.root, el: el || null, label: label,
      qs: function (sel, base) { return (base || el || env.root).querySelector(sel); },
      qsa: function (sel, base) { return Array.prototype.slice.call((base || el || env.root).querySelectorAll(sel)); },
      on: function (target, type, fn, opts) {
        target.addEventListener(type, fn, opts);
        r.cleanups.push(function () { target.removeEventListener(type, fn, opts); });
        return fn;
      },
      onLeave: function (fn) { r.cleanups.push(fn); },
      setTimeout: function (fn, ms) { var t = setTimeout(fn, ms); r.cleanups.push(function () { clearTimeout(t); }); return t; },
      setInterval: function (fn, ms) { var t = setInterval(fn, ms); r.cleanups.push(function () { clearInterval(t); }); return t; }
    };
  }

  function exec(r, code, el, label) {
    if (!code || !code.trim()) return;
    try {
      var f = new Function('ctx', code + '\n//# sourceURL=cm/' + label + '.js');
      f.call(el || window, makeCtx(r, el, label));
    } catch (e) { warn(label, e); }
  }

  function place(r, slot, nodes) {
    var d = (r.env.slots || {})[slot];
    var ref = d ? document.querySelector(d[0]) : null;
    if (!ref) { try { console.warn('[Code Manager] slot not found on this page: ' + slot); } catch (x) {} return false; }
    var pos = d[1], anchor = ref, i;
    if (pos === 'afterbegin') { for (i = nodes.length - 1; i >= 0; i--) ref.insertAdjacentElement('afterbegin', nodes[i]); }
    else if (pos === 'afterend') { for (i = 0; i < nodes.length; i++) { anchor.insertAdjacentElement('afterend', nodes[i]); anchor = nodes[i]; } }
    else { for (i = 0; i < nodes.length; i++) ref.insertAdjacentElement(pos, nodes[i]); }
    nodes.forEach(function (n) { r.nodes.push(n); });
    return true;
  }

  function leave() {
    if (!run) return;
    var r = run; run = null;
    for (var i = r.cleanups.length - 1; i >= 0; i--) { try { r.cleanups[i](); } catch (e) { warn('leave', e); } }
    r.nodes.forEach(function (n) { if (n.parentNode) n.parentNode.removeChild(n); });
  }

  function apply(b, env) {
    leave();
    if (typeof env.root === 'string') env.root = document.querySelector(env.root) || document.body;
    var r = run = { env: env, cleanups: [], nodes: [] };
    var comps = b.components || [], pages = b.pages || [], secs = b.sections || [];

    comps.forEach(function (c) { style(r, c.css, 'component:' + c.slug); });
    pages.forEach(function (p) { style(r, p.css, 'page:' + p.id); });
    secs.forEach(function (s) { style(r, s.css, 'section:' + s.id); });

    var wrappers = [];
    pages.forEach(function (p) {
      if (!p.html || !p.html.trim()) { if (p.js) wrappers.push([p, null]); return; }
      var w = wrapper(p.html, 'cm-page', { 'data-cm-page': String(p.id) });
      (env.slots && env.slots.bottom) ? place(r, 'bottom', [w]) : env.root.appendChild(w);
      r.nodes.push(w); activate(w); wrappers.push([p, w]);
    });

    var bySlot = {}, order = [];
    secs.forEach(function (s) {
      if (!s.html || !s.html.trim()) { if (s.js) wrappers.push([s, null]); return; }
      var w = wrapper(s.html, 'cm-section', { 'data-cm-section': String(s.id) });
      (bySlot[s.pos] = bySlot[s.pos] || []).push(w);
      if (order.indexOf(s.pos) < 0) order.push(s.pos);
      wrappers.push([s, w]);
    });
    order.forEach(function (slot) { if (place(r, slot, bySlot[slot])) bySlot[slot].forEach(activate); });

    /* JS order: components (once per instance) → sections → pages */
    comps.forEach(function (c) {
      if (!c.js) return;
      Array.prototype.slice.call(document.querySelectorAll('[data-cm-component="' + c.slug + '"]')).forEach(function (el) { exec(r, c.js, el, 'component-' + c.slug); });
    });
    wrappers.forEach(function (x) { if (x[0].js && 'pos' in x[0]) exec(r, x[0].js, x[1], 'section-' + x[0].id); });
    wrappers.forEach(function (x) { if (x[0].js && !('pos' in x[0])) exec(r, x[0].js, x[1], 'page-' + x[0].id); });
  }

  /* Admin preview only: applies the GLOBAL code (in production the server emits it as tags). */
  function applyGlobal(g) {
    if (g.css && g.css.trim()) { var s = document.createElement('style'); s.textContent = g.css; document.head.appendChild(s); }
    if (g.head && g.head.trim()) { var h = document.createElement('div'); h.innerHTML = g.head; activate(h); Array.prototype.slice.call(h.childNodes).forEach(function (n) { document.head.appendChild(n); }); }
    if (g.body && g.body.trim()) { var b = document.createElement('div'); b.innerHTML = g.body; activate(b); Array.prototype.slice.call(b.childNodes).forEach(function (n) { document.body.appendChild(n); }); }
    if (g.js && g.js.trim()) { var sc = document.createElement('script'); sc.textContent = g.js + '\n//# sourceURL=cm/global.js'; document.body.appendChild(sc); }
  }

  window.CodeManagerRuntime = { apply: apply, leave: leave, applyGlobal: applyGlobal };

  /* Multi-page auto-start */
  var cfg = window.CodeManagerConfig;
  if (cfg && cfg.bundle) {
    var start = function () { try { apply(cfg.bundle, { route: cfg.route, sub: cfg.sub, lang: cfg.lang, root: cfg.root, slots: cfg.slots }); } catch (e) { warn('start', e); } };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
  }
})();
