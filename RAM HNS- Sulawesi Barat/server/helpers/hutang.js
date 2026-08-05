/**
 * Helper Manajemen Hutang Terpadu
 * Satu buku besar (hutang_ledger) untuk semua jenis pihak.
 *
 * Registry memetakan `party_type` -> tabel master + nama kolomnya.
 * - supir / supplier / tkbm : pakai tabel masternya sendiri (punya relasi transaksi/gaji)
 * - petani / karyawan       : pakai tabel `kontak` generik (dibedakan kolom `tipe`)
 *
 * catatHutangTx() adalah SATU-SATUNYA penulis hutang. Baik potongan otomatis
 * (timbangan/gaji) maupun input manual harus lewat sini agar saldo konsisten.
 */

const PARTIES = {
  // baseWhere: filter tambahan agar entri yang tidak seharusnya muncul di manajemen hutang
  //            (mis. supplier "dadakan" hasil ketik di timbangan → is_temporary=1) tidak tampil.
  supir:    { label: 'Supir',        table: 'supir',         nameCol: 'nama_supir',    phoneCol: 'no_telepon', addrCol: 'alamat', isKontak: false },
  supplier: { label: 'Supplier',     table: 'supplier',      nameCol: 'nama_supplier', phoneCol: null,          addrCol: null,     isKontak: false, baseWhere: 'is_temporary = 0' },
  tkbm:     { label: 'Tukang Muat',  table: 'karyawan_tkbm', nameCol: 'nama_karyawan', phoneCol: 'no_telepon', addrCol: 'alamat', isKontak: false },
  petani:   { label: 'Petani',       table: 'kontak',        nameCol: 'nama',          phoneCol: 'no_telepon', addrCol: 'alamat', isKontak: true, tipe: 'petani' },
  karyawan: { label: 'Karyawan',     table: 'kontak',        nameCol: 'nama',          phoneCol: 'no_telepon', addrCol: 'alamat', isKontak: true, tipe: 'karyawan' },
};

function getPartyConfig(type) {
  const c = PARTIES[type];
  if (!c) throw new Error('Tipe hutang tidak dikenal: ' + type);
  return c;
}

/**
 * Filter dasar sebuah tipe pihak (untuk WHERE). Menggabungkan tipe kontak
 * (petani/karyawan) dan baseWhere (mis. supplier non-temporary).
 * Mengembalikan { sql: 'AND ...', params: [...] } — bisa string kosong.
 */
function partyFilter(c) {
  const clauses = [];
  const params = [];
  if (c.isKontak) { clauses.push('tipe = ?'); params.push(c.tipe); }
  if (c.baseWhere) { clauses.push(c.baseWhere); }
  const sql = clauses.length ? ' AND ' + clauses.join(' AND ') : '';
  return { sql, params };
}

/**
 * Bangun ekspresi SELECT kolom standar (nama, no_telepon, alamat) untuk sebuah tipe.
 */
function selectCols(c) {
  const nama = `${c.nameCol} AS nama`;
  const telp = (c.phoneCol ? c.phoneCol : `''`) + ` AS no_telepon`;
  const alamat = (c.addrCol ? c.addrCol : `''`) + ` AS alamat`;
  return `id, ${nama}, ${telp}, ${alamat}, total_hutang, status`;
}

/**
 * Catat mutasi hutang di dalam sebuah transaksi (tx dari beginTransaction()).
 * Mengembalikan saldo hutang terbaru.
 *
 * @param {object} tx      objek transaksi { execute }
 * @param {object} p
 * @param {string} p.type       party_type
 * @param {number} p.partyId    id pihak di tabel masternya
 * @param {'tambah'|'bayar'} p.jenis
 * @param {number} p.jumlah
 * @param {string} [p.keterangan]
 * @param {number|null} [p.idReferensi]  id transaksi/gaji terkait
 * @param {'manual'|'timbangan'|'gaji'} [p.sumber]
 * @param {number|null} [p.operatorId]
 * @param {string|null} [p.tanggal]      YYYY-MM-DD
 */
function catatHutangTx(tx, p) {
  const c = getPartyConfig(p.type);
  const amount = Math.abs(parseFloat(p.jumlah) || 0);
  if (amount <= 0) throw new Error('Jumlah hutang harus lebih dari 0');
  if (p.jenis !== 'tambah' && p.jenis !== 'bayar') throw new Error('Jenis hutang tidak valid');
  const partyId = parseInt(p.partyId);
  if (!partyId) throw new Error('ID pihak tidak valid');

  const tgl = p.tanggal || new Date().toISOString().split('T')[0];

  // Saldo saat ini dari cache master
  const [rows] = tx.execute(`SELECT total_hutang FROM ${c.table} WHERE id = ?`, [partyId]);
  if (!rows || !rows.length) throw new Error(c.label + ' tidak ditemukan');
  const current = parseFloat(rows[0].total_hutang) || 0;

  const saldo = p.jenis === 'bayar' ? Math.max(0, current - amount) : current + amount;

  // Update cache saldo di master
  tx.execute(`UPDATE ${c.table} SET total_hutang = ? WHERE id = ?`, [saldo, partyId]);

  // Catat ke buku besar
  tx.execute(
    `INSERT INTO hutang_ledger
       (party_type, party_id, tanggal, jenis, jumlah, keterangan, id_referensi, sumber, saldo_setelah, operator_id, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))`,
    [p.type, partyId, tgl, p.jenis, amount, p.keterangan || null,
     p.idReferensi != null ? p.idReferensi : null, p.sumber || 'manual', saldo, p.operatorId != null ? p.operatorId : null]
  );

  return saldo;
}

module.exports = { PARTIES, getPartyConfig, partyFilter, selectCols, catatHutangTx };
