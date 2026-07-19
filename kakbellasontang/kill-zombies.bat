@echo off
echo ==================================================
echo 🧹 CLEANING ZOMBIE PROCESSES FOR WEIGHBRIDGE APP
echo ==================================================
taskkill /F /IM node.exe /T 2>nul
taskkill /F /IM electron.exe /T 2>nul
taskkill /F /IM "Weighbridge - Arroyan Jv Teknik.exe" /T 2>nul
echo.
echo Proses pembersihan selesai! Silakan coba jalankan aplikasi kembali.
echo.
pause
