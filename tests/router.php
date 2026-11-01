<?php
// Nur für den lokalen Test mit "php -S": statische Dateien direkt ausliefern.
$f = __DIR__ . '/../public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($f) && !str_ends_with($f, '.php')) return false;
require __DIR__ . '/../public/index.php';
