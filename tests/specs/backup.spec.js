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
