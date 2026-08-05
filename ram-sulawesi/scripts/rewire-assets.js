/* ============================================================================
 * rewire-assets.js — bagian kedua migrasi Tailwind
 *
 *  1. Ganti semua <link> CSS lama (bootstrap, bw-theme, all.min, pages/*-bw)
 *     dengan satu /assets/css/app.css hasil build Tailwind.
 *  2. Ganti <script src=".../bootstrap.bundle.min.js"> dengan /js/ui.js
 *     dan sisipkan /js/icons.js.
 *  3. Ubah ikon font <i class="bi bi-x"> / <i class="fas fa-x"> menjadi
 *     <svg class="icon"><use href="#i-x"></use></svg>.
 *
 *   node scripts/rewire-assets.js [--dry]
 * ========================================================================== */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DRY = process.argv.includes('--dry');

const FA_MAP = {
  'arrows-alt': 'arrows-move', 'cubes': 'boxes', 'edit': 'pencil-square',
  'envelope': 'envelope', 'exclamation-triangle': 'exclamation-triangle',
  'layer-group': 'layers', 'lock': 'lock-fill', 'plus': 'plus-lg',
  'print': 'printer', 'save': 'floppy', 'search': 'search',
  'trash': 'trash', 'trash-alt': 'trash', 'undo': 'arrow-counterclockwise',
  'users': 'people-fill',
};

// ukuran font lama -> ukuran kotak SVG
function sizeClassFromFontSize(px) {
  const n = parseFloat(px);
  if (!n) return null;
  const table = [[10,'w-2.5 h-2.5'],[12,'w-3 h-3'],[14,'w-3.5 h-3.5'],[16,'w-4 h-4'],
                 [18,'w-[18px] h-[18px]'],[20,'w-5 h-5'],[22,'w-[22px] h-[22px]'],
                 [24,'w-6 h-6'],[26,'w-[26px] h-[26px]'],[28,'w-7 h-7'],[32,'w-8 h-8'],
                 [36,'w-9 h-9'],[40,'w-10 h-10'],[48,'w-12 h-12'],[64,'w-16 h-16']];
  let best = table[0];
  for (const row of table) if (Math.abs(row[0] - n) < Math.abs(best[0] - n)) best = row;
  return best[1];
}

const DROP_CLASSES = new Set(['bi', 'fas', 'far', 'fab', 'fa', 'icon']);

function convertIcons(src) {
  return src.replace(/<i\s+class="([^"]*)"([^>]*)>\s*<\/i>/g, (full, cls, attrs) => {
    const tokens = cls.split(/\s+/).filter(Boolean);
    let name = null;
    const extra = [];

    for (const t of tokens) {
      if (DROP_CLASSES.has(t)) continue;
      let m = t.match(/^bi-(.+)$/);
      if (m && !name) { name = m[1]; continue; }
      m = t.match(/^fa-(.+)$/);
      if (m && !name && FA_MAP[m[1]]) { name = FA_MAP[m[1]]; continue; }
      if (m && !name) { continue; } // fa-* tanpa padanan: buang saja
      extra.push(t);
    }
    if (!name) return full; // bukan ikon -> biarkan

    // pindahkan style="font-size:Npx" menjadi kelas ukuran
    let rest = attrs;
    let sizeCls = null;
    rest = rest.replace(/\sstyle="([^"]*)"/g, (sm, styleVal) => {
      const fs2 = styleVal.match(/font-size:\s*([\d.]+)px/);
      if (fs2) {
        sizeCls = sizeClassFromFontSize(fs2[1]);
        const leftover = styleVal.replace(/font-size:\s*[\d.]+px;?/, '').trim();
        return leftover ? ` style="${leftover}"` : '';
      }
      return sm;
    });

    const classes = ['icon', ...(sizeCls ? [sizeCls] : []), ...extra].join(' ');
    return `<svg class="${classes}" aria-hidden="true"${rest}><use href="#i-${name}"></use></svg>`;
  });
}

const CSS_LINK = /[ \t]*<link[^>]*href="\/assets\/(?:css|font)\/(?:bootstrap\.min|all\.min|bw-theme|main\.min|custom|non-critical|bootstrap-icons|pages\/[a-z_]+-bw)\.css"[^>]*>\r?\n?/g;
const SWAL_LINK = /<link[^>]*href="\/assets\/css\/sweetalert2\.min\.css"[^>]*>/;
const BS_SCRIPT = /[ \t]*<script[^>]*src="\/assets\/js\/bootstrap\.bundle\.min\.js"[^>]*><\/script>\r?\n?/g;
const SIDEBAR_SCRIPT = /<script[^>]*src="\/js\/sidebar\.js"[^>]*><\/script>/;

function processHtml(file) {
  let src = fs.readFileSync(file, 'utf8');
  const before = src;

  const hadCss = CSS_LINK.test(src);
  CSS_LINK.lastIndex = 0;
  src = src.replace(CSS_LINK, '');

  if (hadCss) {
    const appLink = '    <link rel="stylesheet" href="/assets/css/app.css">\n';
    if (SWAL_LINK.test(src)) {
      src = src.replace(SWAL_LINK, (m) => appLink.trim() + '\n    ' + m);
    } else {
      src = src.replace(/<\/head>/, appLink + '</head>');
    }
  }

  const hadBs = BS_SCRIPT.test(src);
  BS_SCRIPT.lastIndex = 0;
  src = src.replace(BS_SCRIPT, '');

  // Sisipkan ui.js + icons.js sebelum sidebar.js (atau sebelum </body>)
  if (hadBs || /<body/.test(src)) {
    const inject = '    <script src="/js/icons.js"></script>\n    <script src="/js/ui.js"></script>\n';
    if (!/src="\/js\/ui\.js"/.test(src)) {
      if (SIDEBAR_SCRIPT.test(src)) {
        src = src.replace(SIDEBAR_SCRIPT, (m) => inject.trim().split('\n').map(s => s.trim()).join('\n    ') + '\n    ' + m);
      } else if (hadBs) {
        src = src.replace(/<\/body>/, inject + '</body>');
      }
    }
  }

  src = convertIcons(src);

  if (src !== before) {
    if (!DRY) fs.writeFileSync(file, src);
    return true;
  }
  return false;
}

function processJs(file) {
  const src = fs.readFileSync(file, 'utf8');
  const next = convertIcons(src);
  if (next !== src) {
    if (!DRY) fs.writeFileSync(file, next);
    return true;
  }
  return false;
}

let n = 0;
for (const f of fs.readdirSync(path.join(ROOT, 'renderer/pages')).filter((x) => x.endsWith('.html'))) {
  if (processHtml(path.join(ROOT, 'renderer/pages', f))) { n++; console.log('  html  ' + f); }
}
for (const f of ['sidebar.js', 'form-nav.js', 'auto-serial-connect.js']) {
  const p = path.join(ROOT, 'renderer/js', f);
  if (fs.existsSync(p) && processJs(p)) { n++; console.log('  js    ' + f); }
}
console.log((DRY ? '[dry-run] ' : '') + 'Total file diubah: ' + n);
