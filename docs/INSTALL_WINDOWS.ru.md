# Установка SquirrelStock на Windows

Для отдельного компьютера на складе с Windows 10 или 11, в том числе без интернета. Ставятся три программы с официальных сайтов: веб-сервер Apache, PHP и база MariaDB, все три — службами Windows, которые запускаются при включении компьютера. Пользователю ничего запускать не нужно: он открывает ярлык в браузере. С телефонов на складе программа открывается по локальной сети (раздел 8).

Пометки: **[проверено]** — сделано на практике (что именно — сказано рядом); **[из кода]** — так устроена программа; **[не проверено]** — по документации Apache, PHP, MariaDB и Microsoft. **На Windows установка целиком ещё не проходилась**: все шаги, кроме отмеченных, — **[не проверено]**. Скрипт проверки `tools/install/check-windows.ps1` запускался в Windows PowerShell 5.1 (раздел 9).

Это та же схема, что у Docker-стенда, установщика Linux ([INSTALL_LINUX.ru.md](INSTALL_LINUX.ru.md)) и хостинга ([INSTALL_HOSTING.ru.md](INSTALL_HOSTING.ru.md)): Apache с модулем PHP читает `.htaccess` программы, поэтому служебные папки закрыты так же. Встроенный сервер PHP и nginx `.htaccess` не читают, с ними файл с паролем базы был бы виден из браузера.

## 1. Что нужно

- 1.1. **Компьютер.** Windows 10 22H2 или Windows 11, 64-битная. Памяти — от 2 ГБ, лучше 4 ГБ: Apache с PHP и MariaDB вместе занимают несколько сотен мегабайт, остальное — Windows и браузер. Диск — 1 ГБ на программы и сайт плюс место для резервных копий (по умолчанию 30 штук). Лучше SSD. [не проверено]
- 1.2. **Права администратора** на время установки: службы, Брандмауэр, установщики. Для работы потом права не нужны.
- 1.3. **Версии.** PHP **8.4** (Thread Safe, x64) и MariaDB **11.4** (LTS). Пока программа не переведена на них, ставьте PHP **8.2** и MariaDB **10.11**: на них программа проверена (Docker-стенд, автотесты). Поддержка безопасности PHP 8.2 заканчивается 31.12.2026, PHP 8.4 — 31.12.2028; MariaDB 10.11 поддерживается до 16.02.2028, 11.4 — до 29.05.2029 (php.net, mariadb.org).
- 1.4. **Файлы.** Скачайте их на любом компьютере с интернетом и перенесите на флешке. На складе интернет не нужен: программа работает без внешних запросов [из кода].

| Что | Откуда | Файл (на 16.10.2026) |
|---|---|---|
| PHP 8.4, Thread Safe, x64 | https://windows.php.net/download/ (архив выпусков: https://downloads.php.net/~windows/releases/) | `php-8.4.<N>-Win32-vs17-x64.zip`; до перехода — `php-8.2.<N>-Win32-vs16-x64.zip` |
| Apache 2.4 Win64 | https://www.apachelounge.com/download/ (на эту сборку ссылается документация Apache, раздел «Downloading Apache for Windows») | `httpd-2.4.<N>-<дата>-Win64-VS18.zip` |
| Visual C++ Redistributable x64 (нужен Apache и PHP) | https://aka.ms/vc14/vc_redist.x64.exe (ссылка со страницы Apache Lounge) | `vc_redist.x64.exe` |
| MariaDB 11.4, Windows x86_64, MSI | https://mariadb.org/download/ | `mariadb-11.4.<N>-winx64.msi`; до перехода — 10.11 |
| rclone (только для копий в облако) | https://rclone.org/downloads/ | `rclone-v<N>-windows-amd64.zip` |
| SquirrelStock | `tools/release/build.sh` ([INSTALL_HOSTING.ru.md](INSTALL_HOSTING.ru.md), раздел 1) | `squirrel-stock-<версия>.zip`, `-empty.sql` (или `-demo.sql`), `.sha256` |
| Проверка установки | этот репозиторий | `tools/install/check-windows.ps1` (раздел 9) |

