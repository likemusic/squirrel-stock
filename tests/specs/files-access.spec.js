// Прикрепленные файлы и комментарии: loadfile.php отдает файл тому, кто видит объект, к которому он прикреплен
// (App\SQS\FileAccess); окно документа удаляет только файлы и комментарии этого документа и только тем, кому можно;
// loadshopimage.php без входа — только при включенном магазине; файл больше 4 МБ — сообщение, а не тишина.
// Файлы — безобидный текст, вставляются прямо в базу (кроме проверки размера).
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { blockExternal, clickWait, createItem, createDoc } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const hashOf = pass => execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'], { input: pass, encoding: 'utf8' });
const meta = (type, name) => Number(value(`select meta_id from metadata where meta_type = ${type} and meta_name = '${name}'`));

function addFile(itemType, itemId, name, body, userId) {
  query(`insert into files (item_id, user_id, filename, description, item_type, mime) values (${itemId}, ${userId}, '${esc(name)}', '', ${itemType}, '')`);
  const id = Number(value(`select max(file_id) from files where filename = '${esc(name)}'`));
  query(`insert into filesdata (file_id, filedata) values (${id}, '${esc(body)}')`);
  return id;
}
const fileExists = id => Number(value(`select count(*) from files where file_id = ${id}`)) == 1;

