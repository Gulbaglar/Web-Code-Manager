<?php
/** Demo home page — shows how little a host site needs: two calls (head + footer). */
require dirname(__DIR__) . '/src/bootstrap.php';
use CodeManager\Frontend;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Demo site</title>
<link rel="stylesheet" href="/assets/demo.css">
<?php Frontend::head(); ?>
</head>
<body>
<header><span>Demo site</span><a href="/">Home</a><a href="/about.php">About</a><a href="/admin/">Admin →</a></header>
<main>
  <div class="hero"><h1>Welcome</h1><p>This page is rendered by plain PHP. Open <a href="/admin/">/admin/</a>, write some HTML/CSS/JS, preview and publish it — it appears here.</p></div>
  <section id="features"><h2>Features</h2><p>Sample section.</p></section>
  <section id="teaser"><h2>Learn more</h2><p><a href="/about.php">About us</a></p></section>
</main>
<footer>© Demo site</footer>
<?php Frontend::footer('home'); ?>
</body>
</html>
