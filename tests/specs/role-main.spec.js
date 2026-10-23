// Главная у роли «Склад» (TODO 2.21): на демо пользователь, заведенный в интерфейсе, видел только оперативное меню и
// заголовок «Zippy CRM» — стартовая страница нового пользователя «Не указано» (пустая страница), а «Ниже минимального
// остатка» на главной — только с галочкой «Дашборд» у роли. Теперь «Не указано» ведет на главную, а в профиле
// благотворительной организации (денежных плиток нет) главная полная у каждого: напоминания о сроках оборудования и
// «Ниже минимального остатка» с данными, заголовок «Главная». Справочник договоров — все договоры, а не только свои.
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { loginAs } = require('../helpers/users');
const { clickWait } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { webPhp } = require('../helpers/stand');
const { query, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const day = n => new Date(Date.now() + n * 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

test('роль «Склад», стартовая страница «Не указано»: главная с напоминаниями и «Ниже минимального остатка»', async ({ browser, uniq }) => {
  test.setTimeout(4 * 60_000);
  const role = 'Склад-' + uniq, login = 'sm' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  const eq = 'RM-' + uniq, inv = 'INV' + uniq, low = 'RML-' + uniq, who = 'Получатель ' + uniq, num = 'RM' + uniq.slice(-5) + '/26';
  seed({ items: [{ name: 'Кровать ' + eq, code: eq, serial: true }, { name: 'Подгузники ' + low, code: low, minqty: 10 }],
         customers: [{ name: who, type: 1 }],
         contracts: [{ number: num, customer: who, from: day(-30), to: day(-3) }],
         docs: [{ type: 'IncomeItem', date: day(-40), rows: [{ code: eq, qty: 1, price: 0, sn: inv }, { code: low, qty: 2, price: 0 }] },
                { type: 'GoodsIssue', date: day(-30), customer: who, contract: num, rows: [{ code: eq, qty: 1, price: 0, sn: inv }] }] });
  execFileSync('docker', ['exec', '-u', 'www-data', '-e', 'SQS_USER_PASS=' + pass, WEB, 'php', 'cli/role-warehouse.php', '--name=' + role, '--user=' + login]);
  // как у пользователя, заведенного в «Система → Пользователи»: стартовая страница «Не указано»
  webPhp('-r', `require "init.php"; $u = \\App\\Entity\\User::getByLogin("${login}"); $u->mainpage = '\\App\\Pages\\Blank'; $u->save();`);
  webPhp('cli/charity-profile.php');
  let context;
  try {
    let page;
    ({ context, page } = await loginAs(browser, login, pass));
    await page.goto('/');
    await expect(page).toHaveTitle(/^(Главная|Дашборд)$/);   // common.dashbord: ru «Главная», uk «Дашборд»
    await expect(page.locator('#sqs-due tr', { hasText: inv }), 'напоминание о сроке').toHaveCount(1);
    await expect(page.locator('#sqs-belowmin')).toBeVisible();
    await expect(page.locator('[zippy=mqlist], tr', { hasText: low }).first(), '«Ниже минимального остатка» с данными').toBeVisible();
    await expect(page.locator('#sqs-belowmin a'), 'ссылка на отчет — право на отчет есть').toHaveCount(1);
    // справочник договоров: у не-admin Zippy показывал только договоры, где он автор или ответственный, — склад видел пустой
    await page.goto('/index.php?p=App/Pages/Reference/ContractList');
    await page.locator('#filter #searchkey').fill(num);
    await clickWait(page, '#filter [type=submit]');
    await expect(page.locator('body'), 'договор, заведенный не этим пользователем').toContainText(num);
  } finally {
    await context?.close();
    webPhp('cli/charity-profile.php', '--back');
    query(`delete from users where userlogin = '${esc(login)}'; delete from roles where rolename = '${esc(role)}'`);
  }
});

// Второй администратор через интерфейс (TODO 2.22): в списке ролей формы пользователя не было admins — у нее
// disabled = NULL (db.sql, db/demo), а отбор шел по «disabled <> 1». Роль admins здесь такая же: disabled = NULL.
test('второй пользователь с ролью admins заводится в «Система → Пользователи» и видит «Систему»', async ({ page, browser, uniq }) => {
  const login = 'ad' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  const was = query("select coalesce(disabled, 'NULL') from roles where rolename = 'admins'")[0][0];
  query("update roles set disabled = NULL where rolename = 'admins'");
  let context;
  try {
    await page.goto('/index.php?p=App/Pages/Users');
    await page.locator('#addnew').click();
    await page.locator('#editform #editlogin').waitFor();
    const roles = await page.locator('#editform #editrole option').allInnerTexts();
    expect(roles, 'роль admins в списке').toContain('admins');
    await page.locator('#editform #editlogin').fill(login);
    await page.locator('#editform #editpass').fill(pass);
    await page.locator('#editform #editrole').selectOption({ label: 'admins' });
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('#editform button[type=submit]').click()]);
    expect(query(`select r.rolename from users u join roles r on r.role_id = u.role_id where u.userlogin = '${esc(login)}'`)[0]?.[0]).toBe('admins');

    let p2;
    ({ context, page: p2 } = await loginAs(browser, login, pass));
    await p2.goto('/');
    await expect(p2.locator('a[href="/index.php?p=App/Pages/Options"]'), 'раздел «Система» в меню').toBeAttached();
    await p2.goto('/index.php?p=App/Pages/Users');
    await expect(p2.locator('#addnew'), '«Пользователи» открываются').toBeVisible();
  } finally {
    await context?.close();
    query(`delete from users where userlogin = '${esc(login)}'`);
    query(`update roles set disabled = ${was === 'NULL' ? 'NULL' : Number(was)} where rolename = 'admins'`);
  }
});