Для PHP нужна именно сборка **Thread Safe (TS)**: «if you want to use the Apache HTTP Server, use the Thread Safe (TS) builds» (php.net). Контрольные суммы SHA-256 php.net публикует рядом со ссылками, у пакета SquirrelStock они в файле `.sha256`. Сверка: `Get-FileHash -Algorithm SHA256 <файл>`.

Пароль `admin` для баз пакета `build.sh` печатает при сборке — запишите его.

## 2. Подготовка

Все команды ниже — в **PowerShell от имени администратора** (Пуск → PowerShell → «Запуск от имени администратора»). Папка с файлами с флешки в примерах — `D:\install`.

```powershell
cd D:\install
Get-ChildItem | Unblock-File                      # снять пометку «скачано из интернета», иначе Windows может не дать запустить
Get-FileHash -Algorithm SHA256 *.zip, *.msi, *.sql # сверить с суммами на сайтах и в .sha256
[Environment]::Is64BitOperatingSystem             # True
Get-NetTCPConnection -LocalPort 80,3306 -State Listen -ErrorAction SilentlyContinue   # пусто — порты свободны
New-Item -ItemType Directory C:\SquirrelStock, C:\SquirrelStock\tmp, C:\SquirrelStock\squirrelstock-backup | Out-Null
.\vc_redist.x64.exe /install /quiet /norestart
```

Если порт 80 занят (IIS, другая веб-программа), используйте другой, например 8080: в `Listen` (раздел 5) и в адресе сайта.

Раскладка (все пути латиницей и без пробелов: с кириллицей в путях Apache и MariaDB не проверялись):

```
C:\php\                                  PHP
C:\SquirrelStock\Apache24\               Apache
C:\SquirrelStock\MariaDB\                MariaDB (данные — в data\)
C:\SquirrelStock\www\                    сайт
C:\SquirrelStock\squirrelstock-backup\   резервные копии (так программа называет папку по умолчанию: рядом с папкой сайта)
C:\SquirrelStock\tmp\                    временные файлы и сессии PHP
C:\SquirrelStock\rclone\                 rclone.exe и rclone.conf (если копии в облако)
```

## 3. MariaDB

```powershell
$rootpw = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 20 | ForEach-Object { [char]$_ })
"root MariaDB: $rootpw"                            # запишите
msiexec /i D:\install\mariadb-11.4.<N>-winx64.msi INSTALLDIR=C:\SquirrelStock\MariaDB SERVICENAME=MariaDB PASSWORD=$rootpw PORT=3306 BUFFERPOOLSIZE=256 REMOVE=HeidiSQL /qn | Out-Null
Get-Service MariaDB                                # Running
```

- Служба создаётся, только если задан `SERVICENAME`. `BUFFERPOOLSIZE` — память под данные в мегабайтах (по умолчанию 1/8 ОЗУ). HeidiSQL — графический клиент из комплекта, для работы не нужен (документация MariaDB, «Installing MariaDB MSI Packages on Windows»). Вместо тихой установки можно пройти мастер установщика с теми же значениями.
- База должна слушать только этот компьютер. В `C:\SquirrelStock\MariaDB\data\my.ini` в раздел `[mysqld]` добавить строку `bind-address=127.0.0.1`, затем `Restart-Service MariaDB`. Сеть совсем не выключать (`SKIPNETWORKING`): PHP подключается к базе по TCP.

База и пользователь программы (как в `tools/install/linux.sh`):

```powershell
$dbpw = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 20 | ForEach-Object { [char]$_ })
$mysql = 'C:\SquirrelStock\MariaDB\bin\mysql.exe'
& $mysql -u root "-p$rootpw" -e "CREATE DATABASE squirrelstock CHARACTER SET utf8 COLLATE utf8_general_ci; CREATE USER 'squirrelstock'@'localhost' IDENTIFIED BY '$dbpw'; GRANT ALL PRIVILEGES ON squirrelstock.* TO 'squirrelstock'@'localhost';"
cmd /c "$mysql -u root -p$rootpw --default-character-set=utf8mb4 squirrelstock < D:\install\squirrel-stock-<версия>-empty.sql"
& $mysql -u root "-p$rootpw" squirrelstock -e "SHOW TRIGGERS" | Select-String entrylist   # два триггера: без них не ведутся остатки
```

