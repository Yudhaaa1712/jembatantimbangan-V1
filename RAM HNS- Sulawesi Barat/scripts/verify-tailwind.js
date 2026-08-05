/* ============================================================================
 * verify-tailwind.js — pemeriksaan setelah migrasi
 *
 * Mengecek:
 *   1. Setiap kelas di markup benar-benar ada di CSS hasil build
 *      (kelas yang tidak tergenerate = aturan yang diam-diam hilang).
 *   2. Setiap <use href="#i-..."> punya simbol di icons.js.
 *   3. Semua href/src ke aset lokal benar-benar ada filenya.
 *   4. Tidak ada sisa kelas / file / skrip Bootstrap.
 *   5. Setiap id yang dipakai getElementById ada di halaman terkait.
 *
 *   node scripts/verify-tailwind.js
 * ========================================================================== */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PAGES = path.join(ROOT, 'renderer/pages');
const RENDERER = path.join(ROOT, 'renderer');

let problems = 0;
const warn = (msg) => { console.log('  ✗ ' + msg); problems++; };
const ok = (msg) => console.log('  ✓ ' + msg);

const css = {
  app: fs.readFileSync(path.join(RENDERER, 'assets/css/app.css'), 'utf8'),
  print: fs.readFileSync(path.join(RENDERER, 'assets/css/print.css'), 'utf8'),
  swal: fs.readFileSync(path.join(RENDERER, 'assets/css/sweetalert2.min.css'), 'utf8'),
};

