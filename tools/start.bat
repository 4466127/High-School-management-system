@echo off
rem Start the school system with one click: Apache + MySQL, monthly backup if due, then open the login page (see start.ps1).
powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%~dp0start.ps1" %*
