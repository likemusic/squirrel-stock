// Резервная копия одной кнопкой и восстановление: данные возвращаются к моменту копии,
// представления и триггеры базы на месте. ZIPPY_GAPS п. 26.
const { test, expect } = require('../fixtures');
const { createItem, createDoc, clickWait } = require('../helpers/zippy');
const { value, stockQty } = require('../helpers/db');

test('копия и восстановление', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const code = 'BK-' + uniq, after = 'BKA-' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }] });
  const objects = () => value("select concat((select count(*) from information_schema.tables where table_schema = database()), '/', (select count(*) from information_schema.triggers where trigger_schema = database()))");
  const before = objects();

  await page.goto('/');
  await expect(page.locator('a[href*="Service/Backup"]').first(), 'пункт меню (db/squirrelstock.sql)').toBeAttached();
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#bform #create').click()]);
  expect(download.suggestedFilename()).toMatch(/^squirrelstock-.*\.zip$/);
  const file = testInfo.outputPath('backup.zip');
  await download.saveAs(file);

  // журнал копий этой установки восстановление не трогает (в копию он не попадает)
  const marker = 'marker-' + uniq;
  value(`insert into sqs_backuplog (place, createdon, ok, file, message) values ('local', now(), 0, '', '${marker}')`);
  // изменения после копии
  await createDoc(page, 'IncomeItem', { date: '2026-10-02', rows: [{ code, qty: 3, price: 0 }] });
  await createItem(page, { name: 'Позиция ' + after, code: after });
  expect(stockQty(code)).toBe(8);

  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await page.locator('#rform #rfile').setInputFiles(file);
  await clickWait(page, '#rform #restore');
  await expect(page.locator('.toast-message, .alert-danger:not(#backup-stale)').first()).toContainText(/Підтвердіть/);   // без подтверждения не восстанавливает
  await page.locator('#rform #rfile').setInputFiles(file);
  await page.locator('#rform #confirm').setChecked(true);
  await clickWait(page, '#rform #restore');
  await expect(page.locator('.toast-message').first()).toContainText(/відновлено/);

  expect(stockQty(code)).toBe(5);
  expect(Number(value(`select count(*) from items where item_code = '${after}'`))).toBe(0);
  expect(objects()).toBe(before);
  expect(Number(value(`select count(*) from sqs_backuplog where message = '${marker}'`)), 'журнал копий после восстановления').toBe(1);
  value(`delete from sqs_backuplog where message = '${marker}'`);
});

// Восстановление пишет в upload/ только картинки и документы: скрипт, .htaccess и «x.php.png» из копии пропускаются.
// С введенным паролем незашифрованная копия не принимается (ZipArchive читает такие файлы и без пароля) — до удаления
// текущих данных. Добавляемые в копию файлы безобидные: PHP печатает сумму, картинка 1×1.
test('восстановление: в upload/ только картинки и документы, пароль — только для зашифрованной копии', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const { execFileSync } = require('child_process');
  const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
  const dock = (...a) => execFileSync('docker', a, { encoding: 'utf8' });
  const dir = 'sqs-rt-' + uniq;
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#bform #create').click()]);
  const plain = testInfo.outputPath('plain.zip'), file = testInfo.outputPath('backup.zip');
  await download.saveAs(plain);
  dock('cp', plain, `${WEB}:/tmp/${dir}.zip`);
  dock('exec', WEB, 'php', '-r', `$z = new ZipArchive(); $z->open('/tmp/${dir}.zip');
    $php = '<?php echo "probe-" . (40 + 2);';
    $z->addFromString('upload/${dir}/a.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    $z->addFromString('upload/${dir}/note.pdf', '%PDF-1.4 probe');
    $z->addFromString('upload/${dir}/a.php', $php);
    $z->addFromString('upload/${dir}/b.php.png', $php);
    $z->addFromString('upload/${dir}/.htaccess', 'Require all granted');
    $z->addFromString('upload/${dir}/c.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $z->close();`);
  dock('cp', `${WEB}:/tmp/${dir}.zip`, file);
  dock('exec', WEB, 'rm', '-f', `/tmp/${dir}.zip`);
  const after = 'RTA-' + uniq;
  await createItem(page, { name: 'Позиция ' + after, code: after });   // изменение после копии
  const inUpload = () => dock('exec', WEB, 'sh', '-c', `ls -A upload/${dir} 2>/dev/null || true`).split('\n').filter(Boolean).sort();

  try {
    // не копия (выбран другой файл) — отказ до удаления текущих таблиц
    await page.goto('/index.php?p=App/Pages/Service/Backup');
    await page.locator('#rform #rfile').setInputFiles({ name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('просто текст\n') });
    await page.locator('#rform #confirm').setChecked(true);
    await clickWait(page, '#rform #restore');
    await expect(page.locator('.toast-message').first()).toContainText(/не є резервною копією/);
    expect(Number(value(`select count(*) from items where item_code = '${after}'`)), 'данные не изменены').toBe(1);

    // пароль введен, копия не зашифрована — отказ, данные не тронуты
    await page.goto('/index.php?p=App/Pages/Service/Backup');
    await page.locator('#rform #rfile').setInputFiles(file);
    await page.locator('#rform #rpassword').fill('пароль');
    await page.locator('#rform #confirm').setChecked(true);
    await clickWait(page, '#rform #restore');
    await expect(page.locator('.toast-message').first()).toContainText(/не зашифрована/);
    expect(Number(value(`select count(*) from items where item_code = '${after}'`)), 'данные не изменены').toBe(1);
    expect(inUpload()).toEqual([]);

    await page.goto('/index.php?p=App/Pages/Service/Backup');
    await page.locator('#rform #rfile').setInputFiles(file);
    await page.locator('#rform #confirm').setChecked(true);
    await clickWait(page, '#rform #restore');
    await expect(page.locator('.toast-message').first()).toContainText(/відновлено.*Не відновлено файлів/s);
    expect(Number(value(`select count(*) from items where item_code = '${after}'`)), 'восстановлено').toBe(0);
    expect(inUpload(), 'в upload/ — только картинка и документ').toEqual(['a.png', 'note.pdf']);
  } finally {
    dock('exec', WEB, 'rm', '-rf', `upload/${dir}`);
  }
});
