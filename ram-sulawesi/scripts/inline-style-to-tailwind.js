/* ============================================================================
 * inline-style-to-tailwind.js — bagian keempat migrasi Tailwind
 *
 * Mengubah atribut style="..." statis menjadi kelas Tailwind (memakai nilai
 * arbitrer bila perlu, mis. w-[90px] / bg-[#f8fafc]) sehingga tampilannya
 * persis sama.
 *
 * Sengaja TIDAK disentuh:
 *   - style yang mengandung ${...}  (dibangkitkan JavaScript)
 *   - deklarasi `display: none`     (beberapa skrip membaca
 *     el.style.display === 'none', mis. kasContainerT2 di timbangan2.html)
 *   - properti yang tidak punya padanan -> tetap tertinggal di style=""
 *
 *   node scripts/inline-style-to-tailwind.js [--dry]
 * ========================================================================== */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DRY = process.argv.includes('--dry');

const SIDE = { top: 't', right: 'r', bottom: 'b', left: 'l' };
const WEIGHT = {
  100: 'font-thin', 200: 'font-extralight', 300: 'font-light', 400: 'font-normal',
  500: 'font-medium', 600: 'font-semibold', 700: 'font-bold', 800: 'font-extrabold',
  900: 'font-black', normal: 'font-normal', bold: 'font-bold',
};

/* Nilai arbitrer Tailwind tidak boleh mengandung spasi. Spasi HARUS diganti
   underscore, bukan dihapus — menghapusnya menghasilkan CSS tidak valid
   (mis. `1fr 1fr 1fr` pernah menjadi `1fr1fr1fr`, yang membuat grid tiga
   kolom di Timbangan 2 runtuh menjadi satu kolom). */
const len = (v) => v.trim().replace(/,\s+/g, ',').replace(/\s+/g, '_');

