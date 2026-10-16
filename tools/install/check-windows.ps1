# Проверка установки SquirrelStock на Windows (docs/INSTALL_WINDOWS.ru.md, раздел 9): службы и их автозапуск, задача
# резервной копии, база не слушает сеть, заголовки безопасности, закрытые служебные адреса, нужные страницам файлы,
# ошибки PHP не показываются, cookie сессии. Только чтение: GET-запросы к сайту и сведения о службах. Вход не проверяется.
# Это часть tools/release/check-site.sh для локальной установки без HTTPS; сам check-site.sh можно запустить с другого
# компьютера (bash и curl) по адресу в локальной сети.
#
#   powershell -ExecutionPolicy Bypass -File C:\SquirrelStock\check-windows.ps1
#   powershell -ExecutionPolicy Bypass -File C:\SquirrelStock\check-windows.ps1 -Url http://192.168.1.10
#   ... -SkipServices     только сайт (например, с другого компьютера)
#
# Код выхода: 0 — ошибок нет (предупреждения возможны), 1 — есть ошибки.
# Файл в UTF-8 с BOM: без BOM Windows PowerShell 5.1 читает русский текст в кодировке ANSI.
param(
    [string]$Url = 'http://localhost',
    [string]$ApacheService = 'Apache2.4',
    [string]$DbService = 'MariaDB',
    [string]$Task = 'SquirrelStock backup',
    [switch]$SkipServices
)

$ErrorActionPreference = 'Stop'
$base = $Url.TrimEnd('/')
$script:ok = 0; $script:err = 0; $script:warn = 0
function OK($m)   { $script:ok++;   Write-Host "OK              $m" }
function ERR($m)  { $script:err++;  Write-Host "ОШИБКА          $m" -ForegroundColor Red }
function WARN($m) { $script:warn++; Write-Host "ПРЕДУПРЕЖДЕНИЕ  $m" -ForegroundColor Yellow }
function Section($m) { Write-Host ''; Write-Host "== $m" }

# GET без перехода по перенаправлениям; ответы 4xx и 5xx — не исключение, а код. Status 0 — сайт не ответил.
function Get-Page([string]$path) {
    $req = [System.Net.HttpWebRequest]::Create("$base/$path")
    $req.AllowAutoRedirect = $false
    $req.Timeout = 30000
    $req.CookieContainer = New-Object System.Net.CookieContainer
    try { $resp = $req.GetResponse() }
    catch [System.Net.WebException] {
        $resp = $_.Exception.Response
        if ($null -eq $resp) { return @{ Status = 0; Headers = @{}; Body = ''; Error = $_.Exception.Message } }
    }
    $reader = New-Object System.IO.StreamReader($resp.GetResponseStream(), [System.Text.Encoding]::UTF8)
    $body = $reader.ReadToEnd()
    $reader.Close()
    $h = @{}
    foreach ($k in $resp.Headers.AllKeys) { $h[$k] = $resp.Headers[$k] }
    $r = @{ Status = [int]$resp.StatusCode; Headers = $h; Body = $body; Error = '' }
    $resp.Close()
    return $r
}

if (-not $SkipServices) {
    Section '1. Службы и резервная копия'
    foreach ($name in @($ApacheService, $DbService)) {
        $s = Get-Service -Name $name -ErrorAction SilentlyContinue
        if ($null -eq $s) { ERR "служба $name не найдена (другое имя — параметры -ApacheService, -DbService)"; continue }
        if ($s.Status -eq 'Running') { OK "служба $name работает" } else { ERR "служба ${name}: $($s.Status)" }
        # StartType есть у Get-Service начиная с PowerShell 5.0
        if ("$($s.StartType)" -eq 'Automatic') { OK "служба $name запускается при включении компьютера" }
        else { ERR "служба ${name}: тип запуска $($s.StartType), нужен «Автоматически» (Set-Service $name -StartupType Automatic)" }
    }
    $t = Get-ScheduledTask -TaskName $Task -ErrorAction SilentlyContinue
    if ($null -eq $t) { ERR "нет задачи Планировщика «$Task» (INSTALL_WINDOWS, раздел 7)" }
    else {
        $i = $t | Get-ScheduledTaskInfo
        if ($t.State -eq 'Disabled') { ERR "задача «$Task» отключена" } else { OK "задача «$Task» есть, пользователь $($t.Principal.UserId)" }
        if ($i.LastRunTime -lt (Get-Date '2000-01-01')) { WARN "задача «$Task» ещё не запускалась: Start-ScheduledTask '$Task'" }
        elseif ($i.LastTaskResult -eq 0) { OK "последний запуск задачи $($i.LastRunTime): успешно" }
        else { ERR "последний запуск задачи $($i.LastRunTime): код $($i.LastTaskResult) — подробности на странице «Резервная копия»" }
    }

    Section '2. База данных не слушает сеть'
    $l = @(Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue)
    if ($l.Count -eq 0) { WARN 'на порту 3306 никто не слушает: база на другом порту или служба остановлена' }
    else {
        $open = @($l | Where-Object { $_.LocalAddress -notin @('127.0.0.1', '::1') })
        if ($open.Count -eq 0) { OK 'MariaDB слушает только 127.0.0.1' }
        else { ERR "MariaDB слушает $(($open.LocalAddress | Sort-Object -Unique) -join ', '): в my.ini нужен bind-address=127.0.0.1" }
    }
}

