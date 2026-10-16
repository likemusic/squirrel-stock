// Версия программы и страница «Сервис → Обновление программы» (модуль update, App\SQS\Release): версия из config/version.txt
// внизу меню и на странице, смена версии — в истории обновлений; без списка файлов пакета (стенд, установка из git) —
// сообщение вместо сверки; страница только для роли admins. Сверка с пакетом и удаление остатков старых версий —
// tools/install/test-update.sh hosting (удаляет файлы, поэтому только в одноразовом контейнере).
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { blockExternal } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const VERSION_FILE = '/var/www/html/config/version.txt';
const hashOf = pass => execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'], { input: pass, encoding: 'utf8' });

test('версия внизу меню и в истории обновлений; страница — только администратору', async ({ page, browser, uniq }) => {
  const ver = '2026.01.02-t' + uniq.toLowerCase().replace(/[^a-z0-9]/g, '');
  const user = 'au' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  let other;
  execFileSync('docker', ['exec', WEB, 'sh', '-c', `echo '${ver}' > ${VERSION_FILE}`]);
  try {
    await page.goto('/');
    await expect(page.locator('#sqs-version')).toContainText('SquirrelStock ' + ver);
    await page.goto('/index.php?p=App/Pages/Service/AppUpdate');
    await expect(page.locator('#appupdate-version')).toHaveText(ver);
    await expect(page.locator('#appupdate-history tr').nth(1)).toContainText(ver);   // новая запись — первой
    await expect(page.locator('#appupdate-nomanifest')).toBeVisible();
    await expect(page.locator('#clean')).toHaveCount(0);
    expect(value(`select vald from keyval where keyd = 'sqs_version'`)).toBe(ver);

    // пользователь не из admins: страница не открывается (кнопка удаляет файлы сайта)
    query(`insert into users (userlogin, userpass, createdon, email, acl, disabled, options, role_id)
           select '${esc(user)}', '${esc(hashOf(pass))}', now(), '', acl, 0, options, (select min(role_id) from roles where rolename <> 'admins') from users where userlogin = 'admin'`);
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    await blockExternal(context);
    other = await context.newPage();
    await other.goto('/');
    await other.locator('#userlog').fill(user);
    await other.locator('input[type=password]').first().fill(pass);
    await other.locator('button[type=submit], input[type=submit]').first().click();
    await other.locator('#logout').first().waitFor({ state: 'attached', timeout: 15000 });
    await expect(other.locator('#sqs-version')).toContainText(ver);
    await other.goto('/index.php?p=App/Pages/Service/AppUpdate');
    await expect(other.locator('#appupdate-version')).toHaveCount(0);
  } finally {
    if (other) await other.context().close();
    query(`delete from users where userlogin = '${esc(user)}'`);
    execFileSync('docker', ['exec', WEB, 'rm', '-f', VERSION_FILE]);
  }
  // без файла — dev, смена записана
  await page.goto('/');
  await expect(page.locator('#sqs-version')).toContainText('SquirrelStock dev');
  expect(value(`select vald from keyval where keyd = 'sqs_version'`)).toBe('dev');
});