Перенаправление `<` в PowerShell не работает, поэтому загрузка дампа — через `cmd /c` (путь к `mysql.exe` без пробелов, кавычки не нужны). Вместо пустой базы можно загрузить `-demo.sql` с вымышленными данными, чтобы посмотреть программу.

## 4. PHP

```powershell
Expand-Archive D:\install\php-8.4.<N>-Win32-vs17-x64.zip C:\php
Copy-Item C:\php\php.ini-production C:\php\php.ini
[Environment]::SetEnvironmentVariable('Path', [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';C:\php', 'Machine')
```

`C:\php` в системной переменной `Path` нужна, чтобы модуль PHP в Apache нашёл библиотеки расширений curl и intl. Службы видят новый `Path` после перезагрузки компьютера (руководство PHP, установка на Windows) [не проверено].

В `C:\php\php.ini` раскомментировать (убрать `;` в начале строки) или дописать:

```ini
extension_dir = "ext"
extension=curl
extension=fileinfo
extension=gd
extension=gmp
extension=intl
extension=mbstring
extension=mysqli
extension=openssl
extension=zip
zend_extension=opcache
opcache.enable=1

; как на Docker-стенде (docker/php.ini)
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
date.timezone = Europe/Minsk
display_errors = Off
log_errors = On
expose_php = Off

session.save_path = "C:\SquirrelStock\tmp"
upload_tmp_dir = "C:\SquirrelStock\tmp"
```

`session.save_path` и `upload_tmp_dir` заданы явно: иначе PHP берёт временную папку пользователя, а в профиле с кириллическим именем (`C:\Users\Склад\…`) это лишний риск.

Проверка:

```powershell
C:\php\php.exe -v
C:\php\php.exe -m                                  # в списке: curl, gd, gmp, intl, mbstring, mysqli, openssl, zip, Zend OPcache
C:\php\php.exe -r "var_dump(defined('ZipArchive::EM_AES_256'));"   # bool(true) — шифрование копии в облако
```

Последняя строка нужна для облачных копий: их программа шифрует средствами zip (AES-256) [из кода]. Есть ли это в сборке PHP для Windows, [не проверено]. Если `false`, копии в папку и в сетевую папку работают, а в облако программа их не отправит.

## 5. Apache

```powershell
Expand-Archive D:\install\httpd-2.4.<N>-<дата>-Win64-VS18.zip D:\install\apache
Move-Item D:\install\apache\Apache24 C:\SquirrelStock\Apache24
notepad C:\SquirrelStock\Apache24\conf\httpd.conf
```

В `httpd.conf`:

- `Define SRVROOT "C:/SquirrelStock/Apache24"` (в архиве — `c:/Apache24`);
- `Listen 127.0.0.1:80` — только этот компьютер; `Listen 80` — если нужен доступ с телефонов (раздел 8);
- раскомментировать `LoadModule rewrite_module modules/mod_rewrite.so` и `LoadModule headers_module modules/mod_headers.so`;
- строка `LoadModule access_compat_module modules/mod_access_compat.so` должна остаться включённой: `config/.htaccess` программы написан в старом синтаксисе `Order/Deny` [из кода];
- `ServerName localhost`;
- в конец — `Include conf/extra/squirrelstock.conf`.

Файл `C:\SquirrelStock\Apache24\conf\extra\squirrelstock.conf` (правила — как в `tools/install/linux.sh`):

