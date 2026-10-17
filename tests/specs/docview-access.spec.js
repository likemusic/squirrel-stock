// Окно документа (widgets/docview): методы вызываются по адресу с номером документа в параметрах, поэтому каждый
// проверяет, что пользователь видит этот документ (ACL::checkShowDoc), а не только тот, что открыт в окне:
// данные и печать, связанные документы (список, привязка, отвязка). Document::load условие прав не подставляет,
// в отличие от Document::find — поэтому поиск документа для привязки был закрыт и раньше.
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { blockExternal, clickWait } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { query, value, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const hashOf = pass => execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'], { input: pass, encoding: 'utf8' });
const meta = (type, name) => Number(value(`select meta_id from metadata where meta_type = ${type} and meta_name = '${name}'`));
const docId = number => Number(value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`));
const parentOf = id => Number(value(`select parent_id from documents where document_id = ${id}`));

// роль: приход и общий журнал; расход не виден
function createUser(login, pass, uniq) {
  const view = [meta(1, 'IncomeItem'), meta(3, 'DocList')].join(',');
  const acl = execFileSync('docker', ['exec', WEB, 'php', '-r', `echo serialize(['noshowpartion' => 0, 'showotherstores' => 1, 'aclview' => '${view}', 'acledit' => '', 'aclexe' => '', 'aclcancel' => '', 'aclstate' => '', 'acldelete' => '', 'widgets' => '', 'modules' => '', 'smartmenu' => '']);`], { encoding: 'utf8' });
  query(`insert into roles (rolename, acl, disabled) values ('docview-${uniq}', '${esc(acl)}', 0)`);
  const role = Number(value(`select max(role_id) from roles where rolename = 'docview-${uniq}'`));
  query(`insert into users (userlogin, userpass, createdon, email, acl, disabled, options, role_id)
         select '${esc(login)}', '${esc(hashOf(pass))}', now(), '', acl, 0, options, ${role} from users where userlogin = 'admin'`);
  return role;
}

async function login(browser, user, pass) {
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  await blockExternal(context);
  const page = await context.newPage();
  await page.goto('/');
  await page.locator('#userlog').fill(user);
  await page.locator('input[type=password]').first().fill(pass);
  await page.locator('button[type=submit], input[type=submit]').first().click();
  await page.locator('#logout').first().waitFor({ state: 'attached', timeout: 15000 });
  return { context, page };
}

async function openDoc(page, number) {
  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await page.locator('#filter #searchnumber').fill(number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchnumber').press('Enter')]);
  await clickWait(page, 'a[id^=show_]:visible');
  await expect(page.locator('#_path_')).toBeAttached();
}
const call = (page, method, params) => page.evaluate(([m, p]) => fetch(getMethodUrl($('#_path_').attr('path') + '::' + m, p), { credentials: 'same-origin' }).then(r => r.text()), [method, params]);
const json = async (page, method, params) => { const t = await call(page, method, params); try { return JSON.parse(t); } catch { return { notJson: t.slice(0, 200) }; } };

test('окно документа: данные и связанные документы — только видимых документов', async ({ page, browser, uniq }) => {
  test.setTimeout(300_000);
  const code = 'DV-' + uniq;
  const [inc, inc2, out] = seed({
    items: [{ name: 'Позиция ' + code, code }],
    docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 2, price: 0 }] },
           { type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 1, price: 0 }] },
           { type: 'GoodsIssue', date: '2026-10-02', rows: [{ code, qty: 1 }] }],
  }).docs;
  const a = docId(inc.number), c = docId(inc2.number), b = docId(out.number);   // a, c — приходы (видны), b — расход (не виден)
  const user = 'dv' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  const role = createUser(user, pass, uniq);
  let other;
  try {
    other = await login(browser, user, pass);
    await openDoc(other.page, inc.number);

    // данные и печать чужого типа документа — нет
    const data = await json(other.page, 'loaddata', [b]);
    expect(data.html, 'loaddata невидимого документа').toBeUndefined();
    expect(JSON.stringify(data)).not.toContain(out.number);
    expect(await call(other.page, 'printEP', [b]), 'printEP невидимого документа').not.toContain('"buf"');

    // поиск для привязки: свой тип находится, невидимый — нет (Document::findArray и раньше фильтровал по правам — проверка от регрессии)
    const search = async n => (await json(other.page, 'getDocs', [n, a])).map(d => d.value);
    expect(await search(inc2.number)).toContain(inc2.number);
    expect(await search(out.number), 'getDocs невидимого документа').not.toContain(out.number);

    // невидимый документ не привязывается и не отвязывается
    await call(other.page, 'addchilddoc', [b, a]);
    expect(parentOf(b), 'addchilddoc невидимого документа').toBe(0);
    await call(other.page, 'addchilddoc', [a, b]);
    expect(parentOf(a), 'addchilddoc к невидимому документу').toBe(0);
    query(`update documents set parent_id = ${a} where document_id in (${b}, ${c})`);
    await call(other.page, 'delchilddoc', [b]);
    expect(parentOf(b), 'delchilddoc невидимого документа').toBe(a);
    // видимый, но чужой (автор admin) — кнопки удаления нет, и вызов не отвязывает
    await call(other.page, 'delchilddoc', [c]);
    expect(parentOf(c), 'delchilddoc чужого документа').toBe(a);

    // список связанных: невидимый не показывается, остальные — да; у невидимого документа список пустой
    const kids = await json(other.page, 'loadchilddocs', [a]);
    expect(Array.isArray(kids), JSON.stringify(kids)).toBe(true);
    expect(kids.map(k => Number(k.id))).toEqual([c]);
    expect(kids[0].candel).toBe(false);
    query(`update documents set parent_id = ${b} where document_id = ${c}`);
    expect(await json(other.page, 'loadchilddocs', [b]), 'loadchilddocs невидимого документа').toEqual([]);

    // данные видимого документа с невидимым основанием: ответ есть, основание не показано
    const own = await json(other.page, 'loaddata', [c]);
    expect(own.html, JSON.stringify(own).slice(0, 200)).toBeTruthy();
    expect(own.pdoc_id).toBe(0);

    // admin: привязка и отвязка работают
    query(`update documents set parent_id = 0 where document_id in (${b}, ${c})`);
    await openDoc(page, inc.number);
    await call(page, 'addchilddoc', [c, a]);
    expect(parentOf(c), 'admin привязал').toBe(a);
    expect((await json(page, 'loadchilddocs', [a])).map(k => Number(k.id))).toEqual([c]);
    await call(page, 'delchilddoc', [c]);
    expect(parentOf(c), 'admin отвязал').toBe(0);
    await call(page, 'addchilddoc', [a, a]);
    expect(parentOf(a), 'документ не привязывается к самому себе').toBe(0);
  } finally {
    if (other) await other.context.close();
    query(`update documents set parent_id = 0 where document_id in (${a}, ${b}, ${c})`);
    query(`delete from users where userlogin = '${esc(user)}'`);
    query(`delete from roles where role_id = ${role}`);
  }
});
