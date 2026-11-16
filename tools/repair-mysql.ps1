# تعمیر MySQL وقتی روشن نمی‌شود (معمولاً بعد از قطع ناگهانی برق؛ مرحله‌ی ۱۰)
# فقط جدول‌های داخلی خود MySQL (پوشه‌ی data\mysql: کاربران و اجازه‌ها) را تعمیر می‌کند؛
# به اطلاعات مکتب (پایگاه داده‌ی maktab) دست نمی‌زند.
#   ۱) اگر MySQL سالم روشن است، هیچ کاری نمی‌کند.
#   ۲) اگر برنامه‌ی دیگری پورت 3306 را گرفته است، نامش را نشان می‌دهد (تعمیر لازم نیست).
#   ۳) پیش از هر تغییر: کاپی کامل پوشه‌ی data\mysql در backups\mysql-system-<وقت> (کنار پروژه)
#   ۴) هر جدول خراب: aria_chk --recover؛ اگر نشد، کاپی سالم همان جدول از xampp\mysql\backup\mysql (همراه XAMPP)
#   ۵) MySQL را روشن و امتحان می‌کند؛ نتیجه با پیام فارسی و فایل گزارش storage\logs\repair-mysql-<وقت>.txt
# اجرا: دو بار کلیک روی repair-mysql.bat. (برای امتحان روی پایگاه داده‌ی دیگر: -DataDir ... -Port ... -NoUi)
param([string]$DataDir = '', [int]$Port = 3306, [switch]$NoUi)

$ErrorActionPreference = 'Continue'
$project = Split-Path -Parent $PSScriptRoot
$xampp = Split-Path -Parent (Split-Path -Parent $project)
if ($DataDir -eq '') { $DataDir = Join-Path $xampp 'mysql\data' }
$bin = Join-Path $xampp 'mysql\bin'
$cleanDir = Join-Path $xampp 'mysql\backup\mysql'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$logDir = Join-Path $project 'storage\logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$log = Join-Path $logDir "repair-mysql-$stamp.txt"
$title = 'تعمیر MySQL — سیستم مکتب'

function Write-Log([string]$t) { Add-Content -Path $log -Value $t -Encoding UTF8; if ($NoUi) { Write-Output $t } }
Add-Type -AssemblyName System.Windows.Forms | Out-Null
function Show-Message([string]$text, [string]$icon = 'Information') {
    if ($NoUi) { Write-Output $text; return }
    [System.Windows.Forms.MessageBox]::Show($text, $title, 'OK', $icon, 'Button1', 'RtlReading') | Out-Null
}
function Test-Port {
    $c = New-Object System.Net.Sockets.TcpClient
    try { return $c.ConnectAsync('127.0.0.1', $Port).Wait(500) -and $c.Connected } catch { return $false } finally { $c.Close() }
}
# MySQL سالم = پورت جواب می‌دهد و بعد از ۴ ثانیه هم هنوز جواب می‌دهد (MySQL خراب چند لحظه بعد خودش بسته می‌شود)
function Test-Healthy { if (-not (Test-Port)) { return $false }; Start-Sleep -Seconds 4; return (Test-Port) }
function Start-MySql {
    $service = Get-Service -Name 'mysql' -ErrorAction SilentlyContinue
    if ($service) {
        try { Start-Service -Name 'mysql' -ErrorAction Stop; return } catch { }
    }
    $ini = Join-Path $bin 'my.ini'
    if (-not (Test-Path $ini)) { $ini = Join-Path $DataDir 'my.ini' }
    $startup = New-CimInstance -ClassName Win32_ProcessStartup -ClientOnly -Property @{ ShowWindow = [uint16]0 }
    Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{
        CommandLine = '"' + (Join-Path $bin 'mysqld.exe') + '" --defaults-file="' + $ini + '" --standalone'
        CurrentDirectory = (Split-Path -Parent $bin); ProcessStartupInformation = $startup
    } | Out-Null
}
function Invoke-AriaChk([string[]]$arguments) {
    $out = (& (Join-Path $bin 'aria_chk.exe') @arguments 2>&1 | ForEach-Object { "$_" }) -join "`n"
    return @{ Code = $LASTEXITCODE; Out = $out }
}

Write-Log "repair-mysql $stamp  data=$DataDir  port=$Port"
if (Test-Healthy) {
    Write-Log 'MySQL is healthy; nothing to do.'
    Show-Message "MySQL سالم است و روشن است؛ تعمیر لازم نیست.`n`nاگر سیستم باز هم کار نمی‌کند، tools\check.bat را اجرا کنید."
    exit 0
}