const escapeClass = (c) => c.replace(/[.:/[\]()#%,!+*~'"^$|?<>{}=&@`\\]/g, (ch) => '\\' + ch);

// kelas yang memang tidak perlu ada di CSS (dipakai sebagai penanda oleh JS)
const JS_ONLY = new Set([
  'active', 'show', 'showing', 'connected', 'selected', 'disabled', 'no-print',
  'd-print-none', 'modal-open', 'row-debit', 'saldo-awal-row', 'format-rupiah',
  'currency-format', 'rupiah-input', 'swal2-input', 'swal2-select', 'sisa',
  'lbl', 'val', 'line', 'title', 'subtitle', 'debit', 'kredit', 'saldo',
  'total-row', 'saldo-awal', 'right', 'num', 'center', 'summary', 'sign',
  'meta', 'sheet', 'notes', 'unit', 'spinner', 'loading',
]);

/* ---- 0. sintaks setiap file JS ------------------------------------------ */
/* Ini yang dulu terlewat: sidebar.js rusak sintaksnya dan sidebar tidak
   pernah tampil di halaman mana pun, tapi tidak ada pemeriksaan yang
   menangkapnya. */
console.log('\n0. Sintaks file JavaScript');
{
  const vm = require('vm');
  const jsFiles = fs.readdirSync(path.join(RENDERER, 'js')).filter((f) => f.endsWith('.js'));
  for (const f of jsFiles) {
    try {
      new vm.Script(fs.readFileSync(path.join(RENDERER, 'js', f), 'utf8'));
      ok('js/' + f);
    } catch (e) {
      warn(`js/${f} GAGAL PARSE — ${e.message}`);
    }
  }
  for (const f of fs.readdirSync(PAGES).filter((x) => x.endsWith('.html'))) {
    const src = fs.readFileSync(path.join(PAGES, f), 'utf8');
    let i = 0;
    for (const m of src.matchAll(/<script(?![^>]*\ssrc=)[^>]*>([\s\S]*?)<\/script>/g)) {
      i++;
      if (!m[1].trim()) continue;
      try { new vm.Script(m[1]); }
      catch (e) { warn(`${f} blok skrip ${i} GAGAL PARSE — ${e.message}`); }
    }
  }
}

/* ---- 1. kelas markup vs CSS hasil build --------------------------------- */
console.log('\n1. Kelas di markup vs CSS hasil build');
const pageFiles = fs.readdirSync(PAGES).filter((f) => f.endsWith('.html'));
const missing = new Map();

// sidebar.js ikut diperiksa: kelas sidebar hanya muncul di situ
const scanTargets = [
  ...pageFiles.map((f) => ({ name: f, file: path.join(PAGES, f) })),
  { name: 'js/sidebar.js', file: path.join(RENDERER, 'js/sidebar.js') },
];

for (const { name: f, file } of scanTargets) {
  const src = fs.readFileSync(file, 'utf8');
  const isPrintDoc = ['print_ticket.html', 'print_hutang.html', 'surat_jalan.html'].includes(f);
  const sheet = (isPrintDoc ? css.print : css.app) + css.swal;

  for (const m of src.matchAll(/class="([^"]*)"/g)) {
    for (const tok of m[1].split(/\s+/).filter(Boolean)) {
      // lewati potongan ekspresi template literal (mis. ${a > 0 ? 'x' : 'y'})
      if (tok.includes('${') || /[?:'"`=<>&|!()]/.test(tok) || /^[\d.]+$/.test(tok)) continue;
      if (JS_ONLY.has(tok)) continue;
      if (sheet.includes('.' + escapeClass(tok))) continue;
      const key = tok;
      if (!missing.has(key)) missing.set(key, new Set());
      missing.get(key).add(f);
    }
  }
}
if (missing.size === 0) ok('semua kelas punya aturan CSS');
else for (const [k, v] of [...missing].sort()) warn(`kelas tanpa CSS: ${k.padEnd(30)} (${[...v].join(', ')})`);

/* ---- 1b. nilai arbitrer yang kehilangan spasi --------------------------- */
/* Tailwind tetap membangkitkan aturan untuk nilai arbitrer yang rusak, jadi
   pemeriksaan "kelas ada di CSS" di atas TIDAK menangkapnya. Contoh nyata:
   `1fr 1fr 1fr` sempat menjadi `[grid-template-columns:1fr1fr1fr]` sehingga
   grid tiga kolom Timbangan 2 runtuh jadi satu kolom. */
console.log('\n1b. Nilai arbitrer (cek spasi yang hilang)');
{
  const SUSPECT = /\[(grid-template-columns|grid-template-rows|box-shadow|transform|transition|inset|margin|padding|border|font|background|flex):([^\]]*)\]/;
  let hits = 0;
  for (const f of pageFiles) {
    const src = fs.readFileSync(path.join(PAGES, f), 'utf8');
    for (const m of src.matchAll(/class="([^"]*)"/g)) {
      for (const tok of m[1].split(/\s+/)) {
        const s = SUSPECT.exec(tok);
        if (!s) continue;
        // dua nilai berdempetan tanpa pemisah, mis. "1fr1fr" / "1frauto" / "2pxsolid"
        if (/(?:fr|px|rem|em|%|vh|vw)(?=[a-z0-9])/i.test(s[2].replace(/,/g, ' '))) {
          warn(`${f}: nilai arbitrer kemungkinan kehilangan spasi -> ${tok}`);
          hits++;
        }
      }
    }
  }
  if (!hits) ok('tidak ada nilai arbitrer yang mencurigakan');
}

/* ---- 2. ikon ------------------------------------------------------------ */
console.log('\n2. Referensi ikon');
const iconsJs = fs.readFileSync(path.join(RENDERER, 'js/icons.js'), 'utf8');
const haveIcons = new Set([...iconsJs.matchAll(/id=\\"i-([a-z0-9-]+)\\"/g)].map((m) => m[1]));
const usedIcons = new Set();
for (const f of [...pageFiles.map((x) => path.join(PAGES, x)),
                 ...fs.readdirSync(path.join(RENDERER, 'js')).map((x) => path.join(RENDERER, 'js', x))]) {
  const src = fs.readFileSync(f, 'utf8');
  for (const m of src.matchAll(/href="#i-([a-z0-9-]+)"/g)) usedIcons.add(m[1]);
}
const missingIcons = [...usedIcons].filter((i) => !haveIcons.has(i));
if (missingIcons.length === 0) ok(`${usedIcons.size} ikon dipakai, semuanya ada di sprite (${haveIcons.size} tersedia)`);
else missingIcons.forEach((i) => warn('ikon tidak ada di sprite: ' + i));

/* ---- 3. aset yang direferensikan ---------------------------------------- */
console.log('\n3. File aset yang direferensikan');
const badAssets = new Set();
for (const f of pageFiles) {
  const src = fs.readFileSync(path.join(PAGES, f), 'utf8');
  for (const m of src.matchAll(/(?:href|src)="(\/(?:assets|js)\/[^"?]+)/g)) {
    const p = path.join(RENDERER, m[1]);
    if (!fs.existsSync(p)) badAssets.add(m[1] + '  <- ' + f);
  }
}
if (badAssets.size === 0) ok('semua aset lokal ditemukan');
else badAssets.forEach((a) => warn('aset tidak ada: ' + a));

/* ---- 4. sisa Bootstrap -------------------------------------------------- */
console.log('\n4. Sisa Bootstrap');
const bsFiles = ['assets/css/bootstrap.min.css', 'assets/js/bootstrap.bundle.min.js',
                 'assets/css/bw-theme.css', 'assets/css/pages'];
const stillThere = bsFiles.filter((p) => fs.existsSync(path.join(RENDERER, p)));
stillThere.length ? stillThere.forEach((p) => warn('file lama masih ada: ' + p)) : ok('tidak ada file CSS/JS Bootstrap tersisa');

