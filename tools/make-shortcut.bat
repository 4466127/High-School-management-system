@echo off
rem Create the desktop shortcut "School system" (once, at install). Add -Startup to also start it with Windows.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0make-shortcut.ps1" %*
