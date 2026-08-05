/**
 * Pengaturan cetak terpusat.
 *
 * Semua halaman struk (transaksi, pengiriman, riwayat hutang, dst.) memakai
 * file ini supaya ukuran kertas, geser X/Y, ukuran font, dan lebar baris
 * cukup diatur di satu tempat: Pengaturan → Profil & Tampilan.
 *
 * Aturan penting: kertas TIDAK pernah diberi margin. Posisi cetak seluruhnya
 * dikendalikan oleh "geser X / geser Y" agar hasil di printer dot matrix
 * benar-benar sesuai preview.
 */
(function () {
  const DEFAULTS = {
    print_mode: 'text',
    print_offset_x: 0,
    print_offset_y: 0,
    print_content_width: 19,
    print_paper_width: 9.5,
    print_paper_height: 11,
    print_font_size: 11,
    print_line_width: 80
  };

  function num(val, fallback) {
    const n = parseFloat(val);
    return (val === undefined || val === null || val === '' || isNaN(n)) ? fallback : n;
  }

  /**
   * Ambil pengaturan cetak dari server, simpan ke window.setupSettings,
   * lalu suntikkan CSS cetak (kertas + margin 0).
   *
   * @param {object} [opts]
   * @param {boolean} [opts.a6Override=true] paksa 4.13x5.83in saat print_mode bukan 'text'
   * @returns {Promise<number>} lebar baris (karakter) untuk struk teks
   */
  async function applyPrintSettings(opts) {
    const options = opts || {};
    const a6Override = options.a6Override !== false;
    let s = {};

    try {
      const res = await fetch('/setup/settings?t=' + Date.now());
      const r = await res.json();
      if (r.success && r.data) s = r.data;
    } catch (e) {
      console.error('[PrintSettings] Gagal memuat pengaturan cetak:', e);
    }

    window.setupSettings = s;

    const offsetX = num(s.print_offset_x, DEFAULTS.print_offset_x);
    const offsetY = num(s.print_offset_y, DEFAULTS.print_offset_y);
    const contentWidth = num(s.print_content_width, DEFAULTS.print_content_width);
    const fontSize = num(s.print_font_size, DEFAULTS.print_font_size);
    const lineWidth = parseInt(s.print_line_width) || DEFAULTS.print_line_width;

    let paperW = num(s.print_paper_width, DEFAULTS.print_paper_width);
    let paperH = num(s.print_paper_height, DEFAULTS.print_paper_height);
    if (a6Override && s.print_mode && s.print_mode !== 'text') {
      paperW = 4.13;
      paperH = 5.83;
    }

    const style = document.createElement('style');
    style.setAttribute('data-print-settings', '1');
    style.innerHTML = `
      @media print {
        @page {
          size: ${paperW}in ${paperH}in;
          margin: 0 !important;
        }
        html, body {
          margin: 0 !important;
          padding: 0 !important;
          background: #fff !important;
        }
        .no-print, .print-btn { display: none !important; }
        .receipt-container, .sheet, .sj-container {
          padding-top: ${offsetY}cm !important;
          padding-left: ${offsetX}cm !important;
          padding-right: 0 !important;
          padding-bottom: 0 !important;
          margin: 0 !important;
          width: ${contentWidth}cm !important;
          max-width: none !important;
          border: none !important;
          box-shadow: none !important;
          box-sizing: border-box !important;
        }
        pre {
          font-size: ${fontSize}pt !important;
          width: 100% !important;
          line-height: 2 !important;
          margin: 0 !important;
          padding: 0 !important;
        }
      }
    `;
    document.head.appendChild(style);

    return lineWidth;
  }

  window.PRINT_SETTING_DEFAULTS = DEFAULTS;
  window.applyPrintSettings = applyPrintSettings;
})();
