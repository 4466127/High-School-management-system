@echo off
chcp 65001 >nul
rem New school installation: create tables and base data, then print a one-time setup code.
rem Open http://<server>/setup in a browser and enter the code to create the first administrator.
rem Safe to run again: existing data is never changed. See DEPLOYMENT.md.
call "%~dp0_php.bat"
cd /d "%~dp0.."
"%PHP%" artisan school:install %*
echo.
pause
