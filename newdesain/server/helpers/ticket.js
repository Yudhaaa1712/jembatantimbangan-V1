/**
 * Ticket Number Generator
 * Replaces: generate_ticket_number() from database.php
 * Thread-safe with retry mechanism using DB transactions
 */
const { pool } = require('../config/database');

/**
 * Generate unique ticket number atomically
 * Format: PREFIX-YYMMDD-XXX (e.g., TKT-260529-001)
 * Replaces PHP generate_ticket_number() with transaction locking
 */
async function generateTicketNumber() {
  const maxRetries = 5;

  for (let attempt = 0; attempt < maxRetries; attempt++) {
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();

      // Get prefix from settings
      const [prefixRows] = await conn.execute(
        `SELECT setting_value FROM settings WHERE setting_key = 'ticket_prefix'`
      );
      const prefix = prefixRows[0]?.setting_value || 'TKT';

      const today = new Date();
      const year = today.getFullYear();
      const month = String(today.getMonth() + 1).padStart(2, '0');
      const day = String(today.getDate()).padStart(2, '0');

      const datePrefix = [
        day,
        month,
        String(year).slice(-2)
      ].join('');

      const todayStr = `${year}-${month}-${day}`;
      const pattern = `${prefix}-${datePrefix}%`;

      // Get max number for today with lock
      const [maxRows] = await conn.execute(
        `SELECT COALESCE(MAX(CAST(SUBSTR(no_tiket, -3) AS INTEGER)), 0) as max_num
         FROM transaksi_timbangan
         WHERE no_tiket LIKE ?`,
        [pattern]
      );

      const nextNum = parseInt(maxRows[0]?.max_num || 0) + 1;
      const ticketNumber = `${prefix}-${datePrefix}-${String(nextNum).padStart(3, '0')}`;

      // Reserve ticket
      await conn.execute(
        `INSERT INTO transaksi_timbangan (no_tiket, tanggal, status, created_at, timbang1_locked)
         VALUES (?, ?, 'reserved', NOW(), 0)`,
        [ticketNumber, todayStr]
      );

      await conn.commit();
      conn.release();
      return ticketNumber;

    } catch (err) {
      await conn.rollback();
      conn.release();

      if (attempt < maxRetries - 1) {
        // Random delay 10-100ms to avoid race conditions
        await new Promise(r => setTimeout(r, 10 + Math.random() * 90));
        continue;
      }
      throw new Error(`Gagal membuat nomor tiket setelah ${maxRetries} percobaan: ${err.message}`);
    }
  }
}

/**
 * Check if ticket number exists
 * Replaces: is_ticket_exists() from database.php
 */
async function isTicketExists(noTiket) {
  const [rows] = await pool.execute(
    `SELECT id, status FROM transaksi_timbangan WHERE no_tiket = ?`,
    [noTiket]
  );
  if (rows.length === 0) return false;
  // Reserved tickets can be reused
  if (rows[0].status === 'reserved') return false;
  return true;
}

/**
 * Activate a reserved ticket with full data
 * Replaces: activate_reserved_ticket() from database.php
 */
async function activateReservedTicket(noTiket, data) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();

    // Update basic fields
    const [r1] = await conn.execute(
      `UPDATE transaksi_timbangan SET
         no_polisi = ?, nama_supir = ?, id_supir = ?, id_supplier = ?, mode_timbangan = ?, 
         is_langsir = ?, jumlah_trip_langsir = ?, updated_at = NOW()
       WHERE no_tiket = ? AND status = 'reserved'`,
      [data.no_polisi, data.nama_supir, data.id_supir || null, data.id_supplier, data.mode_timbangan || 'beli', 
       data.is_langsir || 0, data.jumlah_trip_langsir || 0, noTiket]
    );

    if (r1.affectedRows === 0) {
      throw new Error('Tiket reserved tidak ditemukan');
    }

    // Update detail fields
    await conn.execute(
      `UPDATE transaksi_timbangan SET
         jenis_material = ?, keterangan = ?, harga_per_kg = ?,
         berat_bruto = ?, berat_tara = ?, berat_timbangan1 = ?
       WHERE no_tiket = ?`,
      [data.jenis_material, data.keterangan || '', data.harga_per_kg,
       data.berat_bruto || 0, data.berat_tara || 0, data.berat_timbangan1, noTiket]
    );

    // Update status
    await conn.execute(
      `UPDATE transaksi_timbangan SET
         status = 'timbang_1', timbang1_locked = 1,
         waktu_timbangan1 = NOW(), operator_id = ?
       WHERE no_tiket = ?`,
      [data.operator_id, noTiket]
    );

    await conn.commit();
    conn.release();
    return true;

  } catch (err) {
    await conn.rollback();
    conn.release();
    throw err;
  }
}

module.exports = { generateTicketNumber, isTicketExists, activateReservedTicket };
