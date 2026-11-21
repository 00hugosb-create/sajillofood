<?php
ini_set('display_errors', '0');
// Backend de Sajillo Food con MySQL (base de datos de OVH). Configuración en ../config.php
function out($o, $c = 200) { http_response_code($c); header('Content-Type: application/json'); echo json_encode($o); exit; }
function h($p) { return hash('sha256', 'sajillo:' . ($p ?? '')); }
function uid() { return bin2hex(random_bytes(16)); }
function q($sql, $a = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($a); return $s; }
set_exception_handler(function ($e) { out(['error' => 'Error del servidor: ' . $e->getMessage()], 500); });
if (!is_file(__DIR__ . '/../config.php')) out(['error' => 'Falta config.php'], 500);
$c = require __DIR__ . '/../config.php';
try {
  $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
  foreach ([
    "CREATE TABLE IF NOT EXISTS users (id CHAR(32) PRIMARY KEY, name VARCHAR(60) NOT NULL, role VARCHAR(5) NOT NULL, pin CHAR(64) NOT NULL)",
    "CREATE TABLE IF NOT EXISTS sessions (token CHAR(32) PRIMARY KEY, user_id CHAR(32) NOT NULL)",
    "CREATE TABLE IF NOT EXISTS meals (id CHAR(32) PRIMARY KEY, name VARCHAR(80) NOT NULL, ingredients TEXT, photo MEDIUMBLOB NULL, photo_v INT NOT NULL DEFAULT 0)",
    "CREATE TABLE IF NOT EXISTS ratings (meal_id CHAR(32), user_id CHAR(32), score TINYINT NOT NULL, PRIMARY KEY (meal_id, user_id))",
    "CREATE TABLE IF NOT EXISTS menu (week CHAR(10), slot VARCHAR(20), meal_id CHAR(32) NOT NULL, PRIMARY KEY (week, slot))",
    "CREATE TABLE IF NOT EXISTS shop (id INT AUTO_INCREMENT PRIMARY KEY, t VARCHAR(200) NOT NULL, done TINYINT NOT NULL DEFAULT 0)",
  ] as $sql) $pdo->exec($sql);
} catch (Throwable $e) { out(['error' => 'No se puede conectar a la base de datos: ' . $e->getMessage()], 500); }

$p = array_values(array_filter(explode('/', $_GET['p'] ?? '')));
$m = $_SERVER['REQUEST_METHOD'];
$b = json_decode(file_get_contents('php://input'), true) ?: [];
$a = $p[0] ?? '';

if ($a === 'photo' && $m === 'GET') {
  $r = q('SELECT photo FROM meals WHERE id=?', [$p[1] ?? ''])->fetch();
  if (!$r || !$r['photo']) { http_response_code(404); exit; }
  header('Content-Type: image/jpeg'); header('Cache-Control: public, max-age=31536000'); echo $r['photo']; exit;
}
if ($a === 'users' && $m === 'GET' && !isset($p[1])) out(q('SELECT id,name,role FROM users ORDER BY name')->fetchAll());
if ($a === 'login' && $m === 'POST') {
  $u = q('SELECT * FROM users WHERE id=?', [$b['id'] ?? ''])->fetch();
  if (!$u || $u['pin'] !== h($b['pin'] ?? '')) out(['error' => 'PIN incorrecto'], 403);
  $t = uid(); q('INSERT INTO sessions VALUES (?,?)', [$t, $u['id']]); out(['token' => $t]);
}
$me = q('SELECT u.* FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token=?', [$_SERVER['HTTP_X_TOKEN'] ?? ''])->fetch();
$first = $a === 'users' && $m === 'POST' && (int)q('SELECT COUNT(*) n FROM users')->fetch()['n'] === 0;
if (!$me && !$first) out(['error' => 'auth'], 401);
$adult = $first || $me['role'] === 'adult';

