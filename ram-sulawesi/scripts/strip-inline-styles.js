/* ============================================================================
 * strip-inline-styles.js — bagian ketiga migrasi Tailwind
 *
 * Menghapus blok <style> di <head> halaman aplikasi (isinya sudah dipindahkan
 * ke styles/tailwind.css), memasang kelas scope pada <body>, dan merapikan
 * sisa kelas khas Bootstrap yang tidak lagi dipakai.
 *
 * Blok <style> yang berada DI DALAM string JavaScript (mis. template ekspor
 * Excel di keuangan.html) sengaja tidak disentuh — dokumen itu terpisah dan
 * tidak memuat app.css.
 *
 *   node scripts/strip-inline-styles.js [--dry]
 * ========================================================================== */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DRY = process.argv.includes('--dry');
const PAGES = path.join(ROOT, 'renderer/pages');

// halaman -> kelas yang dipasang di <body>
const BODY_CLASS = {
  'timbangan1.html': 'page-weigh',
  'timbangan2.html': 'page-weigh',
  'upah.html': 'page-upah',
  'login.html': 'no-sidebar',
  'activation.html': 'no-sidebar',
  '404.html': 'no-sidebar',
};

// kelas sisa Bootstrap yang sudah tidak berarti -> diganti / dibuang
const CLEANUP = {
  'form-label-custom': 'form-label',
  'form-control-custom': '',
  'form-select-custom': '',
  'nav-tabs-modern': '',
  'list-group-item': 'list-group-item-custom',
};

function stripHeadStyle(src) {
  const headEnd = src.indexOf('</head>');
  if (headEnd === -1) return src;
  const head = src.slice(0, headEnd);
  const rest = src.slice(headEnd);
  const cleanedHead = head.replace(/[ \t]*<style>[\s\S]*?<\/style>\r?\n?/g, '');
  return cleanedHead + rest;
}

function addBodyClass(src, cls) {
  if (!cls) return src;
  return src.replace(/<body(\s[^>]*)?>/, (full, attrs) => {
    attrs = attrs || '';
    if (/class="/.test(attrs)) {
      if (new RegExp('\\b' + cls + '\\b').test(attrs)) return full;
      return '<body' + attrs.replace(/class="([^"]*)"/, (m, v) => `class="${v} ${cls}"`) + '>';
    }
    return `<body${attrs} class="${cls}">`;
  });
}

function cleanupClasses(src) {
  return src.replace(/class="([^"]*)"/g, (full, val) => {
    const out = [];
    for (const t of val.split(/\s+/).filter(Boolean)) {
      if (Object.prototype.hasOwnProperty.call(CLEANUP, t)) {
        if (CLEANUP[t]) out.push(CLEANUP[t]);
      } else {
        out.push(t);
      }
    }
    const next = [...new Set(out)].join(' ');
    return next === val ? full : `class="${next}"`;
  });
}

let n = 0;
for (const f of fs.readdirSync(PAGES).filter((x) => x.endsWith('.html'))) {
  // Dokumen cetak berdiri sendiri: punya @page sendiri, tidak memuat app.css
  if (['print_ticket.html', 'print_hutang.html', 'surat_jalan.html'].includes(f)) continue;

  const file = path.join(PAGES, f);
  const src = fs.readFileSync(file, 'utf8');
  let next = stripHeadStyle(src);
  next = addBodyClass(next, BODY_CLASS[f]);
  next = cleanupClasses(next);

  if (next !== src) {
    if (!DRY) fs.writeFileSync(file, next);
    console.log('  ' + f);
    n++;
  }
}
console.log((DRY ? '[dry-run] ' : '') + 'File diubah: ' + n);
