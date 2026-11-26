@echo off
chcp 65001 >nul
rem Apply an update AFTER copying the new program files (keep .env, storage and backups).
rem Takes a pre-update backup first; if the backup fails nothing is changed. See DEPLOYMENT.md.
call "%~dp0_php.bat"
cd /d "%~dp0.."
"%PHP%" artisan school:update
echo.
pause
