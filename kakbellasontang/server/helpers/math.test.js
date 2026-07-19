// electron-app/server/helpers/math.test.js
const { calculateRowValues } = require('./math');

describe('calculateRowValues - Unit Tests', () => {
  test('1. Normal Calculation (No Deductions, No Percent Deductions)', () => {
    const mockRow = {
      berat_bruto: 10000,
      berat_tara: 3000,
      persen_potongan: 0,
      harga_per_kg: 2000,
      total_harga: 0, // Should fallback to calculate
      potongan_jalan: 0,
      potongan_pupuk_rp: 0,
      potongan_hutang_rp: 0
    };

    const res = calculateRowValues(mockRow);

    expect(res.bruto).toBe(10000);
    expect(res.tara).toBe(3000);
    expect(res.netto1).toBe(7000); // 10000 - 3000
    expect(res.persen).toBe(0);
    expect(res.kgPot).toBe(0);
    expect(res.netto2).toBe(7000);
    expect(res.hrg).toBe(2000);
    expect(res.totGross).toBe(14000000); // 7000 * 2000
    expect(res.totPot).toBe(0);
    expect(res.sisa).toBe(14000000);
  });

  test('2. Percent Deductions and Rupiah Deductions', () => {
    const mockRow = {
      berat_bruto: 12000,
      berat_tara: 4000,
      persen_potongan: 1.5, // 1.5%
      harga_per_kg: 2100,
      total_harga: 0,
      potongan_jalan: 150000,
      potongan_pupuk_rp: 300000,
      potongan_hutang_rp: 250000
    };

    const res = calculateRowValues(mockRow);

    expect(res.bruto).toBe(12000);
    expect(res.tara).toBe(4000);
    expect(res.netto1).toBe(8000);
    expect(res.persen).toBe(1.5);
    expect(res.kgPot).toBe(120); // 1.5% of 8000 = 120
    expect(res.netto2).toBe(7880); // 8000 - 120
    expect(res.hrg).toBe(2100);
    expect(res.totGross).toBe(16548000); // 7880 * 2100
    expect(res.potJln).toBe(150000);
    expect(res.potPpk).toBe(300000);
    expect(res.potHut).toBe(250000);
    expect(res.totPot).toBe(700000); // 150k + 300k + 250k
    expect(res.sisa).toBe(15848000); // 16548000 - 700000
  });

  test('3. Deductions Exceeding Gross Price (Sisa should not be negative)', () => {
    const mockRow = {
      berat_bruto: 2000,
      berat_tara: 1500,
      persen_potongan: 0,
      harga_per_kg: 1000,
      total_harga: 0,
      potongan_jalan: 300000,
      potongan_pupuk_rp: 300000,
      potongan_hutang_rp: 0
    };

    const res = calculateRowValues(mockRow);

    expect(res.netto1).toBe(500);
    expect(res.totGross).toBe(500000); // 500 * 1000
    expect(res.totPot).toBe(600000); // 300k + 300k
    expect(res.sisa).toBe(0); // Max(0, 500k - 600k) = 0
  });

  test('4. Existing total_harga from database should override calculation', () => {
    const mockRow = {
      berat_bruto: 5000,
      berat_tara: 1000,
      persen_potongan: 0,
      harga_per_kg: 2000,
      total_harga: 9000000, // manual override, calculation would be 4000 * 2000 = 8000000
      potongan_jalan: 100000,
      potongan_pupuk_rp: 0,
      potongan_hutang_rp: 0
    };

    const res = calculateRowValues(mockRow);

    expect(res.totGross).toBe(9000000);
    expect(res.sisa).toBe(8900000); // 9000000 - 100000
  });

  test('5. Non-numeric inputs should handle gracefully with fallbacks', () => {
    const mockRow = {
      berat_bruto: 'invalid',
      berat_tara: null,
      persen_potongan: undefined,
      harga_per_kg: '2000abc',
      total_harga: '',
      potongan_jalan: '100000',
      potongan_pupuk_rp: null,
      potongan_hutang_rp: undefined
    };

    const res = calculateRowValues(mockRow);

    expect(res.bruto).toBe(0);
    expect(res.tara).toBe(0);
    expect(res.netto1).toBe(0);
    expect(res.netto2).toBe(0);
    expect(res.hrg).toBe(2000); // parseFloat parses '2000abc' as 2000
    expect(res.totGross).toBe(0);
    expect(res.totPot).toBe(100000); // parseFloat parses '100000' as 100000
    expect(res.sisa).toBe(0);
  });
});
