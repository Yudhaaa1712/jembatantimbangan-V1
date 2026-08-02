/* ============================================================================
 * print-struk.js — kerangka cetak struk bersama
 *
 * Semua halaman cetak memakai ukuran kertas, lebar kolom, ukuran font, offset,
 * dan kop perusahaan yang SAMA — semuanya diambil dari Pengaturan, sama seperti
 * struk yang keluar setelah Timbangan 2. Ubah pengaturannya sekali, seluruh
 * halaman cetak ikut berubah.
 *
 * Pakai di halaman cetak:
 *   const P = await StrukPrinter.init();      // memuat pengaturan + inject CSS
 *   let out = P.kop();                        // kop perusahaan
 *   out += P.baris('Tanggal', '29/07/2026');
 *   P.render(out);                            // tampilkan lalu cetak
 * ========================================================================== */
(function (global) {

  function padRight(s, n) { s = String(s == null ? '' : s); return s.length >= n ? s.slice(0, n) : s + ' '.repeat(n - s.length); }
  function padLeft(s, n) { s = String(s == null ? '' : s); return s.length >= n ? s.slice(0, n) : ' '.repeat(n - s.length) + s; }

  /** Pecah teks agar tidak melebihi lebar kolom, memotong di spasi bila bisa. */
  function wrapText(text, width) {
    const kata = String(text == null ? '' : text).split(/\s+/).filter(Boolean);
    if (!kata.length) return [''];
    const baris = [];
    let kini = '';
    for (const w of kata) {
      if (!kini.length) { kini = w; continue; }
      if ((kini + ' ' + w).length <= width) kini += ' ' + w;
      else { baris.push(kini); kini = w; }
    }
    if (kini.length) baris.push(kini);
    return baris.map(b => b.length > width ? b.slice(0, width) : b);
  }

  const StrukPrinter = {
    /**
     * Muat pengaturan cetak dan pasang aturan @page yang sama dengan struk
     * timbangan. Mengembalikan objek berisi helper + lebar kolom (W).
     */
    async init() {
      let s = {};
      try {
        const r = await (await fetch('/setup/settings?t=' + Date.now())).json();
        if (r.success && r.data) s = r.data;
      } catch (e) { console.error('[Struk] Gagal memuat pengaturan cetak:', e); }

      const angka = (v, bawaan) => {
        const n = parseFloat(v);
        return (v === undefined || v === null || v === '' || isNaN(n)) ? bawaan : n;
      };

      const offsetX = angka(s.print_offset_x, 0);
      const offsetY = angka(s.print_offset_y, 0);
      const contentWidth = angka(s.print_content_width, 19);
      const paperW = angka(s.print_paper_width, 9.5);
      const paperH = angka(s.print_paper_height, 11);
      const fontSize = angka(s.print_font_size, 11);
      const W = parseInt(s.print_line_width) || 80;

      const style = document.createElement('style');
      style.innerHTML = `
        @media print {
          @page { size: ${paperW}in ${paperH}in; margin: 0; }
          body { margin: 0 !important; padding: 0 !important; }
          .no-print { display: none !important; }
          .receipt-container {
            padding: ${offsetY}cm 0 0 ${offsetX}cm !important;
            margin: 0 !important;
            width: ${contentWidth}cm !important;
            max-width: none !important;
            box-sizing: border-box !important;
          }
          pre {
            font-size: ${fontSize}pt !important;
            width: 100% !important;
            line-height: 1.05 !important;
            margin: 0 !important;
            padding: 0 !important;
          }
        }
      `;
      document.head.appendChild(style);

      const api = {
        W,
        settings: s,
        padRight, padLeft, wrapText,

        tengah(teks) {
          return wrapText(teks, W).map(b => {
            const sisa = W - b.length;
            return sisa <= 0 ? b : ' '.repeat(Math.floor(sisa / 2)) + b;
          }).join('\n');
        },

        garis(char) { return (char || '-').repeat(W); },

        /** Baris "Label      : nilai" dengan label rata kiri. */
        baris(label, nilai, lebarLabel) {
          return padRight(label, lebarLabel || 17) + ': ' + (nilai == null ? '' : nilai);
        },

        /** Baris dengan nilai rata kanan — untuk angka. */
        barisKanan(label, nilai, lebarLabel) {
          const l = lebarLabel || 17;
          return padRight(label, l) + ': ' + padLeft(String(nilai == null ? '' : nilai), W - l - 2);
        },

        rp(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(parseFloat(n) || 0)); },
        angka(n) { return new Intl.NumberFormat('id-ID').format(Math.round(parseFloat(n) || 0)); },

        /** Kop perusahaan dari Pengaturan — otomatis ikut kalau diubah di sana. */
        kop(judul) {
          const nama = s.company_name || 'NAMA PERUSAHAAN';
          const alamat = s.company_address || '';
          const telp = s.company_phone ? 'Telp: ' + s.company_phone : '';
          let out = api.tengah(nama.toUpperCase()) + '\n';
          if (alamat) out += api.tengah(alamat) + '\n';
          if (telp) out += api.tengah(telp) + '\n';
          out += api.garis('=') + '\n';
          if (judul) out += api.tengah(judul.toUpperCase()) + '\n' + api.garis('=') + '\n';
          return out;
        },

        /** Dua kolom tanda tangan di bawah struk. */
        tandaTangan(kiriJudul, kiriNama, kananJudul, kananNama) {
          const kol = Math.floor(W / 2);
          const tengahKol = (t) => {
            t = String(t || '');
            const sisa = kol - t.length;
            return sisa <= 0 ? t.slice(0, kol) : ' '.repeat(Math.floor(sisa / 2)) + t + ' '.repeat(Math.ceil(sisa / 2));
          };
          // Nama dipotong lebih dulu agar kurung tutup tidak ikut terbuang saat
          // nama panjang atau kolomnya sempit.
          const kurung = (nama) => {
            const isi = String(nama || '').trim();
            const ruang = Math.max(1, kol - 4);
            return '( ' + (isi ? padRight(isi.slice(0, ruang), Math.min(isi.length, ruang)) : ' '.repeat(ruang)) + ' )';
          };
          return '\n' + tengahKol(kiriJudul) + tengahKol(kananJudul) + '\n\n\n\n'
            + tengahKol(kurung(kiriNama)) + tengahKol(kurung(kananNama)) + '\n';
        },

        /** Tampilkan hasil ke layar lalu buka dialog cetak. */
        render(teks, autoPrint) {
          const wadah = document.querySelector('.receipt-container');
          wadah.innerHTML = '<pre class="w-full font-mono font-bold text-[11pt] text-[#000] leading-[1.05] m-0 p-0 whitespace-pre-wrap">'
            + String(teks).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            + '</pre>';
          if (autoPrint !== false) setTimeout(() => window.print(), 500);
        }
      };

      return api;
    }
  };

  global.StrukPrinter = StrukPrinter;
})(window);
