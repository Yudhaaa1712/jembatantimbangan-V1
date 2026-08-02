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

module.exports = {
  recalculateKasBalances
};
