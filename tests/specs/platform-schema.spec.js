// Таблицы платформы (www/app/sqs/schema.sql) досоздаются сами, как таблицы модулей: копия, сделанная до появления таблицы
// (вход с блокировкой подбора, двухфакторный вход), после восстановления не ломает вход. Схема применяется только при
// изменении — второй запрос ее не выполняет.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { login, clickWait } = require('../helpers/zippy');
const { value, query } = require('../helpers/db');
const { logCount } = require('../helpers/stand');

const DB = process.env.SQS_DB_CONTAINER || 'squirrelstock-db-1';
const SCHEMA = path.join(__dirname, '../../www/app/sqs/schema.sql');
const TABLES = ['sqs_loginfail', 'sqs_2fa', 'sqs_2fa_backup'];
const MARK = 'SquirrelStock schema platform applied';
const exists = t => value(`select count(*) from information_schema.tables where table_schema = database() and table_name = '${t}'`) === '1';

test('копия без таблиц платформы: после восстановления таблицы на месте, вход работает', async ({ page, browser }, testInfo) => {
  test.setTimeout(5 * 60_000);
  try {
    // «старая» копия: таблиц платформы в ней нет, а отметка о примененной схеме есть — восстановление должно ее сбросить
    query(TABLES.map(t => `drop table if exists ${t}`).join('; '));
    await page.goto('/index.php?p=App/Pages/Service/Backup');
    const [download] = await Promise.all([page.waitForEvent('download'), page.locator('#bform #create').click()]);
    const file = testInfo.outputPath('old.zip');
    await download.saveAs(file);

    const applied = logCount(MARK);
    await page.goto('/index.php?p=App/Pages/Service/Backup');
    await page.locator('#rform #rfile').setInputFiles(file);
    await page.locator('#rform #confirm').setChecked(true);
    await clickWait(page, '#rform #restore');
    await expect(page.locator('.toast-message').first()).toContainText(/восстановлены|відновлено/);
    for (const t of TABLES) expect(exists(t), `таблица ${t} после восстановления`).toBe(true);
    expect(logCount(MARK), 'схема платформы применена после восстановления').toBe(applied + 1);

    // новый вход (без сессии) работает; схема при этом повторно не применяется
    const ctx = await browser.newContext({ storageState: undefined });
    const p2 = await ctx.newPage();
    await login(p2);
    await p2.goto('/index.php?p=App/Pages/UserProfile');
    await expect(p2.locator('#twofa-link')).toBeVisible();
    await ctx.close();
    expect(logCount(MARK), 'второй запрос схему не применяет').toBe(applied + 1);
  } finally {
    restoreTables();
  }
});

// база загружена вручную (mysql) или программа обновлена без cli/modules.php: отметки о схеме нет — первый же вход досоздает
test('нет отметки о схеме платформы: вход досоздает таблицы', async ({ browser }) => {
  try {
    query(`drop table if exists sqs_loginfail; delete from keyval where keyd = 'sqs_schema'`);
    const applied = logCount(MARK);
    const ctx = await browser.newContext({ storageState: undefined });
    await login(await ctx.newPage());
    await ctx.close();
    expect(exists('sqs_loginfail')).toBe(true);
    expect(value(`select vald from keyval where keyd = 'sqs_schema'`), 'отметка записана').toMatch(/^[0-9a-f]{32}$/);
    expect(logCount(MARK)).toBe(applied + 1);
  } finally {
    restoreTables();
  }
});

function restoreTables() {
  if (TABLES.some(t => !exists(t))) {
    execFileSync('docker', ['exec', '-i', DB, 'mariadb', '-uzstore', '-pzstore', 'zstore'], { input: fs.readFileSync(SCHEMA) });
  }
}
