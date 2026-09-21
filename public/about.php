<?php
/** Demo second page: proves that page-specific code does not leak into other pages. */
require dirname(__DIR__) . '/src/bootstrap.php';
use CodeManager\Frontend;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>About — Demo site</title>
<link rel="stylesheet" href="/assets/demo.css">
<?php Frontend::head(); ?>
</head>
<body>
<header><span>Demo site</span><a href="/">Home</a><a href="/about.php">About</a><a href="/admin/">Admin →</a></header>
<main>
  <div class="hero"><h1>About</h1><p>A second page.</p></div>
  <section id="features"><h2>Team</h2><p>Sample content.</p></section>
</main>
<footer>© Demo site</footer>
<?php Frontend::footer('about'); ?>
</body>
</html>
