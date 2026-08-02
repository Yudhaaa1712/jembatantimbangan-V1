# Migrasi ke Tailwind CSS

Bootstrap 5 sudah dilepas seluruhnya. Semua tampilan sekarang dibangun dari Tailwind.

## Yang perlu dijalankan sekali

```bash
npm install          # memasang tailwindcss + bootstrap-icons (devDependency baru)
npm run css          # membangun renderer/assets/css/app.css
npm run css:print    # membangun renderer/assets/css/print.css
```

Setiap kali menambah atau mengubah kelas Tailwind di HTML/JS, jalankan `npm run css`
lagi (atau `npm run css:watch` selama mengembangkan). File hasil build sudah
di-commit, jadi aplikasi tetap jalan walau belum `npm install`.

## Struktur baru

| File | Isi |
|---|---|
| `tailwind.config.js` | Palet warna aplikasi (`bw-*`, `ink-*`), font, lebar sidebar |
| `styles/tailwind.css` | Sumber utama — semua komponen aplikasi |
| `styles/print.css` | Sumber untuk dokumen cetak (surat jalan, struk, riwayat hutang) |
| `renderer/assets/css/app.css` | Hasil build (jangan diedit langsung) |
| `renderer/assets/css/print.css` | Hasil build (jangan diedit langsung) |
| `renderer/js/ui.js` | Pengganti `bootstrap.bundle.min.js` |
| `renderer/js/icons.js` | Sprite ikon SVG (dibangkitkan otomatis) |

**Edit tampilan di `styles/tailwind.css`, bukan di `app.css`.**

## Kenapa Tailwind v3, bukan v4

Aplikasi ini memakai Electron 22 (Chromium 108). Tailwind v4 butuh Chromium 111+
karena memakai `@property` dan `color-mix()`. Kalau nanti Electron dinaikkan ke
v25+, barulah aman pindah ke v4.

## Pengganti Bootstrap JS

`renderer/js/ui.js` mempertahankan API Bootstrap yang sudah dipakai kode aplikasi,
jadi tidak ada JS lama yang perlu diubah:

- `new bootstrap.Modal(el).show()` / `.hide()`
- `bootstrap.Modal.getInstance(el)` / `getOrCreateInstance(el)`
- `data-bs-toggle="modal"`, `data-bs-dismiss="modal"`
- `data-bs-toggle="tab"` + `data-bs-target`
- `data-bs-toggle="collapse"` (submenu sidebar)
- Event `shown.bs.tab`, `shown.bs.modal`, `hidden.bs.modal`

## Ikon

Sebelumnya semua ikon **tidak tampil**: 111 ikon `bi-*` tidak punya file CSS
maupun font, dan 16 ikon `fa-*` menunjuk ke `assets/webfonts/` yang tidak ada.
Sekarang diganti SVG inline:

```html
<svg class="icon"><use href="#i-truck"></use></svg>
```

Ukuran diatur dengan kelas Tailwind biasa (`w-5 h-5`), warna ikut `currentColor`.
Menambah ikon baru: pakai `href="#i-<nama-bootstrap-icons>"` lalu jalankan
`npm run icons`.

## Yang sengaja tetap CSS biasa

1. **`@page`** (ukuran & margin kertas) di `styles/print.css` dan
   `print_ticket.html` — tidak ada padanan utility di Tailwind. Khusus
   `print_ticket.html`, `@page` dibangkitkan saat runtime dari Pengaturan
   (ukuran kertas / geser X / geser Y).
2. **`<style>` di `keuangan.html`** — itu bagian dari string HTML untuk ekspor
   Excel. Dokumen itu terpisah dan tidak memuat `app.css`.
3. **`style="display:none"`** di beberapa elemen — dipertahankan karena ada kode
   yang membaca `el.style.display === 'none'` (mis. `kasContainerT2` di
   `timbangan2.html`). Mengubahnya jadi kelas akan mengubah perilaku.
4. **Aturan cetak halaman Upah** (`body *` visibility) di `styles/tailwind.css` —
   selector semacam ini tidak bisa dinyatakan sebagai utility.

## Skrip migrasi

Disimpan di `scripts/` supaya prosesnya bisa ditelusuri ulang:

- `bs-to-tailwind.js` — peta kelas utilitas Bootstrap → Tailwind
- `inline-style-to-tailwind.js` — `style="..."` → kelas Tailwind
- `rewire-assets.js` — tukar link CSS/JS, ikon font → SVG
- `strip-inline-styles.js` — buang blok `<style>` yang sudah dipindahkan
- `gen-icons.js` — bangkitkan sprite ikon
- `verify-tailwind.js` — pemeriksaan setelah migrasi (`node scripts/verify-tailwind.js`)

## Catatan perubahan tampilan

Warna dan jarak distandarkan ke skala Tailwind, jadi ada pergeseran kecil yang
disengaja (mis. `mb-3` Bootstrap = 16px → `mb-4` Tailwind = 16px, tapi beberapa
jarak dibulatkan). Layout, alur kerja, dan seluruh id/handler tidak berubah.
