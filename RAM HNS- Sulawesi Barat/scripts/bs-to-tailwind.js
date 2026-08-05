/* ============================================================================
 * bs-to-tailwind.js — konversi kelas utilitas Bootstrap 5 -> Tailwind
 *
 * Skrip ini dipakai sekali saat migrasi. Disimpan supaya prosesnya bisa
 * ditelusuri ulang / dijalankan lagi bila ada file lama yang tertinggal.
 *
 *   node scripts/bs-to-tailwind.js --dry     (lihat perubahan, tanpa menulis)
 *   node scripts/bs-to-tailwind.js           (tulis perubahan)
 * ========================================================================== */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DRY = process.argv.includes('--dry');

/* -- skala jarak: Bootstrap (0,.25,.5,1,1.5,3rem) -> Tailwind ------------- */
const SPACE = { '0': '0', '1': '1', '2': '2', '3': '4', '4': '6', '5': '12', auto: 'auto' };
const SIDES = {
  m: 'm', mt: 'mt', mb: 'mb', ms: 'ml', me: 'mr', mx: 'mx', my: 'my',
  p: 'p', pt: 'pt', pb: 'pb', ps: 'pl', pe: 'pr', px: 'px', py: 'py',
};

const MAP = {
  /* tampilan */
  'd-flex': 'flex', 'd-inline-flex': 'inline-flex', 'd-block': 'block',
  'd-inline-block': 'inline-block', 'd-inline': 'inline', 'd-none': 'hidden',
  'd-grid': 'grid', 'd-table': 'table',
  'd-print-none': 'print:hidden', 'd-print-block': 'hidden print:block',
  'd-md-block': 'md:block', 'd-md-none': 'md:hidden', 'd-sm-none': 'sm:hidden',

  /* flexbox */
  'justify-content-start': 'justify-start', 'justify-content-end': 'justify-end',
  'justify-content-center': 'justify-center', 'justify-content-between': 'justify-between',
  'justify-content-around': 'justify-around', 'justify-content-evenly': 'justify-evenly',
  'align-items-start': 'items-start', 'align-items-end': 'items-end',
  'align-items-center': 'items-center', 'align-items-baseline': 'items-baseline',
  'align-items-stretch': 'items-stretch',
  'align-self-start': 'self-start', 'align-self-end': 'self-end',
  'align-self-center': 'self-center',
  'flex-row': 'flex-row', 'flex-column': 'flex-col', 'flex-wrap': 'flex-wrap',
  'flex-nowrap': 'flex-nowrap', 'flex-fill': 'flex-1', 'flex-grow-1': 'grow',
  'flex-shrink-0': 'shrink-0',

  /* teks */
  'text-start': 'text-left', 'text-end': 'text-right', 'text-center': 'text-center',
  'text-uppercase': 'uppercase', 'text-lowercase': 'lowercase',
  'text-capitalize': 'capitalize', 'text-truncate': 'truncate',
  'text-nowrap': 'whitespace-nowrap', 'text-wrap': 'whitespace-normal',
  'text-break': 'break-words', 'text-decoration-none': 'no-underline',
  'text-muted': 'text-ink-500', 'text-dark': 'text-ink', 'text-black': 'text-black',
  'text-white': 'text-white', 'text-primary': 'text-blue-600',
  'text-secondary': 'text-ink-600', 'text-success': 'text-green-600',
  'text-danger': 'text-red-600', 'text-warning': 'text-amber-500',
  'text-info': 'text-sky-600', 'text-light': 'text-slate-300',

  /* tipografi */
  'fw-bold': 'font-bold', 'fw-bolder': 'font-extrabold', 'fw-semibold': 'font-semibold',
  'fw-medium': 'font-medium', 'fw-normal': 'font-normal', 'fw-light': 'font-light',
  'fst-italic': 'italic', 'font-monospace': 'font-mono',
  'fs-1': 'text-[2.5rem]', 'fs-2': 'text-[2rem]', 'fs-3': 'text-[1.75rem]',
  'fs-4': 'text-2xl', 'fs-5': 'text-xl', 'fs-6': 'text-base',
  'lead': 'text-lg', 'small': 'text-sm',
  'display-1': 'text-[5rem] font-light leading-none',
  'display-3': 'text-[4rem] font-light leading-none',
  'lh-1': 'leading-none', 'lh-sm': 'leading-tight', 'lh-base': 'leading-normal',

  /* latar */
  'bg-primary': 'bg-blue-600', 'bg-secondary': 'bg-ink-600', 'bg-success': 'bg-green-600',
  'bg-danger': 'bg-red-600', 'bg-warning': 'bg-amber-400', 'bg-info': 'bg-sky-500',
  'bg-light': 'bg-bw-100', 'bg-dark': 'bg-ink', 'bg-white': 'bg-white',
  'bg-body': 'bg-bw-50', 'bg-transparent': 'bg-transparent', 'bg-gradient': '',
  'bg-opacity-25': 'bg-opacity-25',

  /* ukuran */
  'w-100': 'w-full', 'w-75': 'w-3/4', 'w-50': 'w-1/2', 'w-25': 'w-1/4', 'w-auto': 'w-auto',
  'h-100': 'h-full', 'h-50': 'h-1/2', 'h-auto': 'h-auto',
  'vh-100': 'h-screen', 'mw-100': 'max-w-full', 'mh-100': 'max-h-full',

  /* garis tepi */
  'border': 'border border-bw-300', 'border-0': 'border-0',
  'border-top': 'border-t border-bw-300', 'border-bottom': 'border-b border-bw-300',
  'border-start': 'border-l border-bw-300', 'border-end': 'border-r border-bw-300',
  'border-top-0': 'border-t-0', 'border-bottom-0': 'border-b-0',
  'border-1': 'border', 'border-2': 'border-2', 'border-3': 'border-4',
  'border-primary': 'border-blue-600', 'border-secondary': 'border-ink-500',
  'border-success': 'border-green-600', 'border-danger': 'border-red-600',
  'border-warning': 'border-amber-500', 'border-info': 'border-sky-500',
  'border-dark': 'border-ink', 'border-light': 'border-bw-200',
  'rounded': 'rounded', 'rounded-0': 'rounded-none', 'rounded-1': 'rounded-sm',
  'rounded-2': 'rounded', 'rounded-3': 'rounded-lg',
  'rounded-pill': 'rounded-full', 'rounded-circle': 'rounded-full',
  'rounded-top': 'rounded-t', 'rounded-bottom': 'rounded-b',

  /* bayangan & posisi */
  'shadow-none': 'shadow-none', 'shadow-sm': 'shadow-sm', 'shadow': 'shadow',
  'shadow-lg': 'shadow-lg',
  'position-relative': 'relative', 'position-absolute': 'absolute',
  'position-fixed': 'fixed', 'position-static': 'static', 'position-sticky': 'sticky',
  'float-start': 'float-left', 'float-end': 'float-right', 'float-none': 'float-none',
  'top-0': 'top-0', 'bottom-0': 'bottom-0', 'start-0': 'left-0', 'end-0': 'right-0',

  /* lain-lain */
  'align-middle': 'align-middle', 'align-top': 'align-top', 'align-bottom': 'align-bottom',
  'overflow-auto': 'overflow-auto', 'overflow-hidden': 'overflow-hidden',
  'user-select-none': 'select-none', 'visually-hidden': 'sr-only',
  'container': 'container mx-auto px-4', 'container-fluid': 'w-full px-4',
  'list-unstyled': 'list-none', 'ratio': '',
  'fade': '', 'clearfix': '',
  'opacity-25': 'opacity-25', 'opacity-50': 'opacity-50', 'opacity-75': 'opacity-75',
  'opacity-100': 'opacity-100',
  'text-bg-primary': 'bg-blue-600 text-white', 'text-bg-success': 'bg-green-600 text-white',
  'text-bg-danger': 'bg-red-600 text-white', 'text-bg-warning': 'bg-amber-400 text-ink',
};

