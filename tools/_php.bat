@echo off
rem Find php.exe: XAMPP next to this project (..\..\..\php), then C:\xampp\php, then PATH.
set "PHP=%~dp0..\..\..\php\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"
