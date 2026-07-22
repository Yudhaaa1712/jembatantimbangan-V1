const readline = require('readline');
const { generateLicenseKey } = require('./server/helpers/license');

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

console.log("=============================================");
console.log("   🔑 WEIGHBRIDGE LICENSE GENERATOR 🔑");
console.log("=============================================\n");

rl.question("Masukkan Hardware ID klien: ", (hwid) => {
  if (!hwid.trim()) {
    console.log("Hardware ID tidak boleh kosong!");
    rl.close();
    return;
  }

  rl.question("Masa aktif (dalam hari) [Ketik 0 untuk permanen]: ", (daysInput) => {
    const days = parseInt(daysInput) || 0;
    
    try {
      const licenseKey = generateLicenseKey(hwid.trim(), days);
      
      console.log("\n=============================================");
      console.log("✅ BERHASIL! INI ADALAH KUNCI LISENSINYA:");
      console.log("=============================================\n");
      console.log(licenseKey);
      console.log("\n=============================================");
      console.log("Silakan copy (blok teks di atas lalu klik kanan) dan kirimkan ke klien.");
    } catch (err) {
      console.log("\n❌ Terjadi kesalahan saat membuat lisensi: ", err.message);
    }
    
    rl.close();
  });
});
