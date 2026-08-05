const { query, queryOne } = require('../config/database');

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
 * Catat satu mutasi kas di dalam transaksi berjalan (tx dari beginTransaction()).
 * Saldo berjalan dihitung dari baris kas terakhir supaya konsisten dengan
 * pencatatan kas di modul lain (timbangan, upah, kas manual).
 *
 * @param {object} tx           objek transaksi { execute }
 * @param {object} p
 * @param {'masuk'|'keluar'} p.jenis
 * @param {number} p.jumlah
 * @param {string} p.keterangan  ditulis huruf kapital agar seragam di buku kas
 * @param {number|null} [p.operatorId]
 * @param {string|null} [p.tanggal]  YYYY-MM-DD (default hari ini)
 * @returns {number} saldo kas setelah mutasi
 */
function catatKasTx(tx, p) {
  const jumlah = Math.abs(parseFloat(p.jumlah) || 0);
  if (jumlah <= 0) throw new Error('Jumlah kas harus lebih dari 0');
  if (p.jenis !== 'masuk' && p.jenis !== 'keluar') throw new Error('Jenis kas tidak valid');

  const [lastRows] = tx.execute(`SELECT saldo_setelah FROM kas ORDER BY id DESC LIMIT 1`);
  const saldoSebelum = (lastRows && lastRows.length) ? parseFloat(lastRows[0].saldo_setelah) || 0 : 0;
  const saldoSesudah = p.jenis === 'masuk' ? saldoSebelum + jumlah : saldoSebelum - jumlah;

  const keterangan = String(p.keterangan || '').trim().toUpperCase();

  // Tanggal default memakai waktu lokal SQLite, bukan new Date().toISOString()
  // (UTC), supaya tidak mundur sehari saat dicatat dini hari WIB dan tetap
  // muncul di filter "Hari Ini" halaman Keuangan.
  const tanggalValid = /^\d{4}-\d{2}-\d{2}$/.test(String(p.tanggal || ''));
  const kolomTanggal = tanggalValid ? '?' : `date('now','localtime')`;
  const params = tanggalValid
    ? [p.tanggal, p.jenis, jumlah, keterangan, saldoSesudah, p.operatorId != null ? p.operatorId : null]
    : [p.jenis, jumlah, keterangan, saldoSesudah, p.operatorId != null ? p.operatorId : null];

  tx.execute(
    `INSERT INTO kas (tanggal, jenis, jumlah, keterangan, saldo_setelah, operator_id, created_at)
     VALUES (${kolomTanggal}, ?, ?, ?, ?, ?, datetime('now','localtime'))`,
    params
  );

  return saldoSesudah;
}

module.exports = {
  recalculateKasBalances,
  catatKasTx
};
