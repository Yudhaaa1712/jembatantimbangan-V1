// server/helpers/kasHelper.test.js
// SOP penulisan keterangan Laporan Keuangan: KATEGORI - NAMA - REFERENSI
process.env.DB_PATH = ':memory:';
const { buildKeterangan, tanggalRingkas, periodeRef } = require('./kasHelper');

describe('kasHelper.buildKeterangan - SOP keterangan Buku Kas', () => {
  test('menggabungkan kategori, nama, dan referensi dengan pemisah baku', () => {
    expect(buildKeterangan('PEMBELIAN TBS', 'Ujang', 'TKT250805001'))
      .toBe('PEMBELIAN TBS - UJANG - TKT250805001');
  });

  test('semua huruf dibesarkan dan spasi berlebih dirapikan', () => {
    expect(buildKeterangan('  bayar   hutang supir ', 'ahmad  fauzi'))
      .toBe('BAYAR HUTANG SUPIR - AHMAD FAUZI');
  });

  test('bagian kosong dibuang, tidak menyisakan tanda hubung menggantung', () => {
    expect(buildKeterangan('MODAL MASUK')).toBe('MODAL MASUK');
    expect(buildKeterangan('BATAL UPAH TKBM', '', 'GJT#5')).toBe('BATAL UPAH TKBM - GJT#5');
    expect(buildKeterangan('KASBON PETANI', null, undefined)).toBe('KASBON PETANI');
  });

  test('keterangan tambahan ditulis dalam kurung di paling belakang', () => {
    expect(buildKeterangan('PEMBAYARAN SUPPLIER', 'PT Sawit Jaya', 'BYR250805001', 'transfer'))
      .toBe('PEMBAYARAN SUPPLIER - PT SAWIT JAYA - BYR250805001 (TRANSFER)');
  });

  test('tanpa tambahan, tidak ada kurung kosong', () => {
    expect(buildKeterangan('PENJUALAN TBS', 'Sirun', 'TKT250805002', null))
      .toBe('PENJUALAN TBS - SIRUN - TKT250805002');
  });
});

describe('kasHelper.periodeRef - referensi periode gaji/upah', () => {
  test('tanggal diubah ke format Indonesia', () => {
    expect(tanggalRingkas('2026-08-05')).toBe('05/08/2026');
  });

  test('rentang ditulis dengan pemisah S/D', () => {
    expect(periodeRef('2026-08-01', '2026-08-05')).toBe('01/08/2026 S/D 05/08/2026');
  });

  test('periode satu hari tidak diulang', () => {
    expect(periodeRef('2026-08-05', '2026-08-05')).toBe('05/08/2026');
  });

  test('periode kosong menghasilkan string kosong', () => {
    expect(periodeRef(null, null)).toBe('');
  });
});