```apache
# SquirrelStock (docs/INSTALL_WINDOWS.ru.md)
LoadModule php_module "C:/php/php8apache2_4.dll"
PHPIniDir "C:/php"
AddHandler application/x-httpd-php .php
ServerTokens Prod
ServerSignature Off

<VirtualHost *:80>
    DocumentRoot "C:/SquirrelStock/www"
    DirectoryIndex index.php
    <Directory "C:/SquirrelStock/www">
        AllowOverride All
        Require all granted
    </Directory>
    # служебные папки не отдаются по HTTP (vendor нужен: из него грузятся скрипты фреймворка)
    <DirectoryMatch "^C:/SquirrelStock/www/(config|logs|cli)">
        Require all denied
    </DirectoryMatch>
    # загруженные файлы: только картинки, PHP не выполняется (правила здесь — восстановление из копии пишет в upload/)
    <Directory "C:/SquirrelStock/www/upload">
        AllowOverride None
        Require all denied
        <FilesMatch "(?i)\.(jpe?g|png|gif|webp)$">
            Require all granted
        </FilesMatch>
        <FilesMatch "(?i)\.(php\d*|pht|phtml|phar|cgi|pl|py|sh|shtml)\.">
            Require all denied
        </FilesMatch>
        <IfModule php_module>
            php_admin_flag engine off
        </IfModule>
    </Directory>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    ErrorLog "logs/squirrelstock-error.log"
    CustomLog "logs/squirrelstock-access.log" combined
</VirtualHost>
```

Если `Listen` с другим портом (8080), то и `<VirtualHost *:8080>`. Модуль PHP 8.2 называется так же (`php8apache2_4.dll`).

Служба (документация Apache, «Using Apache HTTP Server on Microsoft Windows»):

```powershell
cd C:\SquirrelStock\Apache24\bin
.\httpd.exe -t                                     # Syntax OK
.\httpd.exe -k install                             # служба Apache2.4
sc.exe failure Apache2.4 reset= 86400 actions= restart/60000     # перезапуск, если упала
sc.exe failure MariaDB reset= 86400 actions= restart/60000
```

Службы `Apache2.4` и `MariaDB` ставятся с типом запуска «Автоматически». Если служба не запускается (ошибка 1067), запустите `.\httpd.exe` в консоли: он покажет причину.

Apache работает от учётной записи LocalSystem. Документация Apache предупреждает, что у неё широкие права на этом компьютере и нет доступа к сети. Пока Apache слушает только 127.0.0.1, это приемлемо. Если сайт открыт для телефонов, можно перевести службу на отдельного пользователя с правами только на `C:\SquirrelStock` и `C:\php`, как описано в той же документации [не проверено].

## 6. Сайт, первый вход, начальная настройка

```powershell
Expand-Archive D:\install\squirrel-stock-<версия>.zip C:\SquirrelStock\www
Copy-Item C:\SquirrelStock\www\config\config.example.php C:\SquirrelStock\www\config\config.php
notepad C:\SquirrelStock\www\config\config.php     # имя базы squirrelstock, пользователь squirrelstock, пароль $dbpw из раздела 3
Restart-Computer                                    # Path для PHP (раздел 4); службы поднимутся сами
```

В корне `C:\SquirrelStock\www` должны лежать `index.php`, `.htaccess`, папки `app`, `config`, `vendor`, `upload`, `logs`. Своего `config.php` в пакете нет, чтобы распаковка нового пакета при обновлении не затирала настройки [из кода].

После перезагрузки, в PowerShell от администратора:

```powershell
cd C:\SquirrelStock\www
C:\php\php.exe cli\modules.php                      # таблицы и пункты меню SquirrelStock
C:\php\php.exe cli\charity-profile.php              # профиль для благотворительной организации (только для пустой базы)
C:\php\php.exe cli\localize-db.php ru               # русские названия документов и пунктов меню (только для пустой базы)
```

