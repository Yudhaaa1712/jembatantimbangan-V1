/**
 * diagnosa-langsir.js — laporan tiket langsir yang terkena salah hitung tara
 *
 * Jalankan:  node scripts/diagnosa-langsir.js
 *
 * Script ini HANYA MEMBACA database, tidak mengubah apa pun.
 *
 * Latar belakang:
 *   Versi lama menghitung netto tiket langsir sebagai
 *       (jumlah bruto semua trip) - (SATU tara)
 *   sehingga tara mobil pada trip ke-2 dan seterusnya ikut terhitung sebagai buah.
 *   Netto membengkak kira-kira sebesar (jumlah_trip - 1) x tara.
 *
 * Kenapa hanya PERKIRAAN:
 *   Tara per trip dulu tidak pernah disimpan. Angka di bawah memakai asumsi
 *   tara semua trip sama dengan tara yang tercatat di tiket. Kalau trip memakai
 *   mobil dengan tara berbeda, angka sebenarnya bisa lebih besar atau lebih kecil.
 *   Karena itu jangan dipakai sebagai dasar tagihan — pakai sebagai daftar periksa.
 */
const path = require('path');
const Database = require('better-sqlite3');
const fs = require('fs');

function cariDbPath() {
  if (process.env.DB_PATH) return process.env.DB_PATH;
  let folder = 'weighbridge-arroyan';
  try {
    const pkg = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'package.json'), 'utf8'));
    if (pkg.name) folder = pkg.name;
  } catch (e) { /* pakai bawaan */ }
  const base = process.env.APPDATA || process.env.USERPROFILE || '.';
  return path.join(base, folder, 'database.db');
}

const dbPath = cariDbPath();
if (!fs.existsSync(dbPath)) {
  console.error('Database tidak ditemukan di: ' + dbPath);
  console.error('Set DB_PATH bila lokasinya berbeda, contoh:');
  console.error('  set DB_PATH=C:\\path\\ke\\database.db && node scripts/diagnosa-langsir.js');
  process.exit(1);
}

const db = new Database(dbPath, { readonly: true });
const rp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
const kg = (n) => new Intl.NumberFormat('id-ID').format(Math.round(n || 0));

console.log('Database : ' + dbPath);
console.log('Dibaca   : ' + new Date().toLocaleString('id-ID'));
console.log('');

// Tiket langsir yang taranya belum tersimpan per trip = data era rumus lama.
const tiket = db.prepare(`
  SELECT tt.id, tt.no_tiket, tt.tanggal, tt.no_polisi, tt.status, tt.status_bayar,
         tt.jumlah_trip_langsir, tt.berat_bruto, tt.berat_tara, tt.berat_netto,
         tt.netto_akhir, tt.harga_per_kg, tt.total_harga, tt.persen_potongan,
         s.nama_supplier,
         (SELECT COUNT(*) FROM transaksi_timbangan_langsir l WHERE l.id_transaksi = tt.id) AS trip_tercatat,
         (SELECT COALESCE(SUM(l.berat_bruto),0) FROM transaksi_timbangan_langsir l WHERE l.id_transaksi = tt.id) AS bruto_trip,
         (SELECT COALESCE(SUM(l.berat_tara),0)  FROM transaksi_timbangan_langsir l WHERE l.id_transaksi = tt.id) AS tara_trip
  FROM transaksi_timbangan tt
  LEFT JOIN supplier s ON tt.id_supplier = s.id
  WHERE tt.is_langsir = 1 AND tt.status = 'selesai'
  ORDER BY tt.tanggal ASC, tt.id ASC
`).all();

if (!tiket.length) {
  console.log('Tidak ada tiket langsir yang sudah selesai. Tidak ada yang perlu diperiksa.');
  process.exit(0);
}

let totalSelisihKg = 0, totalSelisihRp = 0, terdampak = 0;
const baris = [];

