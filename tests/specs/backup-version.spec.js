// Копия знает версию программы (первая строка database.sql). Копия более новой версии на старой установке восстанавливается
// только после второго подтверждения: старый код может не знать ее таблиц и столбцов. Запрета нет — откат бывает нужен.
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { value } = require('../helpers/db');
const { seed } = require('../helpers/seed');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
// версия установки — config/version.txt (пакет, установщик); на стенде файла нет — версия dev
const setVersion = v => execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'sh', '-c', v ? `echo ${v} > config/version.txt` : 'rm -f config/version.txt']);

test('копия новой версии на старой: предупреждение и второе подтверждение', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const history = value(`select coalesce(max(vald), '') from keyval where keyd = 'sqs_versions'`);
  const marker = 'Позиция BV-' + uniq;
  try {
    setVersion('2026.10.20-aaaaaaaaa');
    await page.goto('/index.php?p=App/Pages/Service/Backup');
    const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#bform #create').click()]);
    const file = testInfo.outputPath('backup.zip');
    await download.saveAs(file);
    expect(execFileSync('sh', ['-c', 'unzip -p "$0" database.sql | head -1', file], { encoding: 'utf8' }).trim()).toMatch(/^-- SquirrelStock backup \S+ \S+ version 2026\.10\.20-aaaaaaaaa$/);

    // после копии — позиция, которой в копии нет: по ней видно, восстановилась ли база
    seed({ items: [{ name: marker, code: 'BV-' + uniq }] });
    setVersion('2026.10.10-bbbbbbbbb');
    await page.goto('/index.php?p=App/Pages/Service/Backup');
    await expect(page.locator('#rform #confirmnewer'), 'второго подтверждения сразу нет').toHaveCount(0);
    await page.locator('#rform #rfile').setInputFiles(file);
    await page.locator('#rform #confirm').setChecked(true);
    await clickWait(page, '#rform #restore');
    await expect(page.locator('.toast-message').first()).toContainText(/2026\.10\.20-aaaaaaaaa.*2026\.10\.10-bbbbbbbbb/);
    expect(value(`select count(*) from items where itemname = '${marker}'`), 'данные не изменены').toBe('1');

    await page.locator('#rform #rfile').setInputFiles(file);
    await page.locator('#rform #confirm').setChecked(true);
    await page.locator('#rform #confirmnewer').setChecked(true);
    await clickWait(page, '#rform #restore');
    await expect(page.locator('.toast-message').first()).toContainText(/відновлено|восстановлены/);
    expect(value(`select count(*) from items where itemname = '${marker}'`), 'база из копии').toBe('0');

    // копия той же или более старой версии — без второго подтверждения
    setVersion('2026.10.20-ccccccccc');
    await page.goto('/index.php?p=App/Pages/Service/Backup');
    await page.locator('#rform #rfile').setInputFiles(file);
    await page.locator('#rform #confirm').setChecked(true);
    await clickWait(page, '#rform #restore');
    await expect(page.locator('.toast-message').first()).toContainText(/відновлено|восстановлены/);
  } finally {
    setVersion('');
    value(`delete from items where itemname = '${marker}'`);
    value(`delete from keyval where keyd = 'sqs_versions'`);
    if (history) value(`insert into keyval (keyd, vald) values ('sqs_versions', '${history.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}')`);
  }
});