Скрипты меняют только базу, поэтому их можно запускать от администратора, а не от службы. Файлы журналов, которые они создадут в `logs\`, служба (LocalSystem) прочитает и допишет. С демо-базой профиль и названия уже применены: достаточно `cli\modules.php`.

Откройте http://localhost/ и войдите как `admin` с паролем из сборки пакета. Пароль смените в профиле, там же включите двухфакторный вход. Дальше — [GUIDE.ru.md](GUIDE.ru.md), раздел «Начальная настройка» (профиль и русские названия уже применены).

Ярлык на общем рабочем столе:

```powershell
Set-Content C:\Users\Public\Desktop\SquirrelStock.url "[InternetShortcut]`r`nURL=http://localhost/"
```

В Edge и Chrome сайт можно ещё «установить как приложение» (меню браузера): он откроется отдельным окном без адресной строки.

Чтобы компьютер не засыпал при питании от сети: иначе сайт пропадёт на телефонах, а копия по расписанию отложится.

```powershell
powercfg /change standby-timeout-ac 0
```

## 7. Резервная копия по расписанию

Что куда попадает и как настраивается — [BACKUP.ru.md](BACKUP.ru.md). Скрипт `cli\backup.php` раз в час запускает Планировщик заданий; с `--if-older=20` копия делается раз в сутки, а если компьютер был выключен — после включения. Готовая команда `schtasks` для этого компьютера показана на странице «Сервисные страницы → Резервная копия» [из кода]. Ниже — то же самое через PowerShell, с параметрами, которых в `schtasks` нет.

**7.1. Копии на этом компьютере и в облако** — задача от SYSTEM: работает без вошедшего пользователя.

```powershell
$a = New-ScheduledTaskAction -Execute 'C:\php\php.exe' -Argument 'C:\SquirrelStock\www\cli\backup.php --if-older=20' -WorkingDirectory 'C:\SquirrelStock\www'
$t = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Hours 1)
$s = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 1) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName 'SquirrelStock backup' -Action $a -Trigger $t -Settings $s -User 'SYSTEM' -RunLevel Highest
Start-ScheduledTask 'SquirrelStock backup'          # затем обновить страницу «Резервная копия»
```

`-StartWhenAvailable` — пропущенный запуск (компьютер был выключен) выполняется при включении. Копия пишется во временный файл и переименовывается только целиком, поэтому оборванная выключением копия не выглядит готовой [из кода].

**7.2. Сетевая папка.** У SYSTEM нет доступа к общим папкам других компьютеров (документация Apache о LocalSystem; для Планировщика то же). Для сетевой папки задача запускается от отдельного пользователя с сохранённым паролем:

```powershell
$cred = Get-Credential -UserName sqs-backup -Message 'Пароль для пользователя копий'
New-LocalUser sqs-backup -Password $cred.Password -PasswordNeverExpires -UserMayNotChangePassword
icacls C:\SquirrelStock\squirrelstock-backup /grant 'sqs-backup:(OI)(CI)M'
icacls C:\SquirrelStock\www\logs /grant 'sqs-backup:(OI)(CI)M'
icacls C:\SquirrelStock\tmp /grant 'sqs-backup:(OI)(CI)M'
Register-ScheduledTask -TaskName 'SquirrelStock backup' -Action $a -Trigger $t -Settings $s -User $cred.UserName -Password $cred.GetNetworkCredential().Password -RunLevel Highest
```

Для задачи с сохранённым паролем пользователю нужно право «Вход в качестве пакетного задания». Обычно Планировщик выдаёт его сам при регистрации задачи; если задача не запускается с ошибкой входа, право выдаётся в «Локальной политике безопасности» (`secpol.msc` → Локальные политики → Назначение прав пользователя) [не проверено].

На сетевом хранилище или другом компьютере заведите пользователя `sqs-backup` с тем же паролем или дайте ему доступ к папке. В настройках копии сетевую папку пишите как `\\server\share\squirrelstock`, а не буквой диска: подключённые буквы задаче не видны (BACKUP.ru.md; документация Apache о сетевых дисках для служб).

**7.3. Облака (rclone).** Распакуйте `rclone.exe` в `C:\SquirrelStock\rclone`. Настройте облако (BACKUP.ru.md, раздел 3), указав файл настроек явно:

```powershell
C:\SquirrelStock\rclone\rclone.exe --config C:\SquirrelStock\rclone\rclone.conf config
C:\SquirrelStock\rclone\rclone.exe --config C:\SquirrelStock\rclone\rclone.conf lsd yandex:
icacls C:\SquirrelStock\rclone\rclone.conf /inheritance:r /grant:r 'SYSTEM:F' 'Administrators:F'   # + 'sqs-backup:R' для задачи из 7.2
```

На странице «Резервная копия» укажите путь к rclone `C:\SquirrelStock\rclone\rclone.exe` и файл настроек `C:\SquirrelStock\rclone\rclone.conf`. Без явного пути rclone ищет настройки в профиле того, кто запустил задачу: у SYSTEM это системная папка, у `sqs-backup` — его профиль. В файле лежат токены доступа к облаку, поэтому права на него ограничены. **Пароль шифрования копии запишите на бумаге** (BACKUP.ru.md, раздел 1).

## 8. Доступ с телефонов по локальной сети

Нужен, если выдачу оформляют с телефона: короткая форма выдачи для этого и сделана.

```powershell
# в httpd.conf: Listen 80 вместо Listen 127.0.0.1:80, затем
Restart-Service Apache2.4
Get-NetConnectionProfile                            # сеть склада должна быть Private («Частная»)
Set-NetConnectionProfile -InterfaceAlias 'Wi-Fi' -NetworkCategory Private   # имя подключения — из предыдущей команды
New-NetFirewallRule -DisplayName 'SquirrelStock' -Direction Inbound -Protocol TCP -LocalPort 80 -Profile Private -Action Allow
Get-NetIPAddress -AddressFamily IPv4 | Where-Object PrefixOrigin -ne WellKnown   # адрес компьютера в сети
```

Телефоны открывают `http://<адрес компьютера>/`. Чтобы адрес не менялся, закрепите его в настройках роутера (резервирование DHCP).

