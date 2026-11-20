<?php
// Backend PHP de Sajillo Food (para alojamiento compartido OVH). Datos en ../data (bloqueado por .htaccess)
$D = __DIR__ . '/../data';
if (!is_dir($D)) { mkdir($D, 0775, true); file_put_contents($D . '/.htaccess', "Require all denied\n"); }
if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0775, true);
$F = $D . '/db.json';
$db = is_file($F) ? json_decode(file_get_contents($F), true) : ['users' => [], 'meals' => [], 'menu' => [], 'shop' => [], 'sessions' => []];
function save() { global $db, $F; file_put_contents($F, json_encode($db), LOCK_EX); }
function out($o, $c = 200) { http_response_code($c); header('Content-Type: application/json'); echo json_encode($o); exit; }
function h($p) { return hash('sha256', 'sajillo:' . ($p ?? '')); }
function uid() { return bin2hex(random_bytes(16)); }
function pub($u) { return ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']]; }
$p = array_values(array_filter(explode('/', $_GET['p'] ?? '')));
$m = $_SERVER['REQUEST_METHOD'];
$b = json_decode(file_get_contents('php://input'), true) ?: [];
$tok = $_SERVER['HTTP_X_TOKEN'] ?? '';
$me = null;
foreach ($db['users'] as $u) if (($db['sessions'][$tok] ?? null) === $u['id']) $me = $u;
$a = $p[0] ?? '';

if ($a === 'users' && $m === 'GET' && !isset($p[1])) out(array_map('pub', $db['users']));
if ($a === 'login' && $m === 'POST') {
  foreach ($db['users'] as $u) if ($u['id'] === ($b['id'] ?? '') && $u['pin'] === h($b['pin'] ?? '')) {
    $t = uid(); $db['sessions'][$t] = $u['id']; save(); out(['token' => $t]);
  }
  out(['error' => 'PIN incorrecto'], 403);
}
$first = $a === 'users' && $m === 'POST' && count($db['users']) === 0;
if (!$me && !$first) out(['error' => 'auth'], 401);
$adult = $first || $me['role'] === 'adult';

if ($a === 'data') out(['me' => pub($me), 'users' => array_map('pub', $db['users']), 'meals' => $db['meals'], 'menu' => (object)$db['menu'], 'shop' => $db['shop']]);

if ($a === 'users' && $m === 'POST') {
  if (!$adult || empty($b['name'])) out([], 403);
  $u = ['id' => uid(), 'name' => trim($b['name']), 'role' => $first ? 'adult' : (($b['role'] ?? '') === 'kid' ? 'kid' : 'adult'), 'pin' => h($b['pin'] ?? '')];
  $db['users'][] = $u; $r = [];
  if ($first) { $r['token'] = uid(); $db['sessions'][$r['token']] = $u['id']; }
  save(); out($r);
}
if ($a === 'users' && $m === 'DELETE') {
  if (!$adult || ($p[1] ?? '') === $me['id']) out([], 403);
  $db['users'] = array_values(array_filter($db['users'], fn($u) => $u['id'] !== $p[1])); save(); out([]);
}
if ($a === 'meals' && $m === 'PUT') {
  $i = null;
  foreach ($db['meals'] as $k => $x) if ($x['id'] === ($p[1] ?? '')) $i = $k;
  if ($i === null) { $db['meals'][] = ['id' => uid(), 'ratings' => []]; $i = count($db['meals']) - 1; }
  $x = &$db['meals'][$i];
  $x['name'] = mb_substr($b['name'] ?? 'Sin nombre', 0, 80);
  $x['ingredients'] = mb_substr($b['ingredients'] ?? '', 0, 2000);
  $ph = $b['photo'] ?? '';
  if (is_string($ph) && strpos($ph, 'data:image/') === 0) {
    file_put_contents(__DIR__ . '/uploads/' . $x['id'] . '.jpg', base64_decode(explode(',', $ph)[1]));
    $x['photo'] = 'uploads/' . $x['id'] . '.jpg?v=' . time();
  }
  $r = $x; unset($x); save(); out($r);
}
if ($a === 'meals' && $m === 'DELETE') {
  $db['meals'] = array_values(array_filter($db['meals'], fn($x) => $x['id'] !== $p[1]));
  foreach ($db['menu'] as $w => $slots) foreach ($slots as $s => $id) if ($id === $p[1]) unset($db['menu'][$w][$s]);
  save(); out([]);
}
if ($a === 'rate' && $m === 'POST') {
  $s = (int)($b['score'] ?? 0);
  foreach ($db['meals'] as $k => $x) if ($x['id'] === ($p[1] ?? '') && $s >= 1 && $s <= 5) { $db['meals'][$k]['ratings'][$me['id']] = $s; save(); }
  out([]);
}
if ($a === 'menu' && $m === 'PUT') {
  $w = $b['week'] ?? ''; $s = $b['slot'] ?? '';
  if (!isset($db['menu'][$w])) $db['menu'][$w] = [];
  if (!empty($b['meal'])) $db['menu'][$w][$s] = $b['meal']; else unset($db['menu'][$w][$s]);
  save(); out([]);
}
if ($a === 'shop' && $m === 'PUT') { $db['shop'] = is_array($b['items'] ?? null) ? $b['items'] : []; save(); out([]); }
out([], 404);
