// Остатки Zippy в профиле благотворительной организации (TODO 2.11–2.15): журнал выдач без заказа и суммы, с «Отримувач» и кнопками
// форм выдачи; на главной «Нижче мінімального залишку» со ссылкой на отчет; оперативное меню — выдачи; «Документація» — инструкция
// форка, без «Тех. підтримки» Zippy; служебный контрагент «Фiз. особа» выключен; «Стан складів» без пустых «Артикул» и «Бренд».
// Без профиля — как было (проверяется после --back). Признак получателя в карточке отдельно от тегов, <html lang> страницы входа.
const { test, expect } = require('../fixtures');
const { blockExternal, login, clickWait } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { webPhp } = require('../helpers/stand');
const { query, value } = require('../helpers/db');

// новая сессия: меню, настройки и роль (оперативное меню) кэшируются в сессии
async function freshPage(browser) {
  const context = await browser.newContext({ storageState: undefined });
  await blockExternal(context);
  const page = await context.newPage();
  await login(page);
  return page;
}
const profile = async (browser, on) => {
  webPhp('cli/charity-profile.php', ...(on ? [] : ['--back']));
  return freshPage(browser);
};
const headers = page => page.locator('table tr').first().locator('th:visible').allInnerTexts().then(a => a.map(s => s.trim()));

test.afterEach(() => webPhp('cli/charity-profile.php', '--back'));

test('журнал выдач в профиле: «Отримувач», без замовлення і суми, кнопки форм выдачи, статусы «Всі / Проведені / Не проведені»', async ({ browser, uniq }) => {
  const r = 'Подопечный ' + uniq;
  const { docs } = seed({
    items: [{ name: 'Позиция ' + uniq, code: 'PP-' + uniq }],
    customers: [{ name: r, type: 1 }],
    docs: [
      { type: 'IncomeItem', date: '2026-10-01', rows: [{ code: 'PP-' + uniq, qty: 5, price: 1 }] },
      { type: 'GoodsIssue', date: '2026-10-02', customer: r, rows: [{ code: 'PP-' + uniq, qty: 1, price: 0 }] },
      { type: 'GoodsIssue', date: '2026-10-03', customer: r, draft: true, rows: [{ code: 'PP-' + uniq, qty: 1, price: 0 }] },
    ],
  });
  const [done, draft] = [docs[1].number, docs[2].number];

  let p = await profile(browser, true);
  await p.goto('/index.php?p=App/Pages/Register/GIList');
  const list = p.locator('table:has(th:text-is("Номер"))');
  const head = (await list.locator('tr').first().locator('th:visible').allInnerTexts()).map(s => s.trim()).filter(Boolean);
  expect(head).toContain('Отримувач');
  for (const off of ['Замовлення', 'Клієнт', 'Сума']) expect(head, off).not.toContain(off);
  await expect(list.locator('[id^=amount_]:visible, [id^=order_]:visible')).toHaveCount(0);
  expect(await p.locator('#filter #status option').evaluateAll(o => o.map(x => x.value + ':' + x.textContent.trim())))
    .toEqual(['3:Всі', '6:Проведені', '7:Не проведені']);
  await expect(p.locator('label[for=searchcust]')).toHaveText('Отримувач');
  // кнопки журнала (вверху страницы те же формы — в оперативном меню, на телефоне его нет)
  await expect(p.locator('a.btn-primary[href$="Service/ConsumablesIssue"]')).toHaveText('Видача витратних матеріалів');
  await expect(p.locator('a.btn-primary[href$="Service/EquipmentIssue"]')).toHaveText('Видача обладнання');
  await expect(p.locator('a.btn[href$="Doc/GoodsIssue"]'), 'есть формы выдачи — без «Створити ВН»').toHaveCount(0);

  // «Проведені» — проведенная выдача, «Не проведені» — черновик (поиск по тексту получателя)
  for (const [status, shown, hidden] of [['6', done, draft], ['7', draft, done]]) {
    await p.locator('#filter #status').selectOption(status);
    await p.locator('#filter #searchtext').fill(uniq);
    await clickWait(p, '#filter input[type=submit]');
    await expect(p.locator(`[id^=number_]:text-is("${shown}")`), 'статус ' + status).toHaveCount(1);
    await expect(p.locator(`[id^=number_]:text-is("${hidden}")`), 'статус ' + status).toHaveCount(0);
  }
  await p.context().close();

  p = await profile(browser, false);
  await p.goto('/index.php?p=App/Pages/Register/GIList');
  const zh = (await p.locator('table:has(th:text-is("Номер"))').locator('tr').first().locator('th:visible').allInnerTexts()).map(s => s.trim());
  for (const keep of ['Замовлення', 'Клієнт', 'Сума']) expect(zh, keep).toContain(keep);
  expect(await p.locator('#filter #status option').evaluateAll(o => o.map(x => x.value))).toEqual(['0', '1', '2', '5', '3']);
  await expect(p.locator('a.btn[href$="Doc/GoodsIssue"]')).toBeVisible();
  await p.context().close();
});

