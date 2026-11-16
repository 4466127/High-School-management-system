@echo off
chcp 65001 >nul
rem Take a full backup now (database + photos/documents) into the backups folder. Nothing is deleted.
rem   backup.bat E:\maktab-backup   also copies it to that folder (e.g. a USB flash drive)
call "%~dp0_php.bat"
cd /d "%~dp0.."
if "%~1"=="" (
  "%PHP%" artisan school:backup
) else (
  "%PHP%" artisan school:backup --to="%~1"
)
echo.
pause
