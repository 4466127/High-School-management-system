@echo off
chcp 65001 >nul
rem Restore from a backup: shows the list, asks which one, asks to type the database name.
rem A backup of the current state is taken first.   restore.bat "E:\maktab-backup\maktab-....zip"  restores that file.
call "%~dp0_php.bat"
cd /d "%~dp0.."
"%PHP%" artisan school:restore %*
echo.
pause