test('журнал выдач в профиле без форм выдачи у роли: кнопка «Нова видача» на накладную', async ({ browser }) => {
  webPhp('cli/charity-profile.php');
  const ids = value(`select group_concat(meta_id) from metadata where meta_type = 5 and meta_name in ('ConsumablesIssue', 'EquipmentIssue') and disabled = 0`);
  query(`update metadata set disabled = 1 where meta_id in (${ids})`);
  try {
    const p = await freshPage(browser);
    await p.goto('/index.php?p=App/Pages/Register/GIList');
    await expect(p.locator('a.btn[href$="Doc/GoodsIssue"]')).toHaveText('Нова видача');
    await expect(p.locator('a.btn-primary[href$="Service/ConsumablesIssue"], a.btn-primary[href$="Service/EquipmentIssue"]')).toHaveCount(0);
    await p.context().close();
  } finally {
    query(`update metadata set disabled = 0 where meta_id in (${ids})`);
  }
});

test('главная и оперативное меню в профиле: «Нижче мінімального залишку» со ссылкой, без постачальника; кнопки выдач', async ({ browser, uniq }) => {
  seed({ items: [{ name: 'Позиция ' + uniq + ' мин', code: 'PM-' + uniq, minqty: 1000000 }],
         docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code: 'PM-' + uniq, qty: 1, price: 1 }] }] });
  const smart = () => value(`select acl from roles where rolename = 'admins'`).match(/s:9:"smartmenu";s:\d+:"([^"]*)"/)[1].split(',').sort().join(',');
  const ids = names => value(`select group_concat(meta_id order by meta_id) from metadata where ${names}`).split(',').sort().join(',');
  const before = smart();

  let p = await profile(browser, true);
  expect(smart()).toBe(ids(`(meta_type = 3 and meta_name = 'GIList') or (meta_type = 5 and meta_name in ('ConsumablesIssue', 'EquipmentIssue'))`));
  await p.goto('/index.php');
  const block = p.locator('.card').filter({ has: p.locator('#sqs-belowmin') });
  await expect(p.locator('#sqs-belowmin a[href$="Report/BelowMinimum"]')).toHaveText('Нижче мінімального залишку');
  expect((await block.locator('tr').first().locator('th:visible').allInnerTexts()).map(s => s.trim())).not.toContain('Постачальник');
  const top = p.locator('.navbar a.btn-outline-primary');
  for (const href of ['Register/GIList', 'Service/ConsumablesIssue', 'Service/EquipmentIssue']) {
    await expect(top.and(p.locator(`[href$="${href}"]`)), href).toHaveCount(1);
  }
  await expect(top.and(p.locator('[href$="Reference/ItemList"], [href$="Register/DocList"]'))).toHaveCount(0);
  await p.context().close();

  // повторный запуск не меняет; --back — снова стандартное, у ручной настройки роли профиль ничего не трогает
  webPhp('cli/charity-profile.php');
  p = await profile(browser, false);
  expect(smart()).toBe(before);
  await p.goto('/index.php');
  await expect(p.locator('h4', { hasText: 'Критична кількість' })).toBeVisible();
  await expect(p.locator('#sqs-belowmin')).toHaveCount(0);
  await expect(p.locator('.navbar a.btn-outline-primary[href$="Register/DocList"]')).toHaveCount(1);
  await p.context().close();

  const role = value(`select role_id from roles where rolename = 'admins'`);
  const acl = value(`select acl from roles where role_id = ${role}`);
  query(`update roles set acl = replace(acl, 's:9:"smartmenu";s:${before.length}:"${value(`select acl from roles where role_id = ${role}`).match(/s:9:"smartmenu";s:\d+:"([^"]*)"/)[1]}"', 's:9:"smartmenu";s:1:"8"') where role_id = ${role}`);
  try {
    webPhp('cli/charity-profile.php');
    expect(smart(), 'меню, настроенное вручную, профиль не меняет').toBe('8');
  } finally {
    query(`update roles set acl = '${acl.replace(/'/g, "''")}' where role_id = ${role}`);
  }
});

