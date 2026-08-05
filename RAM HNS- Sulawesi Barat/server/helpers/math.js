// electron-app/server/helpers/math.js

/**
 * Calculate row values consistently for weighbridge transactions
 * @param {Object} row - Transaction row data
 * @returns {Object} Calculated metrics (bruto, tara, netto1, persen, kgPot, netto2, hrg, totGross, potJln, potPpk, potHut, totPot, sisa)
 */
function calculateRowValues(row) {
  const bruto = parseFloat(row.berat_bruto) || parseFloat(row.berat_timbangan1) || 0;
  const tara = parseFloat(row.berat_tara) || parseFloat(row.berat_timbangan2) || 0;
  const netto1 = bruto - tara;
  const persen = parseFloat(row.persen_potongan || 0);
  // Potongan kg dibulatkan ke kelipatan 10 terdekat (mis. 23 -> 20, 25 -> 30)
  const kgPot = Math.round((persen / 100) * netto1 / 10) * 10;
  const netto2 = Math.round(netto1 - kgPot);
  const hrg = parseFloat(row.harga_per_kg || 0);
  
  // Fallback to netto2 * hrg if total_harga is missing or <= 0
  const totGross = parseFloat(row.total_harga) > 0 ? parseFloat(row.total_harga) : Math.round(netto2 * hrg);
  
  const potJln = parseFloat(row.potongan_jalan || 0);
  const potPpk = parseFloat(row.potongan_pupuk_rp || 0);
  const potHut = parseFloat(row.potongan_hutang_rp || 0);
  const potHutSupplier = parseFloat(row.potongan_hutang_supplier_rp || 0);
  const potMuat = parseFloat(row.potongan_muat_rp || 0);
  const totPot = potJln + potPpk + potHut + potHutSupplier + potMuat;
  const sisa = Math.max(0, totGross - totPot);
  
  return {
    bruto, tara, netto1, persen, kgPot, netto2, hrg, totGross, potJln, potPpk, potHut, potHutSupplier, potMuat, totPot, sisa
  };
}

module.exports = {
  calculateRowValues
};
