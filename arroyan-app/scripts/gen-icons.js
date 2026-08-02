const fs = require('fs');
const path = require('path');

const ROOT = process.env.APP_ROOT || path.resolve(__dirname, '..');
const APP = path.join(ROOT, 'renderer');
const ICONS = process.env.BI_ICONS || path.join(ROOT, 'node_modules/bootstrap-icons/icons');

const FA_MAP = {
  'arrows-alt': 'arrows-move', 'cubes': 'boxes', 'edit': 'pencil-square',
  'envelope': 'envelope', 'exclamation-triangle': 'exclamation-triangle',
  'layer-group': 'layers', 'lock': 'lock-fill', 'plus': 'plus-lg',
  'print': 'printer', 'save': 'floppy', 'search': 'search',
  'trash': 'trash', 'trash-alt': 'trash', 'undo': 'arrow-counterclockwise',
  'users': 'people-fill',
};

function walk(dir, out = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (/\.(html|js)$/.test(e.name) && !/\.min\.js$/.test(e.name) && e.name !== 'icons.js') out.push(p);
  }
  return out;
}

const files = walk(path.join(APP, 'pages')).concat(walk(path.join(APP, 'js')));
const names = new Set();
for (const f of files) {
  const src = fs.readFileSync(f, 'utf8');
  for (const m of src.matchAll(/\bbi-([a-z0-9-]+)/g)) names.add(m[1]);
  for (const m of src.matchAll(/#i-([a-z0-9-]+)/g)) names.add(m[1]);
  for (const m of src.matchAll(/\bfa-([a-z0-9-]+)/g)) if (FA_MAP[m[1]]) names.add(FA_MAP[m[1]]);
}
for (const v of Object.values(FA_MAP)) names.add(v);

const lines = [];
const resolved = [];
for (const n of [...names].sort()) {
  const file = path.join(ICONS, n + '.svg');
  if (!fs.existsSync(file)) { console.log('LEWAT (tidak ada di paket): ' + n); continue; }
  const svg = fs.readFileSync(file, 'utf8');
  const inner = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '').replace(/\s+/g, ' ').trim();
  lines.push('    ' + JSON.stringify(`<symbol id="i-${n}" viewBox="0 0 16 16">${inner}</symbol>`) + ',');
  resolved.push(n);
}

const out = `/* ============================================================================
 * icons.js — sprite ikon SVG (DIBANGKITKAN OTOMATIS, jangan diedit manual)
 *
 * Menggantikan font Bootstrap Icons & Font Awesome, yang file fontnya tidak
 * pernah ada di aplikasi ini sehingga semua ikon dulu tidak tampil.
 *
 * Pakai di markup:
 *   <svg class="icon"><use href="#i-truck"></use></svg>
 *
 * Regenerasi:  npm run icons
 * Jumlah ikon: ${resolved.length}
 * ========================================================================== */
(function () {
  var SYMBOLS = [
${lines.join('\n')}
  ];

  function inject() {
    if (document.getElementById('__icon-sprite')) return;
    var holder = document.createElement('div');
    holder.id = '__icon-sprite';
    holder.setAttribute('aria-hidden', 'true');
    holder.style.display = 'none';
    holder.innerHTML =
      '<svg xmlns="http://www.w3.org/2000/svg">' + SYMBOLS.join('') + '</svg>';
    document.body.insertBefore(holder, document.body.firstChild);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
`;

fs.writeFileSync(path.join(APP, 'js/icons.js'), out);
console.log('Ikon dibundel: ' + resolved.length);