function decl(prop, value) {
  const v = value.trim();
  const lv = v.toLowerCase();

  switch (prop) {
    case 'width': return lv === 'auto' ? 'w-auto' : lv === '100%' ? 'w-full' : `w-[${len(v)}]`;
    case 'min-width': return `min-w-[${len(v)}]`;
    case 'max-width': return lv === '100%' ? 'max-w-full' : lv === 'none' ? 'max-w-none' : `max-w-[${len(v)}]`;
    case 'height': return lv === 'auto' ? 'h-auto' : lv === '100%' ? 'h-full' : `h-[${len(v)}]`;
    case 'min-height': return `min-h-[${len(v)}]`;
    case 'max-height': return `max-h-[${len(v)}]`;

    case 'font-size': return `text-[${len(v)}]`;
    case 'font-weight': return WEIGHT[lv] || `font-[${len(v)}]`;
    case 'font-family': return /mono|courier|consolas/i.test(v) ? 'font-mono' : null;
    case 'font-style': return lv === 'italic' ? 'italic' : lv === 'normal' ? 'not-italic' : null;
    case 'line-height': return `leading-[${len(v)}]`;
    case 'letter-spacing': return `tracking-[${len(v)}]`;
    case 'text-align': return { left: 'text-left', center: 'text-center', right: 'text-right', justify: 'text-justify' }[lv] || null;
    case 'text-transform': return { uppercase: 'uppercase', lowercase: 'lowercase', capitalize: 'capitalize', none: 'normal-case' }[lv] || null;
    case 'text-decoration': return lv === 'none' ? 'no-underline' : lv.includes('underline') ? 'underline' : null;
    case 'white-space': return lv === 'nowrap' ? 'whitespace-nowrap' : lv === 'pre' ? 'whitespace-pre' : lv === 'normal' ? 'whitespace-normal' : null;
    case 'word-break': return lv === 'break-all' ? 'break-all' : null;

    case 'color': return `text-[${len(v)}]`;
    case 'background':
    case 'background-color':
      if (/gradient|url\(/i.test(v)) return null;
      return lv === 'transparent' ? 'bg-transparent' : `bg-[${len(v)}]`;
    case 'opacity': return `opacity-[${len(v)}]`;

    case 'border-radius': return lv === '50%' ? 'rounded-full' : `rounded-[${len(v)}]`;
    case 'border': {
      if (lv === 'none' || lv === '0') return 'border-0';
      const m = v.match(/^([\d.]+px)\s+(solid|dashed|dotted)\s+(.+)$/i);
      if (!m) return null;
      const w = m[1] === '1px' ? 'border' : `border-[${m[1]}]`;
      const style = m[2].toLowerCase() === 'solid' ? '' : ' border-' + m[2].toLowerCase();
      return `${w}${style} border-[${len(m[3])}]`;
    }
    case 'border-top': case 'border-bottom': case 'border-left': case 'border-right': {
      const s = SIDE[prop.split('-')[1]];
      if (lv === 'none' || lv === '0') return `border-${s}-0`;
      const m = v.match(/^([\d.]+px)\s+(solid|dashed|dotted)\s+(.+)$/i);
      if (!m) return null;
      const w = m[1] === '1px' ? `border-${s}` : `border-${s}-[${m[1]}]`;
      return `${w} border-[${len(m[3])}]`;
    }
    case 'border-color': return `border-[${len(v)}]`;
    case 'border-collapse': return lv === 'collapse' ? 'border-collapse' : 'border-separate';

    case 'display':
      // `none` sengaja dilewati (dipakai oleh JS lewat el.style.display)
      return { flex: 'flex', block: 'block', 'inline-block': 'inline-block',
               inline: 'inline', grid: 'grid', 'inline-flex': 'inline-flex' }[lv] || null;
    case 'flex': return v === '1' ? 'flex-1' : `flex-[${len(v)}]`;
    case 'flex-direction': return { row: 'flex-row', column: 'flex-col' }[lv] || null;
    case 'flex-wrap': return { wrap: 'flex-wrap', nowrap: 'flex-nowrap' }[lv] || null;
    case 'align-items': return { center: 'items-center', 'flex-start': 'items-start', 'flex-end': 'items-end', stretch: 'items-stretch', baseline: 'items-baseline' }[lv] || null;
    case 'justify-content': return { center: 'justify-center', 'space-between': 'justify-between', 'space-around': 'justify-around', 'flex-start': 'justify-start', 'flex-end': 'justify-end' }[lv] || null;
    case 'gap': return `gap-[${len(v)}]`;
    case 'grid-template-columns': return `[grid-template-columns:${len(v)}]`;

    case 'position': return { relative: 'relative', absolute: 'absolute', fixed: 'fixed', sticky: 'sticky', static: 'static' }[lv] || null;
    case 'top': case 'bottom': case 'left': case 'right': return `${prop}-[${len(v)}]`;
    case 'z-index': return `z-[${len(v)}]`;
    case 'float': return { left: 'float-left', right: 'float-right', none: 'float-none' }[lv] || null;
    case 'overflow': case 'overflow-x': case 'overflow-y': {
      const key = prop === 'overflow' ? 'overflow' : 'overflow-' + prop.slice(-1);
      return ['auto', 'hidden', 'scroll', 'visible'].includes(lv) ? `${key}-${lv}` : null;
    }
    case 'cursor': return `cursor-${lv}`;
    case 'vertical-align': return ['middle', 'top', 'bottom', 'baseline'].includes(lv) ? `align-${lv}` : null;
    case 'box-shadow': return lv === 'none' ? 'shadow-none' : `shadow-[${len(v)}]`;
    case 'object-fit': return `object-${lv}`;
    case 'list-style': case 'list-style-type': return lv === 'none' ? 'list-none' : null;
    case 'box-sizing': return lv === 'border-box' ? 'box-border' : 'box-content';
    case 'resize': return lv === 'none' ? 'resize-none' : `resize-${lv === 'both' ? '' : lv}`;
    case 'visibility': return lv === 'hidden' ? 'invisible' : 'visible';
    case 'transform': return `[transform:${len(v)}]`;
  }

  // margin / padding
  const mp = prop.match(/^(margin|padding)(?:-(top|right|bottom|left))?$/);
  if (mp) {
    const p = mp[1] === 'margin' ? 'm' : 'p';
    if (mp[2]) {
      if (v === '0') return `${p}${SIDE[mp[2]]}-0`;
      if (lv === 'auto') return `${p}${SIDE[mp[2]]}-auto`;
      return `${p}${SIDE[mp[2]]}-[${len(v)}]`;
    }
    const parts = v.split(/\s+/);
    if (parts.length === 1) return parts[0] === '0' ? `${p}-0` : `${p}-[${len(parts[0])}]`;
    if (parts.length === 2) {
      const y = parts[0] === '0' ? `${p}y-0` : `${p}y-[${parts[0]}]`;
      const x = parts[1] === 'auto' ? `${p}x-auto` : parts[1] === '0' ? `${p}x-0` : `${p}x-[${parts[1]}]`;
      return `${y} ${x}`;
    }
    if (parts.length === 4) {
      return ['t', 'r', 'b', 'l']
        .map((s, i) => (parts[i] === '0' ? `${p}${s}-0` : parts[i] === 'auto' ? `${p}${s}-auto` : `${p}${s}-[${parts[i]}]`))
        .join(' ');
    }
  }
  return null;
}

let converted = 0;
let kept = 0;
const unmapped = new Map();

function convertTag(tag) {
  const styleMatch = tag.match(/\sstyle="([^"]*)"/);
  if (!styleMatch) return tag;
  const raw = styleMatch[1];
  if (raw.includes('${') || raw.includes('{{')) return tag; // dibangkitkan JS

  const classes = [];
  const leftover = [];

  for (const part of raw.split(';')) {
    if (!part.trim()) continue;
    const idx = part.indexOf(':');
    if (idx === -1) { leftover.push(part.trim()); continue; }
    const prop = part.slice(0, idx).trim().toLowerCase();
    const value = part.slice(idx + 1).trim().replace(/\s*!important$/i, '');

    if (prop === 'display' && value.toLowerCase() === 'none') { leftover.push(`${prop}:${value}`); continue; }
    if (/!important/i.test(part)) { leftover.push(part.trim()); continue; }

    const cls = decl(prop, value);
    if (cls) { classes.push(...cls.split(' ')); converted++; }
    else {
      leftover.push(`${prop}:${value}`);
      unmapped.set(prop, (unmapped.get(prop) || 0) + 1);
      kept++;
    }
  }

  if (!classes.length) return tag;

  let out = tag.replace(/\sstyle="[^"]*"/, leftover.length ? ` style="${leftover.join(';')}"` : '');
  if (/\sclass="/.test(out)) {
    out = out.replace(/\sclass="([^"]*)"/, (m, v) => ` class="${[...new Set((v + ' ' + classes.join(' ')).split(/\s+/).filter(Boolean))].join(' ')}"`);
  } else {
    out = out.replace(/^<([a-zA-Z][\w-]*)/, `<$1 class="${classes.join(' ')}"`);
  }
  return out;
}

let files = 0;
for (const f of fs.readdirSync(path.join(ROOT, 'renderer/pages')).filter((x) => x.endsWith('.html'))) {
  const file = path.join(ROOT, 'renderer/pages', f);
  const src = fs.readFileSync(file, 'utf8');
  const next = src.replace(/<[a-zA-Z][^>]*\sstyle="[^"]*"[^>]*>/g, convertTag);
  if (next !== src) {
    if (!DRY) fs.writeFileSync(file, next);
    files++;
  }
}

console.log((DRY ? '[dry-run] ' : '') + `File diubah: ${files} | deklarasi -> kelas: ${converted} | tetap inline: ${kept}`);
if (unmapped.size) {
  console.log('\nProperti yang dibiarkan inline:');
  for (const [k, v] of [...unmapped].sort((a, b) => b[1] - a[1])) console.log('  ' + k.padEnd(24) + v + 'x');
}