test('меню в профиле: «Документація» — инструкция форка, без «Тех. підтримки» Zippy', async ({ browser }) => {
  let p = await profile(browser, true);
  await p.goto('/index.php');
  await expect(p.locator('#sidebar a[href*="zippy.com.ua"]')).toHaveCount(0);
  await expect(p.locator('#sidebar a[href="https://github.com/likemusic/squirrel-stock/blob/main/docs/USER_GUIDE.ru.md"]')).toHaveText('Документація');
  await p.context().close();

  p = await profile(browser, false);
  await p.goto('/index.php');
  await expect(p.locator('#sidebar a[href="https://zippy.com.ua/contacts"]')).toBeAttached();
  await expect(p.locator('#sidebar a[href="https://zippy.com.ua/zstoreusage"]')).toBeAttached();
  await p.context().close();
});

test('служебный контрагент «Фiз. особа» в профиле выключен, --back включает; с документами не трогается', async ({ browser, uniq }) => {
  const id = value(`select min(customer_id) from customers where customer_name in ('Фiз. особа', 'Физ. лицо')`);
  test.skip(!id || id === 'NULL', 'в базе стенда нет служебного контрагента');
  expect(value(`select status from customers where customer_id = ${id}`)).toBe('0');
  let p = await profile(browser, true);
  expect(value(`select status from customers where customer_id = ${id}`)).toBe('1');
  await p.goto('/index.php?p=App/Pages/Reference/CustomerList');
  await expect(p.locator('[id^=customername_]', { hasText: 'Фiз. особа' })).toHaveCount(0);
  await p.context().close();
  webPhp('cli/charity-profile.php', '--back');
  expect(value(`select status from customers where customer_id = ${id}`)).toBe('0');

  // по нему есть документ — профиль его не выключает
  const name = 'Фiз. особа';
  const { docs } = seed({ items: [{ name: 'Позиция ' + uniq, code: 'PF-' + uniq }],
    docs: [{ type: 'GoodsIssue', date: '2026-10-02', customer: name, draft: true, notes: uniq, rows: [{ code: 'PF-' + uniq, qty: 1, price: 0 }] }] });
  try {
    webPhp('cli/charity-profile.php');
    expect(value(`select status from customers where customer_id = ${id}`)).toBe('0');
  } finally {
    query(`delete from documents where document_id = ${docs[0].id}`);
  }
});

test('«Стан складів» в профиле: без пустых «Артикул» и «Бренд», заполненный артикул остается', async ({ browser, uniq }) => {
  const p = await profile(browser, true);
  const run = async () => {
    await p.goto('/index.php?p=App/Pages/Report/StoreItems');
    await clickWait(p, '#filter #onreport');
    return (await p.locator('#preview table tr').nth(1).locator('th').allInnerTexts()).map(s => s.trim());
  };
  const filled = value(`select count(*) from items where disabled <> 1 and trim(coalesce(manufacturer, '')) <> ''`) !== '0';
  const head = await run();
  await expect(p.locator('h3 + small')).toHaveText('Залишки позицій на складах');
  expect(head).toContain('Артикул');   // на стенде у позиций есть коды
  expect(head.includes('Бренд')).toBe(filled);
  await p.context().close();
});

test('карточка получателя: «Ознака» отдельно от тегов', async ({ page, uniq }) => {
  const r = 'Подопечный ' + uniq;
  const { customers } = seed({ customers: [{ name: r, type: 1 }] });
  const cid = customers[r];
  query(`insert into taglist (tag_type, item_id, tag_name) values (1, ${cid}, 'підопічний'), (1, ${cid}, 'Відділення ${uniq}')`);
  try {
    await page.goto('/index.php?p=App/Pages/Report/RecipientCard&arg=' + cid);
    const rows = await page.locator('#preview table.sqs-card-contacts tr').evaluateAll(trs => trs.map(tr => [...tr.cells].map(c => c.textContent.trim()).join(': ')));
    expect(rows).toContain('Ознака: підопічний');
    expect(rows).toContain(`Теги: Відділення ${uniq}`);
  } finally {
    query(`delete from taglist where tag_type = 1 and item_id = ${cid}`);   // украинские теги видны в списках на русском (i18n-ru.spec.js)
  }
});

// стенд тестов — uk, как прежний жестко прописанный lang="uk"; с SQS_LANG=ru страница отдает lang="ru" (проверено вручную 23.10)
test('страница входа: <html lang> — язык интерфейса', async ({ browser }) => {
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  await blockExternal(context);
  const page = await context.newPage();
  await page.goto('/');
  await expect(page.locator('#userlog')).toBeVisible();
  const lang = webPhp('-r', 'require "config/config.php"; echo $_config["common"]["lang"] ?? "uk";').trim();
  await expect(page.locator('html')).toHaveAttribute('lang', lang);
  await context.close();
});