# برنامه‌ی دیگری (نه MySQL خود XAMPP) پورت را گرفته است؟
try {
    $owner = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction Stop | Select-Object -First 1
    if ($owner) {
        $proc = Get-Process -Id $owner.OwningProcess -ErrorAction SilentlyContinue
        if ($proc -and $proc.Name -ne 'mysqld') {
            Write-Log "port $Port is used by $($proc.Name) ($($proc.Path))"
            Show-Message ("پورت $Port را برنامه‌ی دیگری گرفته است: $($proc.Name)`n$($proc.Path)`n`nآن برنامه (یا سرویس MySQL دیگر) را ببندید و دوباره دکمه‌ی «سیستم مکتب» را بزنید. تعمیر لازم نیست.") 'Warning'
            exit 3
        }
    }
} catch { }

# MySQL خراب که شاید هنوز در حال بسته شدن است
Get-Process -Name mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

$sysDir = Join-Path $DataDir 'mysql'
if (-not (Test-Path $sysDir)) {
    Show-Message "پوشه‌ی پایگاه داده‌ی MySQL پیدا نشد: $sysDir" 'Error'
    exit 1
}
# ---- پشتیبان پیش از تغییر ----
$backup = Join-Path $project "backups\mysql-system-$stamp"
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $backup) | Out-Null
Copy-Item -Path $sysDir -Destination $backup -Recurse -Force
Get-ChildItem -Path $DataDir -Filter 'aria_log*' | Copy-Item -Destination $backup -Force
Write-Log "backup: $backup"

# ---- بررسی و تعمیر هر جدول Aria (از پوشه‌ی data، تا aria_log_control دیده شود) ----
Push-Location $DataDir
$fixed = @(); $restored = @(); $failed = @()
foreach ($f in Get-ChildItem -Path $sysDir -Filter '*.MAI') {
    $name = 'mysql\' + $f.BaseName
    $check = Invoke-AriaChk @('--check', '--read-only', '--silent', $name)
    if ($check.Code -eq 0) { continue }
    Write-Log "damaged: $name`n$($check.Out.Trim())"
    $rep = Invoke-AriaChk @('--recover', $name)
    $again = Invoke-AriaChk @('--check', '--read-only', '--silent', $name)
    if ($rep.Code -eq 0 -and $again.Code -eq 0) { $fixed += $f.BaseName; Write-Log "recovered: $name"; continue }
    # تعمیر نشد ← کاپی سالم همان جدول از XAMPP (فقط جدول‌های داخلی MySQL؛ همان چیزی که XAMPP روز نصب دارد)
    $ok = $true
    foreach ($ext in @('frm', 'MAD', 'MAI')) {
        $src = Join-Path $cleanDir ($f.BaseName + '.' + $ext)
        if (Test-Path $src) { Copy-Item -Path $src -Destination (Join-Path $sysDir ($f.BaseName + '.' + $ext)) -Force } else { $ok = $false }
    }
    if ($ok) { Invoke-AriaChk @('--zerofill', $name) | Out-Null }
    $again = Invoke-AriaChk @('--check', '--read-only', '--silent', $name)
    if ($ok -and $again.Code -eq 0) { $restored += $f.BaseName; Write-Log "restored from XAMPP copy: $name" } else { $failed += $f.BaseName; Write-Log "FAILED: $name`n$($again.Out.Trim())" }
}
Pop-Location

# ---- روشن کردن و امتحان ----
Start-MySql
$up = $false
for ($i = 0; $i -lt 20 -and -not $up; $i++) { Start-Sleep -Seconds 1; $up = Test-Healthy }
Write-Log ("fixed: " + ($fixed -join ', ') + " | restored: " + ($restored -join ', ') + " | failed: " + ($failed -join ', ') + " | mysql up: $up")

if ($up) {
    $msg = "MySQL تعمیر شد و روشن است. اطلاعات مکتب دست نخورد.`n`n"
    if ($fixed.Count -gt 0) { $msg += "تعمیر شد: " + ($fixed -join '، ') + "`n" }
    if ($restored.Count -gt 0) { $msg += "از کاپی سالم XAMPP: " + ($restored -join '، ') + "`n" }
    if ($restored -contains 'global_priv') { $msg += "`nتوجه: جدول کاربران MySQL از نو شد؛ اگر برای MySQL رمز گذاشته بودید، رمز دوباره لازم است (README بخش شبکه).`n" }
    $msg += "`nپشتیبان پیش از تعمیر: $backup`nحالا دکمه‌ی «سیستم مکتب» را بزنید و برای اطمینان tools\check.bat را هم اجرا کنید."
    Show-Message $msg
    exit 0
}
Show-Message ("تعمیر کامل نشد و MySQL روشن نشد.`n`nاطلاعات مکتب در پشتیبان ماهانه (پوشه‌ی backups) هست. این فایل گزارش را به برنامه‌نویس بفرستید:`n$log`n`nپشتیبان پوشه‌ی MySQL پیش از تعمیر: $backup") 'Error'
exit 2