const BS_ONLY = /\b(fw-bold|fw-semibold|fs-[1-6]|d-flex|d-none|d-block|text-muted|text-dark|text-end|text-start|bg-light|bg-dark|bg-(primary|secondary|success|danger|warning|info)\b|me-[0-5]|ms-[0-5]|w-100|w-50|h-100|justify-content-|align-items-|text-uppercase|float-(start|end)|rounded-pill|col-md-\d|flex-column|flex-fill)\b/;
let bsClassHits = 0;
for (const f of [...pageFiles.map((x) => path.join(PAGES, x)), path.join(RENDERER, 'js/sidebar.js')]) {
  const src = fs.readFileSync(f, 'utf8');
  src.split('\n').forEach((line, i) => {
    if (BS_ONLY.test(line)) { warn(`kelas Bootstrap tersisa ${path.basename(f)}:${i + 1}  ${line.trim().slice(0, 90)}`); bsClassHits++; }
  });
}
if (!bsClassHits) ok('tidak ada kelas khas Bootstrap tersisa di markup');

/* ---- 5. id yang dipakai JS --------------------------------------------- */
console.log('\n5. id yang dirujuk getElementById');
let idProblems = 0;
for (const f of pageFiles) {
  const src = fs.readFileSync(path.join(PAGES, f), 'utf8');
  const declared = new Set([...src.matchAll(/\sid="([^"${]+)"/g)].map((m) => m[1]));
  const used = new Set([...src.matchAll(/getElementById\('([^'${]+)'\)/g)].map((m) => m[1]));
  const dynamic = /getElementById\(`|getElementById\([a-zA-Z]/.test(src);
  for (const id of used) {
    if (!declared.has(id) && !dynamic) { warn(`${f}: id "${id}" dirujuk tapi tidak ada di markup`); idProblems++; }
  }
}
if (!idProblems) ok('semua id statis yang dirujuk JS ada di markup');

/* ---- 6. kontras: teks gelap di dalam wadah gelap ------------------------ */
/* Beberapa kartu memang berlatar gelap (mis. "Pengaturan Field Timbangan"
   dan modal port serial). Kalau ada komponen yang mematok warna teks gelap
   di dalamnya, teksnya jadi tidak terbaca — persis yang terjadi pada label
   toggle di halaman Pengaturan. */
console.log('\n6. Kontras teks pada wadah gelap');
{
  const DARK = /\b(bg-ink|bg-ink-600|bg-slate-[789]00|bg-gray-[789]00|bg-black|display-panel|weight-display-card|saldo-utama|page-header-box)\b/;
  const LIGHT = /\b(card-header|bg-bw-\d+|bg-white|bg-slate-[12]00|bg-gray-[12]00)\b/;
  const DARKTEXT = /\b(text-ink|text-ink-500|text-ink-600|text-black|btn-secondary)\b/;
  const VOID = new Set(['br', 'hr', 'img', 'input', 'meta', 'link', 'source', 'use', 'path', 'col']);
  let hits = 0;

  for (const f of pageFiles) {
    const src = fs.readFileSync(path.join(PAGES, f), 'utf8');
    const stack = []; // { tag, dark, light }
    const tagRe = /<(\/?)([a-zA-Z][\w-]*)([^>]*?)(\/?)>/g;
    let m;
    while ((m = tagRe.exec(src))) {
      const [, closing, tag, attrs, selfClose] = m;
      const t = tag.toLowerCase();
      if (closing) {
        for (let i = stack.length - 1; i >= 0; i--) {
          if (stack[i].tag === t) { stack.length = i; break; }
        }
        continue;
      }
      const cls = (attrs.match(/\sclass="([^"]*)"/) || [, ''])[1];
      const insideDark = stack.some((s) => s.dark);
      const insideLight = stack.some((s) => s.light);

      if (insideDark && !insideLight && !LIGHT.test(cls) && DARKTEXT.test(cls)) {
        const line = src.slice(0, m.index).split('\n').length;
        warn(`${f}:${line} <${t} class="${cls}"> teks gelap di dalam wadah gelap`);
        hits++;
      }
      if (!selfClose && !VOID.has(t)) {
        stack.push({ tag: t, dark: DARK.test(cls), light: LIGHT.test(cls) });
      }
    }
  }
  if (!hits) ok('tidak ada teks gelap di atas latar gelap');
}

console.log('\n' + (problems === 0 ? '✅ Semua pemeriksaan lolos.' : `⚠️  ${problems} temuan perlu dicek.`));
process.exit(problems ? 1 : 0);
