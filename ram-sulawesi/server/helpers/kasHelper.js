const { query, queryOne } = require('../config/database');

/**
 * Hitung ulang kolom saldo_setelah untuk SELURUH baris kas, urut id.
 * Wajib dipanggil setiap kali ada baris kas yang dihapus atau dicatat mundur
 * (backdate), karena saldo berjalan baris-baris setelahnya jadi tidak valid.
 */
async function recalculateKasBalances() {
  try {
    const all = await query(`SELECT id, jenis, jumlah FROM kas ORDER BY id ASC`);
    let runningSaldo = 0;
    for (const row of all) {
      if (row.jenis === 'masuk') {
        runningSaldo += parseFloat(row.jumlah);
      } else {
        runningSaldo -= parseFloat(row.jumlah);
      }
      await query(`UPDATE kas SET saldo_setelah = ? WHERE id = ?`, [runningSaldo, row.id]);
    }
    console.log('[Kas Helper] Successfully recalculated all cash balances. Final saldo:', runningSaldo);
    return runningSaldo;
  } catch (err) {
    console.error('[Kas Helper] Failed to recalculate cash balances:', err);
    throw err;
  }
}

/**
 * ── SOP PENULISAN KETERANGAN LAPORAN KEUANGAN ───────────────────────────────
 *
 * Satu baris kas ditulis dengan pola tetap:
 *
 *     KATEGORI - NAMA - REFERENSI
 *
 *   KATEGORI  : jenis kejadiannya, kata baku & pendek.
 *               PEMBELIAN TBS / PENJUALAN TBS / PEMBAYARAN SUPPLIER /
 *               BAYAR HUTANG SUPIR / KASBON TUKANG MUAT / UPAH SUPIR /
 *               UPAH TKBM / BATAL TRANSAKSI / MODAL MASUK / BIAYA OPERASIONAL
 *   NAMA      : pihak yang bersangkutan (supir, supplier, karyawan). Boleh kosong.
 *   REFERENSI : nomor tiket / nomor pembayaran / periode. Boleh kosong.
 *
 * Aturan:
 *   - HURUF BESAR semua, pemisah antar bagian selalu " - ".
 *   - Bagian yang kosong dihilangkan, tidak menyisakan tanda hubung menggantung.
 *   - Tanpa kalimat penjelasan, tanpa kata berulang ("PEMBAYARAN PEMBAYARAN..."),
 *     tanpa tanda baca hias. Detail lengkap sudah ada di menu asalnya.
 *   - Keterangan tambahan yang benar-benar perlu (mis. metode transfer) ditulis
 *     dalam kurung di paling belakang: "... (TRANSFER)".
 *
 * Contoh benar:
 *   PEMBELIAN TBS - UJANG - TKT250805001
 *   PEMBAYARAN SUPPLIER - PT SAWIT JAYA - BYR250805001 (TRANSFER)
 *   BAYAR HUTANG SUPIR - AHMAD
 *   UPAH TKBM - BUDI - 01/08/2026 S/D 05/08/2026
 *   BATAL TRANSAKSI - UJANG - TKT250805001
 */
function buildKeterangan(kategori, nama, referensi, tambahan) {
  const bersih = (v) => String(v == null ? '' : v).replace(/\s+/g, ' ').trim().toUpperCase();
  const inti = [kategori, nama, referensi].map(bersih).filter(Boolean).join(' - ');
  const extra = bersih(tambahan);
  return extra ? `${inti} (${extra})` : inti;
}

/** Format tanggal YYYY-MM-DD → DD/MM/YYYY (untuk bagian REFERENSI periode). */
function tanggalRingkas(str) {
  if (!str) return '';
  const p = String(str).split('-');
  return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : String(str);
}

/** Bagian REFERENSI untuk rentang periode gaji/upah. */
function periodeRef(mulai, akhir) {
  const a = tanggalRingkas(mulai), b = tanggalRingkas(akhir);
  if (!a && !b) return '';
  return a === b ? a : `${a} S/D ${b}`;
}

module.exports = {
  recalculateKasBalances,
  buildKeterangan,
  tanggalRingkas,
  periodeRef
};
