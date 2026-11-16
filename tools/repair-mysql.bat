@echo off
rem Repair MySQL system tables when MySQL will not start (usually after a power cut). School data is not touched. See repair-mysql.ps1
powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%~dp0repair-mysql.ps1" %*