/* Kelas komponen aplikasi — dibiarkan apa adanya, sudah didefinisikan
   di styles/tailwind.css memakai @apply. */
const KEEP = new Set([
  'btn', 'btn-sm', 'btn-lg', 'btn-close', 'btn-close-white', 'btn-group', 'btn-check',
  'btn-primary', 'btn-secondary', 'btn-success', 'btn-danger', 'btn-warning',
  'btn-info', 'btn-dark', 'btn-light',
  'btn-outline-primary', 'btn-outline-secondary', 'btn-outline-success',
  'btn-outline-danger', 'btn-outline-warning', 'btn-outline-dark',
  'form-control', 'form-control-sm', 'form-control-lg', 'form-select', 'form-select-sm',
  'form-label', 'form-text', 'form-check', 'form-check-input', 'form-check-label',
  'form-switch', 'form-range', 'input-group', 'input-group-text', 'input-group-sm',
  'is-invalid', 'is-valid', 'invalid-feedback',
  'card', 'card-header', 'card-body', 'card-footer',
  'card-modern', 'card-header-modern', 'card-body-modern', 'page-header-box',
  'modal', 'modal-dialog', 'modal-dialog-centered', 'modal-content', 'modal-header',
  'modal-title', 'modal-body', 'modal-footer', 'modal-sm', 'modal-lg', 'modal-xl',
  'nav', 'nav-tabs', 'nav-link', 'nav-item', 'tab-content', 'tab-pane',
  'table', 'table-sm', 'table-bordered', 'table-responsive', 'table-dark',
  'table-light', 'table-secondary', 'table-info',
  'badge', 'alert', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info',
  'active', 'show', 'disabled', 'collapse',
]);