if ($a === 'data') {
  $meals = q('SELECT id,name,ingredients,(photo IS NOT NULL) hp,photo_v FROM meals ORDER BY name')->fetchAll();
  $rt = [];
  foreach (q('SELECT * FROM ratings')->fetchAll() as $r) $rt[$r['meal_id']][$r['user_id']] = (int)$r['score'];
  foreach ($meals as &$x) {
    $x['ratings'] = (object)($rt[$x['id']] ?? []);
    $x['photo'] = $x['hp'] ? "api.php?p=photo/{$x['id']}&v={$x['photo_v']}" : null;
    unset($x['hp'], $x['photo_v']);
  }
  $menu = [];
  foreach (q('SELECT * FROM menu')->fetchAll() as $r) $menu[$r['week']][$r['slot']] = $r['meal_id'];
  $shop = array_map(function ($r) { return ['t' => $r['t'], 'done' => (bool)$r['done']]; }, q('SELECT t,done FROM shop ORDER BY id')->fetchAll());
  out(['me' => ['id' => $me['id'], 'name' => $me['name'], 'role' => $me['role']],
       'users' => q('SELECT id,name,role FROM users ORDER BY name')->fetchAll(), 'meals' => $meals, 'menu' => (object)$menu, 'shop' => $shop]);
}
if ($a === 'users' && $m === 'POST') {
  if (!$adult || empty($b['name'])) out([], 403);
  $id = uid(); $role = $first ? 'adult' : (($b['role'] ?? '') === 'kid' ? 'kid' : 'adult');
  q('INSERT INTO users VALUES (?,?,?,?)', [$id, mb_substr(trim($b['name']), 0, 60), $role, h($b['pin'] ?? '')]);
  $r = [];
  if ($first) { $r['token'] = uid(); q('INSERT INTO sessions VALUES (?,?)', [$r['token'], $id]); }
  out($r);
}
if ($a === 'users' && $m === 'DELETE') {
  if (!$adult || ($p[1] ?? '') === $me['id']) out([], 403);
  foreach (['users' => 'id', 'sessions' => 'user_id', 'ratings' => 'user_id'] as $t => $col) q("DELETE FROM $t WHERE $col=?", [$p[1]]);
  out([]);
}
if ($a === 'meals' && $m === 'PUT') {
  $id = $p[1] ?? '';
  if (!q('SELECT 1 FROM meals WHERE id=?', [$id])->fetch()) { $id = uid(); q('INSERT INTO meals (id,name) VALUES (?,?)', [$id, '']); }
  q('UPDATE meals SET name=?, ingredients=? WHERE id=?', [mb_substr($b['name'] ?? 'Sin nombre', 0, 80), mb_substr($b['ingredients'] ?? '', 0, 2000), $id]);
  $ph = $b['photo'] ?? '';
  if (is_string($ph) && strpos($ph, 'data:image/') === 0)
  { $st = $pdo->prepare('UPDATE meals SET photo=?, photo_v=? WHERE id=?');
    $st->bindValue(1, base64_decode(explode(',', $ph)[1]), PDO::PARAM_LOB); $st->bindValue(2, time(), PDO::PARAM_INT); $st->bindValue(3, $id); $st->execute(); }
  out(['id' => $id]);
}
if ($a === 'meals' && $m === 'DELETE') {
  foreach (['meals' => 'id', 'ratings' => 'meal_id', 'menu' => 'meal_id'] as $t => $col) q("DELETE FROM $t WHERE $col=?", [$p[1] ?? '']);
  out([]);
}
if ($a === 'rate' && $m === 'POST') {
  $s = (int)($b['score'] ?? 0);
  if ($s >= 1 && $s <= 5) q('REPLACE INTO ratings VALUES (?,?,?)', [$p[1] ?? '', $me['id'], $s]);
  out([]);
}
if ($a === 'menu' && $m === 'PUT') {
  if (!empty($b['meal'])) q('REPLACE INTO menu VALUES (?,?,?)', [$b['week'] ?? '', $b['slot'] ?? '', $b['meal']]);
  else q('DELETE FROM menu WHERE week=? AND slot=?', [$b['week'] ?? '', $b['slot'] ?? '']);
  out([]);
}
if ($a === 'shop' && $m === 'PUT') {
  $pdo->beginTransaction(); $pdo->exec('DELETE FROM shop');
  foreach (is_array($b['items'] ?? null) ? $b['items'] : [] as $i) q('INSERT INTO shop (t,done) VALUES (?,?)', [mb_substr($i['t'] ?? '', 0, 200), !empty($i['done']) ? 1 : 0]);
  $pdo->commit(); out([]);
}
out([], 404);
