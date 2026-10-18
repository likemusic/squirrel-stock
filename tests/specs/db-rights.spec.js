// Пользователь базы без прав на структуру (хостинг, где права урезаны вручную): программа говорит, каких прав не хватает,
// и не портит данные. На время теста у пользователя базы стенда отбираются права (GRANT от root), в конце — возвращаются.
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait, login } = require('../helpers/zippy');
const { value } = require('../helpers/db');
const { logCount, webPhp } = require('../helpers/stand');

const DB = process.env.SQS_DB_CONTAINER || 'squirrelstock-db-1';
const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
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
      execFileSync('sh', ['-c', 'unzip -p "$0" database.sql | docker exec -i "$1" mariadb -uroot -prootpass zstore', file, DB]);
    }
  }
});

const exists = t => value(`select count(*) from information_schema.tables where table_schema = database() and table_name = '${t}'`) === '1';
const cliModules = () => {
  try {
    execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'php', 'cli/modules.php'], { encoding: 'utf8', stdio: 'pipe' });
    return { status: 0, stderr: '' };
  } catch (e) {
    return e;
  }
};
async function freshPage(browser) {
  const ctx = await browser.newContext({ storageState: undefined });
  const p = await ctx.newPage();
  await login(p);
  return p;
}

// обновление на хостинге без прав CREATE и ALTER: схемы не применяются. Страница, которой не хватает права (главная Zippy
// создает служебную таблицу), говорит, какого; «Обновление программы» — какие схемы и почему; cli/modules.php — код 1
test('схема без прав CREATE и ALTER: понятная причина на страницах и код 1 у cli/modules.php', async ({ browser }) => {
  revoke('create, alter');
  value(`delete from keyval where keyd = 'sqs_schema'`);
  const p = await freshPage(browser);
  await p.goto('/index.php?p=App/Pages/Main');
  await expect(p.locator('body')).toContainText(/права CREATE.*ALL PRIVILEGES/);
  await p.goto('/index.php?p=App/Pages/Service/AppUpdate');
  const err = p.locator('#appupdate-schema-error');
  await expect(err).toContainText('app/sqs/schema.sql');
  await expect(err).toContainText('CREATE command denied');
  await expect(err).toContainText('ALL PRIVILEGES');
  await p.context().close();

  const cli = cliModules();
  expect(cli.status, 'cli/modules.php').toBe(1);
  expect(String(cli.stderr)).toContain('CREATE command denied');

  grantAll();
  expect(cliModules().status, 'с правами схема применилась').toBe(0);
  expect(value(`select count(*) from keyval where keyd = 'sqs_schema_error' and vald <> ''`), 'отметка об ошибке снята').toBe('0');
});

// без ALTER (CREATE есть): таблицы создаются, цена партии не расширяется — администратору баннер на главной
test('схема без права ALTER: главная открывается, администратору — баннер', async ({ browser }) => {
  revoke('alter');
  value(`delete from keyval where keyd = 'sqs_schema'`);
  const p = await freshPage(browser);
  await p.goto('/index.php?p=App/Pages/Main');
  const banner = p.locator('#sqs-schema-error');
  await expect(banner).toContainText('ALTER command denied');
  await expect(banner).toContainText('app/sqs/schema.sql');
  await p.context().close();
  grantAll();
  const p2 = await freshPage(browser);
  await p2.goto('/index.php?p=App/Pages/Main');
  await expect(p2.locator('#sqs-schema-error'), 'с правами схема применилась').toHaveCount(0);
  await p2.context().close();
});

// блок модуля на главной падает (нет его таблицы) — блок пропускается с записью в журнал, остальная главная открывается
test('нет таблицы модуля: блок главной пропускается, главная открывается', async ({ page }) => {
  root('rename table zstore.sqs_unit to zstore.sqs_unit_t');
  try {
    const logged = logCount('SquirrelStock mainBlocks');
    await page.goto('/index.php?p=App/Pages/Main');
    await expect(page.locator('body')).not.toContainText(/Помилка бази даних|Ошибка базы данных/);
    await expect(page.locator('#sqs-blocks, .navbar').first()).toBeAttached();
    expect(logCount('SquirrelStock mainBlocks'), 'ошибка блока — в журнале').toBe(logged + 1);
  } finally {
    root('rename table zstore.sqs_unit_t to zstore.sqs_unit');
  }
});

// цена в проводке не расширена (схема не применилась): «точная» цена партии не включается — иначе она молча
// округлялась бы до копеек столбцом decimal(11,2); администратор видит, почему
test('точная цена партии не включается, пока столбцы цены не decimal(15,6)', async ({ page }) => {
  const exact = () => webPhp('-r', "$_SERVER['HTTP_HOST']='localhost'; require 'init.php'; echo intval(\\App\\System::getOptions('common')['sqs_partion_exact'] ?? 0);").trim();
  root(`create table zstore.sqs_t_outprice select entry_id, outprice from zstore.entrylist where outprice <> round(outprice, 2);
        alter table zstore.entrylist modify outprice decimal(11,2) default null`);
  try {
    await page.goto('/index.php?p=App/Pages/Options');
    await page.locator('#common #sqspartion').selectOption('1');
    await clickWait(page, '#common button[type=submit]');
    await expect(page.locator('.toast-message, .alert-danger').first()).toContainText(/entrylist\.outprice/);
    expect(exact(), 'настройка осталась «до копейки»').toBe('0');
  } finally {
    root(`alter table zstore.entrylist modify outprice decimal(15,6) default null;
          update zstore.entrylist e join zstore.sqs_t_outprice t using (entry_id) set e.outprice = t.outprice;
          drop table zstore.sqs_t_outprice`);
  }
  await page.goto('/index.php?p=App/Pages/Options');
  await page.locator('#common #sqspartion').selectOption('1');
  await clickWait(page, '#common button[type=submit]');
  expect(exact(), 'со столбцами decimal(15,6) — включается').toBe('1');
  await page.goto('/index.php?p=App/Pages/Options');
  await page.locator('#common #sqspartion').selectOption('0');
  await clickWait(page, '#common button[type=submit]');
});
