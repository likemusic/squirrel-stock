// Автоматическая резервная копия (cli/backup.php, App\SQS\BackupJob): копия в несколько мест — папка этого компьютера,
// сетевая папка, облако через rclone (в тесте — remote типа local на папку в контейнере, в настоящие облака ничего не уходит).
// Последние N копий в каждом месте; сбой одного места не мешает остальным; в облако — только зашифрованный архив;
// предупреждение, если удачной копии нет больше 2 суток; копия на флешку вручную. ZIPPY_GAPS п. 33.
const { execFileSync, spawnSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait, messages } = require('../helpers/zippy');
const { query } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const T = '/tmp/sqs-backup-test';
// как планировщик: от пользователя веб-сервера
const sh = cmd => execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'sh', '-c', cmd], { encoding: 'utf8' });
const cli = (...args) => spawnSync('docker', ['exec', '-u', 'www-data', '-w', '/var/www/html', WEB, 'php', 'cli/backup.php', ...args], { encoding: 'utf8' });
const files = dir => sh(`ls ${dir} 2>/dev/null || true`).split('\n').filter(f => /^squirrelstock-.*\.zip$/.test(f));
const tick = () => new Promise(r => setTimeout(r, 1100));   // имя копии — с точностью до секунды

// после теста — без его записей журнала и настроек: иначе другие тесты (i18n-ru) видят их на странице
test.afterAll(() => {
  query("delete from sqs_backuplog; delete from keyval where keyd = 'sqs_backup'");
  sh(`rm -rf ${T}`);
});

