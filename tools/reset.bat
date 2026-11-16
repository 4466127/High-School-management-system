@echo off
chcp 65001 >nul
rem Clear test data before the school starts real work (asks what to clear and to type the database name).
rem A full backup is always taken first; if it fails, nothing is deleted.
call "%~dp0_php.bat"
cd /d "%~dp0.."
"%PHP%" artisan school:reset %*
echo.
pause
