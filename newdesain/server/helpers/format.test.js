// electron-app/server/helpers/format.test.js
const { formatRupiah, formatNumber, formatWeight, cleanRupiahValue, formatDate, formatTime } = require('./format');

describe('format.js - Unit Tests', () => {
  test('formatRupiah()', () => {
    expect(formatRupiah(0)).toBe('Rp 0');
    expect(formatRupiah(1500000)).toBe('Rp 1.500.000');
    expect(formatRupiah(2500.67)).toBe('Rp 2.501'); // should round
    expect(formatRupiah(null)).toBe('Rp 0');
  });

  test('formatNumber()', () => {
    expect(formatNumber(1234567.89, 2)).toBe('1.234.567,89');
    expect(formatNumber(1234567.89, 0)).toBe('1.234.568'); // rounds up
    expect(formatNumber(0, 0)).toBe('0');
    expect(formatNumber(null, 2)).toBe('0');
  });

  test('formatWeight()', () => {
    expect(formatWeight(5678.9)).toBe('5.679'); // rounds
    expect(formatWeight(0)).toBe('0');
  });

  test('cleanRupiahValue()', () => {
    expect(cleanRupiahValue('Rp 1.500.000')).toBe(1500000);
    expect(cleanRupiahValue('Rp 250.000')).toBe(250000);
    expect(cleanRupiahValue('12345')).toBe(12345);
    expect(cleanRupiahValue('')).toBe(0);
    expect(cleanRupiahValue(null)).toBe(0);
  });

  test('formatDate()', () => {
    expect(formatDate('2026-06-16')).toBe('16/06/2026');
    expect(formatDate(null)).toBe('-');
  });

  test('formatTime()', () => {
    // Tests time format (HH:MM:SS)
    const dateStr = '2026-06-16T15:30:45';
    expect(formatTime(dateStr)).toMatch(/^\d{2}:\d{2}:\d{2}$/);
    expect(formatTime(null)).toBe('-');
  });
});
