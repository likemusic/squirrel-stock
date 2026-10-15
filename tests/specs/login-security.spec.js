// Вход: пароль проверяется только по хешу; старый пароль, записанный в базе открытым текстом, принимается и сразу
// хешируется. Пользователи — тестовые, создаются копией роли admin. ZIPPY_GAPS п. 36.
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { blockExternal, messages } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const hashOf = pass => execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'], { input: pass, encoding: 'utf8' });

function createUser(login, userpass) {
  query(`insert into users (userlogin, userpass, createdon, email, acl, disabled, options, role_id)
         select '${esc(login)}', '${esc(userpass)}', now(), '', acl, 0, options, role_id from users where userlogin = 'admin'`);
}

// вход в новой сессии; true — вошли
async function tryLogin(browser, login, pass) {
  const context = await browser.newContext({ storageState: undefined });
  await blockExternal(context);
  const page = await context.newPage();
  await page.goto('/');
  await page.locator('#userlog').fill(login);
  await page.locator('input[type=password]').first().fill(pass);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('button[type=submit], input[type=submit]').first().click()]);
  const ok = (await page.locator('input[type=password]').count()) == 0;
  await context.close();
  return ok;
}

test('хранимый хеш вместо пароля не открывает вход', async ({ browser }) => {
  const login = 'hash' + Date.now().toString(36), pass = 'Пароль-' + Date.now();
  const hash = hashOf(pass);
  createUser(login, hash);
  expect(await tryLogin(browser, login, hash), 'вход по строке хеша').toBe(false);
  expect(await tryLogin(browser, login, pass), 'вход по паролю').toBe(true);
});

test('открытый пароль из старой базы: вход и сразу хеш', async ({ browser }) => {
  const login = 'plain' + Date.now().toString(36), pass = 'old-' + Date.now();
  createUser(login, pass);
  expect(await tryLogin(browser, login, 'wrong-' + pass)).toBe(false);
  expect(await tryLogin(browser, login, pass)).toBe(true);
  const stored = value(`select userpass from users where userlogin = '${esc(login)}'`);
  expect(stored, 'после входа пароль хранится хешем').toMatch(/^\$2y\$/);
  expect(await tryLogin(browser, login, pass), 'и вход по-прежнему работает').toBe(true);
});

test('напоминание сменить пароль admin по умолчанию работает и после перехеширования', async ({ browser }) => {
  const remind = () => Number(value("select count(*) from notifies n join users u on u.user_id = n.user_id where u.userlogin = 'admin' and n.message like 'Змініть у профілі пароль за замовчуванням%'"));
  // пароль admin на стенде — admin (после первого входа он уже хранится хешем)
  query("delete from keyval where keyd = 'lastchecksystem'");
  const before = remind();
  expect(await tryLogin(browser, 'admin', process.env.SQS_PASS || 'admin')).toBe(true);
  expect(value("select userpass from users where userlogin = 'admin'")).toMatch(/^\$2y\$/);
  expect(remind(), 'напоминание о пароле по умолчанию').toBe(before + 1);
});
