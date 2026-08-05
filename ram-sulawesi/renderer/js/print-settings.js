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
          /* Geser pakai margin, bukan padding: padding di CSS tidak boleh
             negatif, jadi dulu struk cuma bisa diturunkan — tidak pernah bisa
             dinaikkan mendekati tepi atas kertas. Margin menerima nilai minus. */
          padding: 0 !important;
          margin-top: ${offsetY}cm !important;
          margin-left: ${offsetX}cm !important;
          margin-right: 0 !important;
          margin-bottom: 0 !important;
          width: ${contentWidth}cm !important;
          max-width: none !important;
          border: none !important;
          box-shadow: none !important;
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
      /* Kop struk bergaya kop surat: logo di kiri, nama & alamat di kanannya.
         Tingginya dikunci dan gambarnya memakai object-fit: contain, jadi logo
         berbentuk apa pun — bulat, kotak, memanjang — muat utuh tanpa gepeng. */
      .struk-kop {
        display: flex;
        align-items: center;
        gap: 3mm;
        margin: 0 0 1mm 0;
      }
      .struk-kop-logo {
        height: 22mm;
        width: auto;
        max-width: 40%;
        object-fit: contain;
        flex: none;
        /* Tanpa ini sebagian printer membuang gambar saat mencetak. */
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .struk-kop-teks { flex: 1 1 auto; min-width: 0; }
      .struk-kop-nama {
        font-family: 'JetBrains Mono', Consolas, 'Courier New', monospace;
        font-weight: 700;
        font-size: ${(fontSize * 2).toFixed(1)}pt;
        line-height: 1.15;
        letter-spacing: 0.5px;
      }
      .struk-kop-alamat {
        font-family: 'JetBrains Mono', Consolas, 'Courier New', monospace;
        font-size: ${(fontSize * 0.95).toFixed(1)}pt;
        line-height: 1.2;
      }
    `;
    document.head.appendChild(style);

    return lineWidth;
  }

  function amanHtml(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /**
   * Bangun elemen kop struk berlogo.
   *
   * Logo tidak bisa ditaruh di dalam <pre> karena isinya teks murni, jadi kop
   * dibuat sebagai elemen HTML tersendiri di atas <pre>.
   *
   * @returns {HTMLElement|null} null bila belum ada logo — pemanggil tinggal
   *          memakai kop teks biasa seperti sebelumnya.
   */
  function buatKopStruk(s, opsi) {
    s = s || {};
    // Logo diambil dari pengaturan yang sudah dimuat applyPrintSettings() bila
    // data yang dioper pemanggil belum memuatnya. Tanpa cadangan ini, logo baru
    // muncul setelah aplikasi di-restart, karena endpoint struk perlu dimuat
    // ulang lebih dulu agar ikut mengirim company_logo.
    const dariSetelan = (window.setupSettings || {}).company_logo;
    const logo = s.company_logo || dariSetelan;
    if (!logo) return null;

    const o = opsi || {};
    const nama = String(o.nama || s.company_name || '').toUpperCase();
    const bawah = [o.alamat || s.company_address, o.telepon || s.company_phone]
      .filter(Boolean).join(' ');

    const kop = document.createElement('div');
    kop.className = 'struk-kop';
    kop.innerHTML =
      `<img class="struk-kop-logo" src="${amanHtml(logo)}" alt="">` +
      `<div class="struk-kop-teks">` +
        `<div class="struk-kop-nama">${amanHtml(nama)}</div>` +
        (bawah ? `<div class="struk-kop-alamat">${amanHtml(bawah)}</div>` : '') +
      `</div>`;
    return kop;
  }

  /**
   * Cetak halaman struk tanpa margin bawaan dialog.
   *
   * window.print() memakai margin bawaan Chromium (± 1 cm) yang menimpa
   * "@page { margin: 0 }", jadi isi struk turun dari tepi atas kertas. Lewat
   * Electron kita bisa memaksa marginType 'none'. Di luar Electron — misalnya
   * saat dibuka di browser biasa — otomatis kembali ke window.print().
   */
  function cetakStruk() {
    const api = window.electronAPI;
    if (api && typeof api.printStruk === 'function') {
      return api.printStruk().catch(() => window.print());
    }
    return Promise.resolve(window.print());
  }

  window.PRINT_SETTING_DEFAULTS = DEFAULTS;
  window.applyPrintSettings = applyPrintSettings;
  window.buatKopStruk = buatKopStruk;
  window.cetakStruk = cetakStruk;
})();
