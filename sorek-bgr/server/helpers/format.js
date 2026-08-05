/**
 * Format Helpers
 * Replaces: format_rupiah(), format_number(), format_weight() from PHP
 */

function formatRupiah(number) {
  if (!number) return 'Rp 0';
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(number));
}

function formatNumber(number, decimals = 0) {
  if (number === null || number === undefined) return '0';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  }).format(number);
}

function formatWeight(weight) {
  return new Intl.NumberFormat('id-ID').format(Math.round(weight));
}

function cleanRupiahValue(str) {
  return parseInt(String(str).replace(/[^0-9]/g, '')) || 0;
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`;
}

function formatTime(dateStr) {
  if (!dateStr) return '-';
  if (typeof dateStr === 'string' && !dateStr.includes('-') && dateStr.includes(':')) {
    return dateStr;
  }
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '-';
  return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;
}

module.exports = { formatRupiah, formatNumber, formatWeight, cleanRupiahValue, formatDate, formatTime };
