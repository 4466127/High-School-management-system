@echo off
chcp 65001 >nul
rem Check the system (PHP, database, tables, folders, backup, Apache) and offer automatic fixes.
rem   check.bat         health check, then asks to fix
rem   check.bat /tests  run the automated tests (programmer only; needs database maktab_test)
call "%~dp0_php.bat"
cd /d "%~dp0.."
if /i "%~1"=="/tests" goto tests
"%PHP%" artisan school:check
if not errorlevel 1 goto end
echo.
choice /c YN /m "Fix automatically (Y) or not (N)?"
if errorlevel 2 goto end
echo.
"%PHP%" artisan school:check --fix
goto end
:tests
"%PHP%" artisan test
:end
echo.
pause