/* -------------------------------------------------------------------------- */
function mapToken(tok) {
  if (KEEP.has(tok)) return tok;
  if (Object.prototype.hasOwnProperty.call(MAP, tok)) return MAP[tok];

  // jarak: mb-3, ms-auto, px-2, my-md-4 ...
  let m = tok.match(/^(m|mt|mb|ms|me|mx|my|p|pt|pb|ps|pe|px|py)-(?:(sm|md|lg|xl|xxl)-)?(0|1|2|3|4|5|auto)$/);
  if (m) {
    const side = SIDES[m[1]];
    const bp = m[2] ? (m[2] === 'xxl' ? '2xl' : m[2]) + ':' : '';
    return bp + side + '-' + SPACE[m[3]];
  }

  // gap: g-3 / gap-3 (skala sama dengan jarak)
  m = tok.match(/^(?:g|gap)-(?:(sm|md|lg|xl|xxl)-)?(0|1|2|3|4|5)$/);
  if (m) {
    const bp = m[1] ? (m[1] === 'xxl' ? '2xl' : m[1]) + ':' : '';
    return bp + 'gap-' + SPACE[m[2]];
  }

  return null; // tidak dikenal
}

/* Grid Bootstrap (12 kolom) -> grid Tailwind (12 kolom) */
function mapGrid(tokens) {
  const out = [];
  let isRow = false;
  let gutter = null;
  const cols = [];
  const rest = [];

  for (const t of tokens) {
    if (t === 'row') { isRow = true; continue; }
    let m = t.match(/^g-(0|1|2|3|4|5)$/);
    if (m && isRow) { gutter = SPACE[m[1]]; continue; }
    m = t.match(/^col-(?:(sm|md|lg|xl|xxl)-)?(\d{1,2})$/);
    if (m) { cols.push({ bp: m[1], n: m[2] }); continue; }
    if (t === 'col') { cols.push({ bp: null, n: '12' }); continue; }
    if (t === 'col-auto') { cols.push({ bp: null, n: 'auto' }); continue; }
    rest.push(t);
  }

  if (isRow) {
    out.push('grid', 'grid-cols-12');
    out.push('gap-' + (gutter !== null ? gutter : '6'));
  }
  if (cols.length) {
    const hasBase = cols.some((c) => !c.bp);
    if (!hasBase) out.push('col-span-12');
    for (const c of cols) {
      const span = c.n === 'auto' ? 'col-auto' : 'col-span-' + c.n;
      out.push(c.bp ? (c.bp === 'xxl' ? '2xl' : c.bp) + ':' + span : span);
    }
  }
  return { produced: out, rest, touched: isRow || cols.length > 0 };
}

/* -------------------------------------------------------------------------- */
const unknown = new Map();

function convertClassList(value, file) {
  /* PENTING — jangan pernah menghapus token ganda kalau atribut ini
     mengandung ekspresi template literal. Versi awal skrip ini memakai
     `new Set(...)` tanpa syarat, sehingga operator `&&` yang berulang di
     dalam ${...} ikut terhapus dan seluruh sidebar.js gagal di-parse. */
  const hasExpr = value.includes('${');
  const dedupe = (arr) => (hasExpr ? arr : [...new Set(arr)]);

  const tokens = value.split(/\s+/).filter(Boolean);
  const grid = mapGrid(tokens);
  const out = [...grid.produced];

  for (const tok of grid.rest) {
    // token yang mengandung sisa sintaks template literal -> biarkan
    if (/[${}'"?:=!()<>|&]/.test(tok)) { out.push(tok); continue; }

    const mapped = mapToken(tok);
    if (mapped === null) {
      out.push(tok);
      if (/^(d|m[tbsexy]?|p[tbsexy]?|col|row|g|fs|fw|text|bg|border|justify|align|flex|w|h|float|position|rounded|shadow|order|offset)-/.test(tok)) {
        const key = tok;
        if (!unknown.has(key)) unknown.set(key, new Set());
        unknown.get(key).add(path.basename(file));
      }
    } else if (mapped !== '') {
      out.push(...mapped.split(' '));
    }
  }
  return dedupe(out).join(' ');
}

function processFile(file) {
  const src = fs.readFileSync(file, 'utf8');
  const next = src.replace(/class="([^"]*)"/g, (full, val) => {
    const converted = convertClassList(val, file);
    return converted === val ? full : 'class="' + converted + '"';
  });
  if (next !== src) {
    if (!DRY) fs.writeFileSync(file, next);
    return true;
  }
  return false;
}

const targets = [
  ...fs.readdirSync(path.join(ROOT, 'renderer/pages'))
    .filter((f) => f.endsWith('.html'))
    .map((f) => path.join(ROOT, 'renderer/pages', f)),
  path.join(ROOT, 'renderer/js/sidebar.js'),
];

let changed = 0;
for (const f of targets) if (processFile(f)) changed++;

console.log((DRY ? '[dry-run] ' : '') + 'File diubah: ' + changed + '/' + targets.length);
if (unknown.size) {
  console.log('\nToken mirip-Bootstrap yang TIDAK dipetakan (perlu cek manual):');
  for (const [k, v] of [...unknown].sort()) {
    console.log('  ' + k.padEnd(28) + ' <- ' + [...v].join(', '));
  }
}
