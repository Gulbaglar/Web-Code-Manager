<?php
declare(strict_types=1);

namespace CodeManager;

/** Starter snippets (names/descriptions come from the language files: snip.<key>.name / .desc, category snip.cat.<cat>). */
final class Snippets
{
    public static function all(): array
    {
        return [
            ['key' => 'smooth', 'cat' => 'behavior',
             'js' => "document.documentElement.style.scrollBehavior = 'smooth';"],
            ['key' => 'reveal', 'cat' => 'animation',
             'html' => '<div class="cm-reveal">Content that fades in on scroll</div>',
             'css' => ".cm-reveal{opacity:0;transform:translateY(24px);transition:opacity .7s,transform .7s}\n.cm-reveal.in{opacity:1;transform:none}",
             'js' => "var io = new IntersectionObserver(function (es) {\n  es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });\n}, { threshold: .15 });\ndocument.querySelectorAll('.cm-reveal').forEach(function (el) { io.observe(el); });\nctx.onLeave(function () { io.disconnect(); });"],
            ['key' => 'button', 'cat' => 'ui',
             'html' => '<a class="cm-btn" href="#contact">Get in touch</a>',
             'css' => ".cm-btn{display:inline-block;padding:12px 26px;border-radius:999px;background:var(--cm-accent,#4f8cff);color:#fff;font-weight:600;text-decoration:none;transition:transform .2s,filter .2s}\n.cm-btn:hover{transform:translateY(-2px);filter:brightness(1.1)}"],
            ['key' => 'modal', 'cat' => 'ui',
             'html' => "<button class=\"cm-open\">Open modal</button>\n<div class=\"cm-modal\" hidden><div class=\"cm-modal-box\"><button class=\"cm-close\" aria-label=\"Close\">×</button><h3>Title</h3><p>Modal content.</p></div></div>",
             'css' => ".cm-modal{position:fixed;inset:0;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;z-index:9999}\n.cm-modal[hidden]{display:none}\n.cm-modal-box{position:relative;background:#fff;color:#111;border-radius:14px;padding:32px;max-width:520px;width:90%}\n.cm-close{position:absolute;top:10px;right:14px;background:none;border:0;color:inherit;font-size:1.6rem;cursor:pointer}",
             'js' => "var root = ctx.el || document;\nvar m = root.querySelector('.cm-modal'), o = root.querySelector('.cm-open');\nfunction close() { m.hidden = true; }\nctx.on(o, 'click', function () { m.hidden = false; });\nctx.on(m, 'click', function (e) { if (e.target === m || e.target.classList.contains('cm-close')) close(); });\nctx.on(document, 'keydown', function (e) { if (e.key === 'Escape') close(); });"],
            ['key' => 'lightbox', 'cat' => 'media',
             'html' => '<img class="cm-zoom" src="" alt="" style="max-width:320px;cursor:zoom-in">',
             'css' => ".cm-lb{position:fixed;inset:0;background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center;z-index:9999;cursor:zoom-out}\n.cm-lb img{max-width:92vw;max-height:92vh}",
             'js' => "ctx.qsa('.cm-zoom', document).forEach(function (img) {\n  ctx.on(img, 'click', function () {\n    var d = document.createElement('div'); d.className = 'cm-lb';\n    d.innerHTML = '<img src=\"' + img.src + '\" alt=\"\">';\n    d.onclick = function () { d.remove(); };\n    document.body.appendChild(d);\n    ctx.onLeave(function () { d.remove(); });\n  });\n});"],
            ['key' => 'video', 'cat' => 'media',
             'html' => '<div class="cm-video" data-yt="dQw4w9WgXcQ"><button aria-label="Play">▶</button></div>',
             'css' => ".cm-video{position:relative;aspect-ratio:16/9;max-width:820px;background:#000 center/cover;border-radius:12px;overflow:hidden}\n.cm-video button{position:absolute;inset:0;margin:auto;width:76px;height:76px;border-radius:50%;border:0;background:var(--cm-accent,#4f8cff);color:#fff;font-size:1.8rem;cursor:pointer}\n.cm-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0}",
             'js' => "(ctx.el || document).querySelectorAll('.cm-video').forEach(function (v) {\n  var id = v.dataset.yt;\n  v.style.backgroundImage = 'url(https://i.ytimg.com/vi/' + id + '/hqdefault.jpg)';\n  ctx.on(v.querySelector('button'), 'click', function () {\n    v.innerHTML = '<iframe src=\"https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1\" allow=\"autoplay; fullscreen\" allowfullscreen></iframe>';\n  });\n});"],
            ['key' => 'beforeafter', 'cat' => 'media',
             'html' => "<div class=\"cm-ba\">\n  <img src=\"\" alt=\"After\">\n  <div class=\"cm-ba-top\"><img src=\"\" alt=\"Before\"></div>\n  <input type=\"range\" min=\"0\" max=\"100\" value=\"50\" aria-label=\"Compare\">\n</div>",
             'css' => ".cm-ba{position:relative;max-width:820px;aspect-ratio:16/10;overflow:hidden;border-radius:12px;background:#111}\n.cm-ba img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}\n.cm-ba-top{position:absolute;inset:0;width:50%;overflow:hidden;border-right:2px solid #fff}\n.cm-ba-top img{width:auto;min-width:100%;max-width:none}\n.cm-ba input{position:absolute;inset:auto 0 12px 0;width:80%;margin:auto;accent-color:var(--cm-accent,#4f8cff)}",
             'js' => "(ctx.el || document).querySelectorAll('.cm-ba').forEach(function (b) {\n  var top = b.querySelector('.cm-ba-top'), img = top.querySelector('img'), r = b.querySelector('input');\n  function fit() { img.style.width = b.clientWidth + 'px'; }\n  function set() { top.style.width = r.value + '%'; }\n  fit(); set();\n  ctx.on(r, 'input', set); ctx.on(window, 'resize', fit);\n});"],
        ];
    }
}
