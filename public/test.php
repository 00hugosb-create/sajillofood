<?php
// Página de diagnóstico: ábrela una vez y BÓRRALA después.
header('Content-Type: text/plain; charset=utf-8');
echo "PHP " . PHP_VERSION . "\n";
foreach (['pdo_mysql', 'mbstring', 'json'] as $e) echo "$e: " . (extension_loaded($e) ? 'OK' : 'FALTA') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
$f = __DIR__ . '/../config.php';
echo "config.php: " . (is_file($f) ? 'encontrado' : 'NO ENCONTRADO (debe estar en ' . realpath(__DIR__ . '/..') . ')') . "\n";
if (is_file($f)) {
  $c = require $f;
  try {
    $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Conexión a la base de datos: OK\n";
    echo "Tablas: " . (implode(', ', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)) ?: '(ninguna todavía)') . "\n";
  } catch (Throwable $e) { echo "Conexión a la base de datos: ERROR -> " . $e->getMessage() . "\n"; }
}