- Правило открывает порт только в частных сетях. В общественной сети компьютер остаётся закрыт.
- По HTTP пароль идёт по Wi-Fi открытым текстом. Для сети склада, закрытой паролем, это обычно приемлемо; гостевую сеть для этого не используйте.
- Порт базы 3306 наружу не открывайте: она слушает только 127.0.0.1 (раздел 3).

## 9. Проверка после установки

`tools/release/check-site.sh` написан на bash и рассчитан на сайт с HTTPS. Для Windows вместо него — `tools/install/check-windows.ps1` (положите рядом, например в `C:\SquirrelStock`):

```powershell
powershell -ExecutionPolicy Bypass -File C:\SquirrelStock\check-windows.ps1
powershell -ExecutionPolicy Bypass -File C:\SquirrelStock\check-windows.ps1 -Url http://192.168.1.10   # адрес для телефонов
```

Что проверяет:

- службы `Apache2.4` и `MariaDB` работают и запускаются автоматически;
- задача «SquirrelStock backup» есть и последний запуск успешен;
- база слушает только 127.0.0.1;
- заголовки безопасности: Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy; нет `X-Powered-By`, у `Server` не видна версия; cookie сессии с HttpOnly и SameSite;
- 21 служебный адрес (`/config/config.php`, `/logs/`, `/cli/backup.php`, `/composer.json`, `/lang/ru.json`, `/vendor/autoload.php`, файлы в `/upload/`…) отвечает 403 или 404, а нужные страницам css и js открываются;
- несуществующие страницы не показывают путей и текстов ошибок PHP.

Код выхода 0 — ошибок нет. Скрипт только читает: запросы GET к сайту и сведения о службах; вход не проверяет.

**[проверено]** в Windows PowerShell 5.1 (Windows 11): проверки сайта — против Docker-стенда (Linux, Apache, PHP 8.2), все прошли, одно предупреждение о версии в заголовке `Server`; ветки служб — на штатных службах Windows и на отсутствующих службах. Против настоящей установки на Windows скрипт ещё не запускался.

Дальше вручную:

1. Вход `admin`, смена пароля, страница «Резервная копия» — места копий без ошибок.
2. «Сохранить и сделать копию сейчас» — в `C:\SquirrelStock\squirrelstock-backup` появился zip.
3. Перезагрузите компьютер **и не входите в систему**: сайт должен открыться с телефона (или войдите и откройте ярлык). Так проверяется автозапуск служб.
4. С другого компьютера с bash и curl можно запустить и полный `tools/release/check-site.sh http://<адрес>`. По HTTP он предупредит о HTTPS и cookie без Secure, остальные проверки те же [из кода].

