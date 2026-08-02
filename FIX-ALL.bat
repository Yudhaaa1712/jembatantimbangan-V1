@echo off
REM Double-click file ini untuk jalankan fix-all.ps1
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0fix-all.ps1"
pause
