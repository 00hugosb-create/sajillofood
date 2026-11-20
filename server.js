import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const PORT = process.env.PORT || 3000;
const DATA = process.env.DATA_DIR || './data';
const PUB = path.join(import.meta.dirname, 'public');
fs.mkdirSync(DATA + '/uploads', { recursive: true });
const FILE = DATA + '/db.json';
const db = fs.existsSync(FILE) ? JSON.parse(fs.readFileSync(FILE)) : { users: [], meals: [], menu: {}, shop: [], sessions: {} };
const save = () => fs.writeFileSync(FILE, JSON.stringify(db));
const hash = p => crypto.createHash('sha256').update('sajillo:' + (p || '')).digest('hex');
const MIME = { '.html': 'text/html; charset=utf-8', '.png': 'image/png', '.jpg': 'image/jpeg' };
const pub = u => ({ id: u.id, name: u.name, role: u.role });
const send = (res, code, obj) => { res.writeHead(code, { 'content-type': 'application/json' }); res.end(JSON.stringify(obj)); };
const readBody = req => new Promise(ok => {
  let b = '';
  req.on('data', d => { b += d; if (b.length > 8e6) req.destroy(); });
  req.on('end', () => { try { ok(b ? JSON.parse(b) : {}); } catch { ok({}); } });
});

async function api(req, res, url) {
  const m = req.method, p = (url.pathname === '/api.php' ? (url.searchParams.get('p') || '') : url.pathname.slice(4)).split('/').filter(Boolean), b = await readBody(req);
  const me = db.users.find(u => u.id === db.sessions[req.headers['x-token']]);
  if (p[0] === 'users' && m === 'GET' && !p[1]) return send(res, 200, db.users.map(pub));
  if (p[0] === 'login' && m === 'POST') {
    const u = db.users.find(x => x.id === b.id);
    if (!u || u.pin !== hash(b.pin)) return send(res, 403, { error: 'PIN incorrecto' });
    const t = crypto.randomUUID(); db.sessions[t] = u.id; save();
    return send(res, 200, { token: t });
  }
  const first = p[0] === 'users' && m === 'POST' && db.users.length === 0;
  if (!me && !first) return send(res, 401, { error: 'auth' });
  const adult = first || me.role === 'adult';
  if (p[0] === 'data') return send(res, 200, { me: pub(me), users: db.users.map(pub), meals: db.meals, menu: db.menu, shop: db.shop });
  if (p[0] === 'users' && m === 'POST') {
    if (!adult || !b.name) return send(res, 403, {});
    const u = { id: crypto.randomUUID(), name: b.name.trim(), role: first ? 'adult' : b.role === 'kid' ? 'kid' : 'adult', pin: hash(b.pin) };
    db.users.push(u);
    const out = {};
    if (first) { out.token = crypto.randomUUID(); db.sessions[out.token] = u.id; }
    save(); return send(res, 200, out);
  }
  if (p[0] === 'users' && m === 'DELETE') {
    if (!adult || p[1] === me.id) return send(res, 403, {});
    db.users = db.users.filter(u => u.id !== p[1]); save(); return send(res, 200, {});
  }
  if (p[0] === 'meals' && m === 'PUT') {
    let meal = db.meals.find(x => x.id === p[1]);
    if (!meal) { meal = { id: crypto.randomUUID(), ratings: {} }; db.meals.push(meal); }
    meal.name = String(b.name || 'Sin nombre').slice(0, 80);
    meal.ingredients = String(b.ingredients || '').slice(0, 2000);
    if (b.photo?.startsWith('data:image/')) {
      fs.writeFileSync(`${DATA}/uploads/${meal.id}.jpg`, Buffer.from(b.photo.split(',')[1], 'base64'));
      meal.photo = `/uploads/${meal.id}.jpg?v=${Date.now()}`;
    }
    save(); return send(res, 200, meal);
  }
  if (p[0] === 'meals' && m === 'DELETE') {
    db.meals = db.meals.filter(x => x.id !== p[1]);
    for (const w of Object.values(db.menu)) for (const s in w) if (w[s] === p[1]) delete w[s];
    save(); return send(res, 200, {});
  }
  if (p[0] === 'rate' && m === 'POST') {
    const meal = db.meals.find(x => x.id === p[1]);
    if (meal && b.score >= 1 && b.score <= 5) { meal.ratings[me.id] = b.score; save(); }
    return send(res, 200, {});
  }
  if (p[0] === 'menu' && m === 'PUT') {
    db.menu[b.week] ||= {};
    if (b.meal) db.menu[b.week][b.slot] = b.meal; else delete db.menu[b.week][b.slot];
    save(); return send(res, 200, {});
  }
  if (p[0] === 'shop' && m === 'PUT') { db.shop = Array.isArray(b.items) ? b.items : []; save(); return send(res, 200, {}); }
  send(res, 404, {});
}

http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://x');
  if (url.pathname === '/api.php') return api(req, res, url).catch(() => send(res, 500, {}));
  let f;
  if (url.pathname.startsWith('/uploads/')) f = path.join(DATA, 'uploads', path.basename(url.pathname));
  else f = path.join(PUB, url.pathname === '/' || url.pathname === '/public/' ? 'index.html' : path.basename(url.pathname));
  fs.readFile(f, (e, d) => {
    if (e) { res.writeHead(404); return res.end('No encontrado'); }
    res.writeHead(200, { 'content-type': MIME[path.extname(f)] || 'application/octet-stream' });
    res.end(d);
  });
}).listen(PORT, () => console.log('Sajillo Food en http://localhost:' + PORT));
