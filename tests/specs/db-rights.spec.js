// Пользователь базы без прав на структуру (хостинг, где права урезаны вручную): программа говорит, каких прав не хватает,
// и не портит данные. На время теста у пользователя базы стенда отбираются права (GRANT от root), в конце — возвращаются.
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { value } = require('../helpers/db');

const DB = process.env.SQS_DB_CONTAINER || 'squirrelstock-db-1';
const root = sql => execFileSync('docker', ['exec', '-i', DB, 'mariadb', '-uroot', '-prootpass', '-e', sql], { encoding: 'utf8' });
const revoke = privs => root(`revoke ${privs} on zstore.* from 'zstore'@'%'`);
const grantAll = () => root(`grant all privileges on zstore.* to 'zstore'@'%'`);
const objects = () => value("select concat((select count(*) from information_schema.tables where table_schema = database()), '/', (select count(*) from documents))");

test.afterEach(() => grantAll());

test('копия без права SHOW VIEW: понятная ошибка вместо падения', async ({ page }) => {
  revoke('show view');
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await clickWait(page, '#bform #create');
  await expect(page.locator('.toast-message').first()).toContainText(/SHOW VIEW/);
  await expect(page.locator('.toast-message').first()).toContainText(/ALL PRIVILEGES/);
});

// без CREATE (но с DROP) восстановление удалило бы все таблицы и не смогло создать их заново — пустая база
test('восстановление без права CREATE: отказ до удаления таблиц, данные на месте', async ({ page }, testInfo) => {
  test.setTimeout(5 * 60_000);
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#bform #create').click()]);
  const file = testInfo.outputPath('backup.zip');
  await download.saveAs(file);
  const before = objects();

  revoke('create, create view, trigger');
  await page.goto('/index.php?p=App/Pages/Service/Backup');
  await page.locator('#rform #rfile').setInputFiles(file);
  await page.locator('#rform #confirm').setChecked(true);
  await clickWait(page, '#rform #restore');
  try {
    await expect(page.locator('.toast-message').first()).toContainText(/ALL PRIVILEGES/);
    expect(objects(), 'таблицы и документы на месте').toBe(before);
  } finally {
    let now;
    try { now = objects(); } catch { now = 'нет таблицы documents'; }
    if (now !== before) {   // ошибка воспроизвелась — вернуть базу стенда из копии
      grantAll();
      execFileSync('docker', ['exec', '-i', DB, 'mariadb', '-uroot', '-prootpass', 'zstore'], { input: execFileSync('unzip', ['-p', file, 'database.sql']) });
    }
  }
});