## 10. Обновление и откат

**Программа.** Порядок — как для хостинга в [UPDATE.ru.md](UPDATE.ru.md), раздел 1: копия, распаковка нового пакета поверх, страница «Обновление программы». На Windows:

```powershell
# 1. «Сервисные страницы → Резервная копия» → «Создать копию» (или Start-ScheduledTask и проверить новую копию в папке)
Stop-Service Apache2.4
Expand-Archive D:\install\squirrel-stock-<новая версия>.zip C:\SquirrelStock\www -Force   # config.php в пакете нет — не затрётся
cd C:\SquirrelStock\www
C:\php\php.exe cli\modules.php                      # новые таблицы и пункты меню (иначе — при первом входе)
Start-Service Apache2.4
```

Затем «Сервисные страницы → Обновление программы»: версия «было → стало» и кнопка «Удалить файлы старых версий» (UPDATE.ru.md, 1.5), потом `check-windows.ps1`.

**PHP внутри ветки** (8.4.x → 8.4.y): `Stop-Service Apache2.4`, переименовать `C:\php` в `C:\php-old`, распаковать новый архив в `C:\php`, вернуть свой `php.ini` из `C:\php-old`, `Start-Service Apache2.4`. Откат — обратно, папку `C:\php-old`.

**PHP 8.2 → 8.4** — так же, только `php.ini` не копировать целиком: взять новый `php.ini-production` и повторить в нём настройки раздела 4. Имя модуля Apache то же [не проверено].

**MariaDB внутри серии** (11.4.x → 11.4.y): установщик MSI новой версии поверх, данные остаются [не проверено]. **Смена серии** (10.11 → 11.4): надёжнее всего сделать копию программой, поставить новую MariaDB с пустой базой (раздел 3, база и пользователь те же) и восстановить копию на странице «Резервная копия» [не проверено].

**Откат программы** [не проверено]: удалить папки `app`, `assets`, `cli`, `lang`, `templates`, `vendor` в `C:\SquirrelStock\www`, распаковать прежний пакет, затем «Сервисные страницы → Резервная копия» → «Восстановить» из копии, сделанной перед обновлением (UPDATE.ru.md, раздел 1).

## 11. Если что-то не так

- **Сайт не открывается, служба Apache2.4 не запускается** — `C:\SquirrelStock\Apache24\bin\httpd.exe -t`, затем `.\httpd.exe` в консоли: он напишет причину. Частые причины — занят порт 80 или неверный путь в `LoadModule php_module`. Журнал — `C:\SquirrelStock\Apache24\logs\error.log`.
- **«Call to undefined function mysqli_connect» или нет расширения** — не раскомментирована строка `extension=` в `C:\php\php.ini` или модуль PHP не находит библиотеки: проверьте `C:\php` в системном `Path` и перезагрузку (раздел 4).
- **Ошибки программы** — в `C:\SquirrelStock\www\logs\` (файлы `log-<дата>.txt`).
- **Копия по расписанию не делается** — запуск вручную: `C:\php\php.exe C:\SquirrelStock\www\cli\backup.php` (код 0 — все места успешно), затем страница «Резервная копия». В Планировщике заданий — журнал задачи «SquirrelStock backup».
- **Антивирус** может замедлять базу или блокировать `rclone.exe` и скрипты PowerShell. Если работа заметно тормозит, добавьте в исключения `C:\SquirrelStock\MariaDB\data` и `C:\SquirrelStock\tmp` [не проверено].
- **Обновления Windows с перезагрузкой**: службы поднимутся сами, MariaDB после внезапного выключения восстанавливает базу при запуске. Чтобы Windows не перезагружалась посреди рабочего дня, задайте «Период активности» в Центре обновления. После крупного обновления Windows запустите `check-windows.ps1`.