Section "3. Заголовки безопасности ($base)"
$r = Get-Page 'index.php?p=App/Pages/UserLogin'
if ($r.Status -eq 0) { ERR "сайт не отвечает: $($r.Error)"; Write-Host ''; Write-Host "Итого: OK $ok, ошибок $err, предупреждений $warn"; exit 1 }
if ($r.Status -ne 200) { ERR "страница входа ответила $($r.Status)" }
else {
    $csp = $r.Headers['Content-Security-Policy']
    if ($csp -like "*default-src 'self'*" -and $csp -like '*frame-ancestors*') { OK 'Content-Security-Policy' }
    else { ERR "нет Content-Security-Policy из .htaccess: не включён mod_headers или AllowOverride (INSTALL_WINDOWS, раздел 5)" }
    $xfo = "$($r.Headers['X-Frame-Options'])".ToUpper()
    if ($xfo -match 'SAMEORIGIN|DENY') { OK "X-Frame-Options: $xfo" } else { ERR "X-Frame-Options: нет" }
    if ("$($r.Headers['X-Content-Type-Options'])" -match 'nosniff') { OK 'X-Content-Type-Options: nosniff' } else { ERR 'X-Content-Type-Options: нет' }
    if ($r.Headers['Referrer-Policy']) { OK "Referrer-Policy: $($r.Headers['Referrer-Policy'])" } else { ERR 'Referrer-Policy: нет' }
    if ($r.Headers['X-Powered-By']) { ERR "X-Powered-By: $($r.Headers['X-Powered-By']) — версия PHP видна (expose_php = Off в php.ini)" } else { OK 'нет X-Powered-By' }
    $srv = "$($r.Headers['Server'])"
    if ($srv -match '/\d') { WARN "Server: $srv — видна версия (ServerTokens Prod в httpd.conf)" } else { OK "Server: $srv" }
    $ck = "$($r.Headers['Set-Cookie'])"
    if ($ck -eq '') { WARN 'страница входа не выдала cookie сессии' }
    else {
        if ($ck -match ';\s*httponly') { OK 'cookie сессии: HttpOnly' } else { ERR 'у cookie сессии нет HttpOnly (session.cookie_httponly = On)' }
        if ($ck -match ';\s*samesite=(lax|strict)') { OK 'cookie сессии: SameSite' } else { ERR 'у cookie сессии нет SameSite (session.cookie_samesite = Lax)' }
    }
}

Section '4. Служебные файлы закрыты'
$closed = @('config/config.php', 'config/', 'app/system.php', 'app/', 'templates/pages/base.html', 'templates/', 'logs/', 'cli/',
    'cli/backup.php', 'vendor/autoload.php', 'vendor/composer/installed.json', 'composer.json', 'composer.lock', 'init.php',
    'lang/ru.json', 'lang/uk.json', 'upload/', 'upload/sqs-check.txt', 'upload/sqs-check.php', '.htaccess', 'upload/.htaccess')
$bad = 0
foreach ($p in $closed) {
    $c = (Get-Page $p).Status
    if ($c -eq 403 -or $c -eq 404) { continue }
    $bad++
    if ($c -eq 200) { ERR "/$p отдаётся (200), должен быть 403 или 404: проверьте AllowOverride All и блоки <Directory> в httpd.conf" }
    else { WARN "/${p}: ответ $c (ждали 403 или 404)" }
}
if ($bad -eq 0) { OK "$($closed.Count) служебных адресов отвечают 403 или 404" }
$bad = 0
foreach ($p in @('assets/css/app.css', 'vendor/leon-mbs/zippy/assets/css/zippy-bundle.css', 'vendor/leon-mbs/zippy/assets/js/jquery.js')) {
    $c = (Get-Page $p).Status
    if ($c -ne 200) { $bad++; ERR "/${p}: $c — страницам нужен этот файл" }
}
if ($bad -eq 0) { OK 'файлы, нужные страницам (css, js), открываются' }

Section '5. Ошибки PHP не показываются'
$leak = '[A-Za-z]:\\|/var/www|on line <b>|on line [0-9]+|Stack trace|Fatal error|Uncaught |Warning</b>:|Notice</b>:|Deprecated</b>:'
$bad = 0
foreach ($p in @('sqs-check-no-such-page', 'sqs-check-no-such.php', 'index.php?p=App/Pages/SqsCheckNoSuch')) {
    $b = (Get-Page $p).Body
    if ($b -match $leak) { $bad++; ERR "/${p}: в ответе «$($Matches[0])» — display_errors = Off в php.ini" }
}
if ($bad -eq 0) { OK 'несуществующие страницы не показывают путей и текста ошибок PHP' }

Write-Host ''
Write-Host "Итого: OK $ok, ошибок $err, предупреждений $warn"
if ($err -gt 0) { exit 1 } else { exit 0 }
