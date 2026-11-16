# شروع سیستم مکتب با یک کلیک (دکمه‌ی «سیستم مکتب» روی دسکتاپ؛ مرحله‌ی ۱۰)
# ۱) اگر MySQL و Apache خاموش‌اند، روشن می‌کند (سرویس Windows اگر نصب است، وگرنه برنامه‌ی خود XAMPP، بی پنجره)
# ۲) منتظر می‌ماند تا پایگاه داده و سیستم جواب بدهند (هر کدام تا ۴۵ ثانیه)
# ۳) پشتیبان خودکار ماهانه: «php artisan school:backup --if-due» در پس‌زمینه (فقط اگر آخرین پشتیبان کهنه‌تر از ۳۰ روز باشد)
# ۴) صفحه‌ی ورود (login) را در مرورگر باز می‌کند. آدرس: APP_URL در .env (نصب شبکه) وگرنه http://localhost/<پوشه>/public/login
# اگر چیزی روشن نشد، پیام فارسی با راه حل نشان می‌دهد. برای امتحان بی مرورگر: -NoBrowser
param([switch]$NoBrowser)

$ErrorActionPreference = 'Continue'
$project = Split-Path -Parent $PSScriptRoot                    # ...\xampp\htdocs\<پوشه‌ی پروژه>
$xampp = Split-Path -Parent (Split-Path -Parent $project)      # ...\xampp
$php = Join-Path $xampp 'php\php.exe'
$title = 'سیستم مکتب'

Add-Type -AssemblyName System.Windows.Forms | Out-Null
function Show-Message([string]$text, [string]$icon = 'Information') {
    if ($NoBrowser) { Write-Output $text; return }
    [System.Windows.Forms.MessageBox]::Show($text, $title, 'OK', $icon, 'Button1', 'RtlReading') | Out-Null
}

# ---- آدرس صفحه‌ی ورود ----
$appUrl = ''
$envFile = Join-Path $project '.env'
if (Test-Path $envFile) {
    $line = Get-Content $envFile -Encoding UTF8 | Where-Object { $_ -match '^\s*APP_URL\s*=' } | Select-Object -First 1
    if ($line) { $appUrl = ($line -replace '^\s*APP_URL\s*=\s*', '').Trim().Trim('"').Trim("'").TrimEnd('/') }
}
if ($appUrl -ne '' -and $appUrl -notmatch ':8000$' -and $appUrl -notmatch '^https?://(localhost|127\.0\.0\.1)$') {
    $url = $appUrl + '/login'
} else {
    $url = 'http://localhost/' + [Uri]::EscapeDataString((Split-Path -Leaf $project)) + '/public/login'
}

function Test-Port([int]$port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try { return $client.ConnectAsync('127.0.0.1', $port).Wait(500) -and $client.Connected } catch { return $false } finally { $client.Close() }
}

# روشن کردن یک بخش: اگر روشن است هیچ؛ وگرنه سرویس Windows؛ وگرنه برنامه‌ی XAMPP (پنهان و جدا از این پنجره)
function Start-Part([string]$serviceName, [string]$processName, [string]$exe, [string]$arguments, [string]$workDir) {
    if (Get-Process -Name $processName -ErrorAction SilentlyContinue) { return 'running' }
    $service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    if ($service) {
        try { Start-Service -Name $serviceName -ErrorAction Stop; return 'service' } catch { }
    }
    if (-not (Test-Path $exe)) { return 'missing' }
    $startup = New-CimInstance -ClassName Win32_ProcessStartup -ClientOnly -Property @{ ShowWindow = [uint16]0 }
    $r = Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{
        CommandLine = ('"' + $exe + '" ' + $arguments).Trim(); CurrentDirectory = $workDir; ProcessStartupInformation = $startup
    }
    if ($r.ReturnValue -ne 0) { return 'failed' }
    return 'started'
}

function Wait-Until([scriptblock]$check, [int]$seconds) {
    $sw = [Diagnostics.Stopwatch]::StartNew()
    while ($sw.Elapsed.TotalSeconds -lt $seconds) {
        if (& $check) { return $true }
        Start-Sleep -Milliseconds 700
    }
    return $false
}

# ---- MySQL و Apache ----
$mysqlBin = Join-Path $xampp 'mysql\bin'
$mysql = Start-Part 'mysql' 'mysqld' (Join-Path $mysqlBin 'mysqld.exe') ('--defaults-file="' + (Join-Path $mysqlBin 'my.ini') + '" --standalone') (Join-Path $xampp 'mysql')
$apache = Start-Part 'Apache2.4' 'httpd' (Join-Path $xampp 'apache\bin\httpd.exe') '' (Join-Path $xampp 'apache')

if ($mysql -eq 'missing' -or $apache -eq 'missing') {
    Show-Message ("XAMPP در این جا پیدا نشد: $xampp`n`nپوشه‌ی سیستم باید داخل C:\xampp\htdocs باشد. راهنمای نصب (README بخش ۵) را ببینید.") 'Error'
    exit 1
}

if (-not (Wait-Until { Test-Port 3306 } 45)) {
    Show-Message ("پایگاه داده (MySQL) روشن نشد.`n`nاین معمولاً بعد از قطع ناگهانی برق پیش می‌آید. به ترتیب:`n" +
        "۱) در پوشه‌ی سیستم، پوشه‌ی tools را باز کنید و روی repair-mysql.bat دو بار کلیک کنید.`n" +
        "۲) اگر نشد: کامپیوتر را یک بار خاموش و روشن کنید و دوباره همین دکمه را بزنید.`n" +
        "۳) اگر باز هم نشد: در XAMPP Control Panel کنار MySQL دکمه‌ی Logs را بزنید و عکسش را به برنامه‌نویس بفرستید.`n`n" +
        "اطلاعات گم نمی‌شود؛ پشتیبان ماهانه در پوشه‌ی backups هست.") 'Error'
    exit 2
}

$web = { try { $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5; return ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500) } catch { return $false } }
if (-not (Wait-Until $web 45)) {
    Show-Message ("سیستم در مرورگر جواب نداد ($url).`n`n" +
        "۱) در پوشه‌ی tools روی check.bat دو بار کلیک کنید؛ مشکل را نشان می‌دهد و اگر بشود خودش رفع می‌کند.`n" +
        "۲) اگر نشد: کامپیوتر را یک بار خاموش و روشن کنید و دوباره همین دکمه را بزنید.`n" +
        "۳) در XAMPP Control Panel کنار Apache و MySQL باید سبز باشد؛ اگر سرخ است عکسش را به برنامه‌نویس بفرستید.") 'Error'
    exit 3
}

# ---- پشتیبان خودکار ماهانه (در پس‌زمینه؛ مرورگر منتظر نمی‌ماند) ----
if (Test-Path $php) {
    $log = Join-Path $project 'storage\logs\auto-backup.log'
    $startup = New-CimInstance -ClassName Win32_ProcessStartup -ClientOnly -Property @{ ShowWindow = [uint16]0 }
    Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{
        CommandLine = 'cmd.exe /c ""' + $php + '" artisan school:backup --if-due --no-ansi >> "' + $log + '" 2>&1"'
        CurrentDirectory = $project; ProcessStartupInformation = $startup
    } | Out-Null
}

if ($NoBrowser) {
    Write-Output "OK mysql=$mysql apache=$apache url=$url"
} else {
    Start-Process $url
}
exit 0