// роль: приход и общий журнал, без справочников контрагентов и договоров
function createUser(login, pass, uniq) {
  const view = [meta(1, 'IncomeItem'), meta(3, 'DocList')].join(',');
  const acl = execFileSync('docker', ['exec', WEB, 'php', '-r', `echo serialize(['noshowpartion' => 0, 'showotherstores' => 1, 'aclview' => '${view}', 'acledit' => '', 'aclexe' => '', 'aclcancel' => '', 'aclstate' => '', 'acldelete' => '', 'widgets' => '', 'modules' => '', 'smartmenu' => '']);`], { encoding: 'utf8' });
  query(`insert into roles (rolename, acl, disabled) values ('files-${uniq}', '${esc(acl)}', 0)`);
  const role = Number(value(`select max(role_id) from roles where rolename = 'files-${uniq}'`));
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

// открыть документ в общем журнале (окно документа) и вызвать его метод, как это делает окно (callPageMethod)
async function openDoc(page, number) {
  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await page.locator('#filter #searchnumber').fill(number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchnumber').press('Enter')]);
  await clickWait(page, 'a[id^=show_]:visible');
  await expect(page.locator('#_path_')).toBeAttached();
}
const call = (page, method, params) => page.evaluate(([m, p]) => fetch(getMethodUrl($('#_path_').attr('path') + '::' + m, p), { credentials: 'same-origin' }).then(r => r.text()), [method, params]);

test('файлы: просмотр по правам на объект, удаление только своих файлов документа', async ({ page, browser, uniq }) => {
  test.setTimeout(240_000);
  const admin = Number(value(`select user_id from users where userlogin = 'admin'`));
  const code = 'FA-' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  const { number } = await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 1, price: 0 }] });
  const doc = Number(value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`));
  const cust = Number(value('select min(customer_id) from customers'));
  const fDoc = addFile(1, doc, `Скан ${uniq}.txt`, 'текст документа', admin);
  const fDoc2 = addFile(1, doc, `второй ${uniq}.txt`, 'второй', admin);
  const fCust = addFile(2, cust, `контрагент ${uniq}.txt`, 'текст контрагента', admin);
  const user = 'fa' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  const role = createUser(user, pass, uniq);
  let other;
  try {
    // без входа — нет
    const anon = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    expect((await anon.request.get(`/loadfile.php?id=${fDoc}`)).status(), 'без входа').toBe(403);
    await anon.close();

    // пользователь с правом на приход: файл документа — да (имя по RFC 6266), файл контрагента — нет
    other = await login(browser, user, pass);
    const r = await other.context.request.get(`/loadfile.php?id=${fDoc}`);
    expect(r.status()).toBe(200);
    expect(await r.text()).toBe('текст документа');
    expect(r.headers()['content-disposition']).toContain(`filename*=UTF-8''${encodeURIComponent(`Скан ${uniq}.txt`)}`);
    expect((await other.context.request.get(`/loadfile.php?id=${fCust}`)).status(), 'файл контрагента').toBe(403);
    expect((await other.context.request.get('/loadfile.php?id=999999999')).status()).toBe(404);

    // он же в окне документа: файл чужой (загрузил admin, автор документа admin) — не удаляется
    await openDoc(other.page, number);
    await call(other.page, 'delfile', [fDoc2, doc]);
    expect(fileExists(fDoc2), 'чужой файл документа').toBe(true);
    const list = JSON.parse(await call(other.page, 'loadfiles', [doc]));
    expect(list.find(f => f.id == fDoc2)?.candel, 'кнопка удаления не показывается').toBe(false);

    // admin: файл другого объекта через окно документа не удаляется, файл этого документа — удаляется
    await openDoc(page, number);
    await call(page, 'delfile', [fCust, doc]);
    expect(fileExists(fCust), 'файл контрагента через окно документа').toBe(true);
    await call(page, 'delfile', [fDoc2, doc]);
    expect(fileExists(fDoc2), 'файл этого документа').toBe(false);

    // комментарии: только комментарий документа; чужой комментарий пользователь не удаляет
    query(`insert into messages (user_id, created, message, item_id, item_type) values (${admin}, now(), 'к контрагенту ${uniq}', ${cust}, 2), (${admin}, now(), 'к документу ${uniq}', ${doc}, 1)`);
    const mCust = Number(value(`select message_id from messages where message = 'к контрагенту ${uniq}'`));
    const mDoc = Number(value(`select message_id from messages where message = 'к документу ${uniq}'`));
    await call(other.page, 'delmsg', [mDoc]);
    expect(Number(value(`select count(*) from messages where message_id = ${mDoc}`)), 'чужой комментарий').toBe(1);
    await call(page, 'delmsg', [mCust]);
    expect(Number(value(`select count(*) from messages where message_id = ${mCust}`)), 'комментарий не к документу').toBe(1);
    await call(page, 'delmsg', [mDoc]);
    expect(Number(value(`select count(*) from messages where message_id = ${mDoc}`))).toBe(0);
    query(`delete from messages where message_id = ${mCust}`);
  } finally {
    if (other) await other.context.close();
    query(`delete from filesdata where file_id in (${fDoc}, ${fDoc2}, ${fCust})`);
    query(`delete from files where file_id in (${fDoc}, ${fDoc2}, ${fCust})`);
    query(`delete from users where userlogin = '${esc(user)}'`);
    query(`delete from roles where role_id = ${role}`);
  }
});

test('картинки магазина без входа — только при включенном магазине', async ({ browser }) => {
  const shop = webShopOn();
  query(`insert into images (content, mime) values ('probe-image', 'image/png')`);
  const id = Number(value(`select max(image_id) from images where content = 'probe-image'`));
  try {
    const anon = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    expect((await anon.request.get(`/loadshopimage.php?id=${id}`)).status()).toBe(shop ? 200 : 403);
    expect(await (await anon.request.get(`/loadimage.php?id=${id}`)).text(), 'loadimage — после входа').not.toContain('probe-image');
    await anon.close();
  } finally {
    query(`delete from images where image_id = ${id}`);
  }
});

function webShopOn() {
  return execFileSync('docker', ['exec', WEB, 'php', '-r', `define('_ROOT', '/var/www/html/'); require '/var/www/html/init.php'; echo (\\App\\System::getOptions('modules')['shop'] ?? 0) == 1 ? 1 : 0;`], { encoding: 'utf8' }).trim().endsWith('1');
}

test('файл больше 4 МБ: сообщение, файл не сохранен', async ({ page, uniq }) => {
  const code = 'FB-' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  const { number } = await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 1, price: 0 }] });
  const doc = Number(value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`));
  await openDoc(page, number);
  await page.locator('a[data-bs-target="#tfiles"]').click();
  await page.locator('#fileaddform input[name=addfile]').setInputFiles({ name: `большой-${uniq}.txt`, mimeType: 'text/plain', buffer: Buffer.alloc(4 * 1024 * 1024 + 10, 'a') });
  await page.locator('#fileaddform input[type=button]').click();
  await expect(page.locator('.toast-message').first()).toContainText(`большой-${uniq}.txt`);
  await expect(page.locator('.toast-message').first()).toContainText('4 МБ');
  expect(Number(value(`select count(*) from files where item_id = ${doc} and item_type = 1`))).toBe(0);
});
