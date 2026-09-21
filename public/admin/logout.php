<?php
declare(strict_types=1);
require __DIR__ . '/_boot.php';
\CodeManager\Auth::logout();
header('Location: login.php');
