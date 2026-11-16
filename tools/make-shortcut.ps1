# ساختن میان‌بر «سیستم مکتب» روی دسکتاپ (یک بار، هنگام نصب در مکتب).
# میان‌بر start.bat را اجرا می‌کند: XAMPP (Apache و MySQL) روشن، پشتیبان ماهانه اگر وقتش است، و صفحه‌ی ورود باز می‌شود.
# با -Startup میان‌بر در «شروع خودکار Windows» هم گذاشته می‌شود (سیستم با روشن شدن کامپیوتر خودش باز می‌شود).
# WScript.Shell نام فارسی را در Save خراب می‌کند؛ پس اول با نام انگلیسی ذخیره و بعد با PowerShell نام فارسی داده می‌شود.
# برای امتحان: -Folder <پوشه> (به جای دسکتاپ) و -NoUi (بی پنجره‌ی پیام).
param([switch]$Startup, [string]$Folder = '', [switch]$NoUi)

$target = Join-Path $PSScriptRoot 'start.bat'
$icon = Join-Path $PSScriptRoot 'maktab.ico'
$name = 'سیستم مکتب.lnk'
$shell = New-Object -ComObject WScript.Shell
$places = @()
if ($Folder -ne '') { $places += $Folder } else { $places += [Environment]::GetFolderPath('Desktop') }
if ($Startup) { $places += [Environment]::GetFolderPath('Startup') }
$failed = @()
foreach ($dir in $places) {
    $temp = Join-Path $dir 'maktab-school-temp.lnk'
    $final = Join-Path $dir $name
    try {
        $link = $shell.CreateShortcut($temp)
        $link.TargetPath = $target
        $link.WorkingDirectory = $PSScriptRoot
        $link.WindowStyle = 7  # کوچک (پنجره‌ی سیاه دیده نشود)
        if (Test-Path $icon) { $link.IconLocation = $icon + ',0' } else { $link.IconLocation = (Join-Path $env:SystemRoot 'System32\shell32.dll') + ',43' }
        $link.Description = 'School management system'
        $link.Save()
        Move-Item -LiteralPath $temp -Destination $final -Force -ErrorAction Stop
        if (Test-Path -LiteralPath $final) { Write-Output ('OK: ' + $final) } else { $failed += $final }
    } catch {
        $failed += $final
        Write-Output ('FAILED: ' + $final + ' - ' + $_.Exception.Message)
        if (Test-Path -LiteralPath $temp) { Remove-Item -LiteralPath $temp -Force -ErrorAction SilentlyContinue }
    }
}
if ($NoUi) { if ($failed.Count -gt 0) { exit 1 } else { exit 0 } }
Add-Type -AssemblyName System.Windows.Forms | Out-Null
if ($failed.Count -eq 0) {
    [System.Windows.Forms.MessageBox]::Show("میان‌بر «سیستم مکتب» روی دسکتاپ ساخته شد.`nهر صبح روی آن دو بار کلیک کنید؛ صفحه‌ی ورود باز می‌شود.", 'سیستم مکتب', 'OK', 'Information', 'Button1', 'RtlReading') | Out-Null
    exit 0
}
[System.Windows.Forms.MessageBox]::Show("میان‌بر ساخته نشد.`nعکس پنجره‌ی سیاه را به برنامه‌نویس بفرستید.", 'سیستم مکتب', 'OK', 'Error', 'Button1', 'RtlReading') | Out-Null
exit 1