for (const t of tiket) {
  const jmlTrip = Math.max(parseInt(t.trip_tercatat) || 0, parseInt(t.jumlah_trip_langsir) || 1);
  const taraTiket = parseFloat(t.berat_tara) || 0;
  const taraPerTrip = parseFloat(t.tara_trip) || 0;

  // Kalau tara sudah tersimpan per trip, tiket ini memakai rumus baru → aman.
  const pakaiRumusBaru = taraPerTrip > 0;
  if (pakaiRumusBaru || jmlTrip <= 1) continue;

  // Perkiraan: tara seharusnya dipotong sebanyak jumlah trip.
  const taraSeharusnya = taraTiket * jmlTrip;
  const selisihKg = taraTiket * (jmlTrip - 1);
  const persen = parseFloat(t.persen_potongan) || 0;
  const harga = parseFloat(t.harga_per_kg) || 0;
  const selisihRp = selisihKg * (1 - persen / 100) * harga;

  terdampak++;
  totalSelisihKg += selisihKg;
  totalSelisihRp += selisihRp;

  baris.push({
    tanggal: t.tanggal,
    no_tiket: t.no_tiket,
    supplier: t.nama_supplier || '-',
    trip: jmlTrip,
    tara_tercatat: taraTiket,
    tara_seharusnya: taraSeharusnya,
    netto_tercatat: parseFloat(t.berat_netto) || 0,
    netto_perkiraan: (parseFloat(t.berat_netto) || 0) - selisihKg,
    selisih_kg: selisihKg,
    selisih_rp: selisihRp,
    status_bayar: t.status_bayar || 'belum_bayar'
  });
}

if (!terdampak) {
  console.log(`Diperiksa ${tiket.length} tiket langsir — semuanya sudah memakai tara per trip.`);
  console.log('Tidak ada yang terdampak salah hitung.');
  process.exit(0);
}

const L = (s, n) => String(s).padEnd(n).slice(0, n);
const R = (s, n) => String(s).padStart(n).slice(-n);

console.log('TIKET LANGSIR TERDAMPAK SALAH HITUNG TARA');
console.log('='.repeat(112));
console.log(L('Tanggal', 11) + L('No. Tiket', 17) + L('Supplier', 20) + R('Trip', 5)
  + R('Netto tercatat', 15) + R('Perkiraan benar', 16) + R('Selisih kg', 12) + '  Status');
console.log('-'.repeat(112));
for (const b of baris) {
  console.log(
    L(b.tanggal, 11) + L(b.no_tiket, 17) + L(b.supplier, 20) + R(b.trip, 5)
    + R(kg(b.netto_tercatat), 15) + R(kg(b.netto_perkiraan), 16) + R(kg(b.selisih_kg), 12)
    + '  ' + (b.status_bayar === 'lunas' ? 'LUNAS' : 'BELUM LUNAS')
  );
}
console.log('='.repeat(112));
console.log('');
console.log(`Tiket langsir diperiksa   : ${tiket.length}`);
console.log(`Tiket terdampak           : ${terdampak}`);
console.log(`Perkiraan kelebihan netto : ${kg(totalSelisihKg)} kg`);
console.log(`Perkiraan kelebihan bayar : ${rp(totalSelisihRp)}`);
console.log('');
console.log('CATATAN PENTING');
console.log('- Angka di atas PERKIRAAN. Tara per trip tidak pernah disimpan di versi lama,');
console.log('  jadi dihitung dengan asumsi semua trip memakai tara yang tercatat di tiket.');
console.log('- Tiket berstatus LUNAS uangnya sudah keluar. Memperbaiki nettonya berarti');
console.log('  ikut menyesuaikan total harga, potongan hutang, dan Buku Kas — sebaiknya');
console.log('  diselesaikan sebagai kesepakatan dengan supplier, bukan diubah diam-diam.');
console.log('- Tiket BELUM LUNAS masih bisa dibatalkan lalu ditimbang ulang bila memungkinkan.');
console.log('- Tiket langsir baru sudah memakai tara per trip, jadi masalah ini tidak berulang.');

db.close();