test('автоматическая копия в несколько мест', async ({ page }, testInfo) => {
  test.setTimeout(6 * 60_000);
  sh(`rm -rf ${T} && mkdir -p ${T}/local ${T}/net ${T}/cloud ${T}/usb && printf '[cloudtest]\\ntype = local\\n' > ${T}/rclone.conf`);
  query("delete from sqs_backuplog; delete from keyval where keyd = 'sqs_backup'");
  const status = async () => {
    await page.goto('/index.php?p=App/Pages/Service/Backup');
    const out = {};
    for (const place of ['local', 'network', 'cloud']) {
      out[place] = (await page.locator(`#backup-status tr[data-place=${place}] td`).allInnerTexts()).map(s => s.trim());
    }
    return out;
  };

  // копий еще не было — крупное предупреждение
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await expect(page.locator('#backup-stale')).toBeVisible();

  // настройки; облако без пароля — туда не отправляется, остальные места работают
  await page.locator('#sform #local').fill(`${T}/local`);
  await page.locator('#sform #network').fill(`${T}/net`);
  await page.locator('#sform #cloud').fill(`cloudtest:${T}/cloud`);
  await page.locator('#sform #rclone_conf').fill(`${T}/rclone.conf`);
  await page.locator('#sform #keep').fill('2');
  await clickWait(page, '#sform #runnow');
  expect((await messages(page)).join(' ')).toMatch(/пароль/);
  expect(files(`${T}/local`)).toHaveLength(1);
  expect(files(`${T}/net`)).toHaveLength(1);
  expect(files(`${T}/cloud`)).toHaveLength(0);
  let st = await status();
  expect(st.cloud[3]).toMatch(/пароль/);
  expect(st.local[3]).toBe('');

  // пароль задан: три запуска планировщиком — в каждом месте остаются последние 2
  await page.locator('#sform #password').fill('Секрет-123');
  await clickWait(page, '#sform #save');
  for (let i = 0; i < 3; i++) {
    await tick();
    const r = cli();
    expect(r.status, r.stdout + r.stderr).toBe(0);
  }
  expect(files(`${T}/local`)).toHaveLength(2);
  expect(files(`${T}/net`)).toHaveLength(2);
  const cloud = files(`${T}/cloud`);
  expect(cloud).toHaveLength(2);
  sh(`touch ${T}/cloud/other-file.zip`);   // чужие файлы в папке не удаляются

  // облачная копия зашифрована: без пароля не читается, с паролем — это копия базы
  const zipTest = pass => sh(`php -r '$z = new ZipArchive(); $z->open("${T}/cloud/${cloud[1]}"); ${pass ? `$z->setPassword("${pass}");` : ''} echo $z->getFromName("database.sql") === false ? "closed" : "open";'`);
  expect(zipTest('')).toBe('closed');
  expect(zipTest('Секрет-123')).toBe('open');
  expect(sh(`php -r '$z = new ZipArchive(); $z->open("${T}/local/${files(`${T}/local`)[1]}"); echo $z->getFromName("database.sql") === false ? "closed" : "open";'`)).toBe('open');

  st = await status();
  for (const p of ['local', 'network', 'cloud']) expect(st[p][3], p).toBe('');
  await expect(page.locator('#backup-stale')).toHaveCount(0);

  // облако недоступно (нет такого remote) — ошибка только у облака, код возврата 1
  query(`update keyval set vald = replace(vald, 'cloudtest:', 'nosuchremote:') where keyd = 'sqs_backup'`);
  await tick();
  let r = cli();
  expect(r.status).toBe(1);
  expect(r.stdout).toMatch(/local\s+OK/);
  expect(r.stdout).toMatch(/network\s+OK/);
  expect(r.stdout).toMatch(/cloud\s+ОШИБКА: rclone/);
  expect(files(`${T}/cloud`)).toHaveLength(2);
  expect(sh(`ls ${T}/cloud`)).toContain('other-file.zip');
  st = await status();
  expect(st.cloud[3]).toMatch(/rclone/);
  query(`update keyval set vald = replace(vald, 'nosuchremote:', 'cloudtest:') where keyd = 'sqs_backup'`);

  // запуск каждый час с --if-older: свежая копия есть — пропуск
  r = cli('--if-older=20');
  expect(r.status).toBe(0);
  expect(r.stdout).toMatch(/пропуск/);

  // удачной копии в сетевую папку не было больше 2 суток — предупреждение
  query("update sqs_backuplog set createdon = createdon - interval 3 day where place = 'network'");
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await expect(page.locator('#backup-stale')).toContainText(/на іншому комп'ютері/);

  // папка внутри сайта — открыта из браузера, туда нельзя
  r = cli('/var/www/html/upload');
  expect(r.stdout).toMatch(/local\s+ОШИБКА/);
  // в журнале — ключ перевода, а не текст на языке запустившего: страница покажет его на языке того, кто смотрит
  expect(query("select message from sqs_backuplog where place = 'local' order by id desc limit 1")[0][0]).toContain('"key":"service.backup.folder_in_webroot"');

  // флешка вручную
  await page.locator('#uform #usbpath').fill(`${T}/usb`);
  await clickWait(page, '#uform #copyto');
  expect(files(`${T}/usb`)).toHaveLength(1);

  // восстановление облачной копии: с неверным паролем — отказ, база не тронута; с верным — восстановлено
  const enc = testInfo.outputPath('cloud.zip');
  execFileSync('docker', ['cp', `${WEB}:${T}/cloud/${cloud[1]}`, enc]);
  const tables = () => query('select count(*) from information_schema.tables where table_schema = database()')[0][0];
  const before = tables();
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await page.locator('#rform #rfile').setInputFiles(enc);
  await page.locator('#rform #rpassword').fill('неверный');
  await page.locator('#rform #confirm').setChecked(true);
  await clickWait(page, '#rform #restore');
  expect((await messages(page)).join(' ')).toMatch(/невірний пароль/);
  expect(tables()).toBe(before);
  await page.locator('#rform #rfile').setInputFiles(enc);
  await page.locator('#rform #rpassword').fill('Секрет-123');
  await page.locator('#rform #confirm').setChecked(true);
  await clickWait(page, '#rform #restore');
  await expect(page.locator('.toast-message').first()).toContainText(/відновлено/);
  expect(tables()).toBe(before);
});
