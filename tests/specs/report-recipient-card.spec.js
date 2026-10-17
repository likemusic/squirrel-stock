// Карточка получателя: все по одному подопечному — контакты и «кем направлен», оборудование на руках с «Продлить»,
// расходники за период (по позициям и по месяцам, за вычетом возврата), история выдач и возвратов, когда можно следующую выдачу.
// Вход — ссылка у имени в журнале выдач, значок в справочнике контрагентов, поиск по ФИО на самой странице; печать и Excel.
const { test, expect } = require('../fixtures');
const { clickWait, pickAuto, messages } = require('../helpers/zippy');
const { seed } = require('../helpers/seed');
const { query } = require('../helpers/db');
const { xlsxText } = require('../helpers/xlsx');

test('карточка получателя: оборудование, расходники, история, интервал, вход из журнала и справочника', async ({ page, uniq }) => {
  const r1 = 'Подопечный ' + uniq + ' карточка', r2 = 'Подопечный ' + uniq + ' другой';
  const phone = '029' + String(Date.now()).slice(-7);
  const eq = 'CEQ-' + uniq, small = 'CSM-' + uniq, a = 'CA-' + uniq, b = 'CB-' + uniq, inv = 'INV-' + uniq;
  const base = seed({
    items: [{ name: 'Концентратор ' + uniq, code: eq, serial: true }, { name: 'Матрас ' + uniq, code: small },
            { name: 'Подгузники ' + uniq, code: a }, { name: 'Трубка ' + uniq, code: b }],
    customers: [{ name: r1, type: 1, phone, address: 'г. Вымышленный, ул. Тестовая, 1' }, { name: r2, type: 1 }],
  });
  const cid = base.customers[r1], itemB = base.items[b];
  query(`insert into sqs_issue_interval (kind, ref_id, days) values ('item', ${itemB}, 90)`);
  try {
    const { docs } = seed({
      contracts: [{ number: 'К' + uniq, customer: r1, from: '2026-07-01', to: '2026-09-30', note: 'Врач Вымышленный' }],
      docs: [
        { type: 'IncomeItem', date: '2026-06-01', rows: [{ code: eq, qty: 1, price: 0, sn: inv }, { code: small, qty: 5, price: 0 }, { code: a, qty: 50, price: 0 }, { code: b, qty: 10, price: 0 }] },
        { type: 'GoodsIssue', date: '2026-07-01', customer: r1, contract: 'К' + uniq, rows: [{ code: eq, qty: 1, price: 0, sn: inv }, { code: small, qty: 1, price: 0 }] },
        { type: 'GoodsIssue', date: '2026-07-05', customer: r1, rows: [{ code: a, qty: 3, price: 0 }] },
        { type: 'GoodsIssue', date: '2026-08-10', customer: r1, rows: [{ code: a, qty: 2, price: 0 }] },
        { type: 'GoodsIssue', date: '2026-09-01', customer: r1, rows: [{ code: b, qty: 1, price: 0 }] },
        { type: 'GoodsIssue', date: '2026-09-02', customer: r2, rows: [{ code: a, qty: 7, price: 0 }] },
      ],
    });
    const { id: retBase, number: ret } = docs[3];
    await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + retBase);
    await page.locator('#document_date').fill('2026-08-15');
    await page.locator('#docform #notes').fill('Возврат: не подошел размер');
    await clickWait(page, '#execdoc');
    expect(await page.locator('#docform #execdoc:visible').count(), (await messages(page)).join(' ')).toBe(0);

    await page.goto('/');
    await expect(page.locator('a[href*="Report/RecipientCard"]').first(), 'пункт меню модуля reports').toBeAttached();

    // вход из журнала выдач: имя получателя — ссылка на карточку
    await page.goto('/index.php?p=App/Pages/Register/GIList');
    await page.locator('#filter #searchnumber').fill(ret);
    await clickWait(page, '#filter input[type=submit]');
    const link = page.locator(`a[href$="Report/RecipientCard&arg=${cid}"]`).first();
    await expect(link, 'ссылка у имени в журнале выдач').toHaveText(r1);

    // значок в справочнике контрагентов
    await page.goto('/index.php?p=App/Pages/Reference/CustomerList');
    await page.locator('#filter #searchkey').fill(r1);
    await clickWait(page, '#filter input[type=submit]');
    await expect(page.locator(`a[href$="Report/RecipientCard&arg=${cid}"]`), 'значок карточки в справочнике').toHaveCount(1);

    // поиск по ФИО на самой странице, период
    await page.goto('/index.php?p=App/Pages/Report/RecipientCard');
    await pickAuto(page, '#filter #cust', r1, r1);
    await page.locator('#filter #from').fill('2026-06-01');
    await page.locator('#filter #to').fill('2026-12-31');
    await clickWait(page, '#filter #onreport');
    const preview = page.locator('#preview');
    const table = async cls => {
      const t = preview.locator('table.' + cls);
      const head = (await t.locator('tr').first().locator('th').allInnerTexts()).map(s => s.trim());
      const rows = [];
      for (const tr of await t.locator('tr').all()) rows.push((await tr.locator('td').allInnerTexts()).map(s => s.trim()));
      return { head, rows: rows.filter(r => r.length) };
    };
    const byName = (t, name) => { const r = t.rows.find(r => r[0].startsWith(name)); return r && Object.fromEntries(t.head.map((h, i) => [h, r[i]])); };

    const contacts = await preview.locator('table.sqs-card-contacts').innerText();
    expect(contacts).toContain(r1);
    expect(contacts).toContain(phone);
    expect(contacts).toContain('Врач Вымышленный');

    // оборудование на руках: единица и мелкое по договору, срок, «Продлить»
    const units = await table('sqs-card-units');
    expect(byName(units, 'Концентратор ' + uniq)).toMatchObject({ 'Інв. номер': inv, 'Строк': expect.stringContaining('30.09.2026'), 'Ким направлено': 'Врач Вымышленный' });
    expect(byName(units, 'Матрас ' + uniq)).toMatchObject({ 'Кількість': '1' });

    // расходники: по договору — не расходники; возврат вычитается и идет в месяц выдачи
    const items = await table('sqs-card-items');
    expect(byName(items, 'Подгузники ' + uniq)).toMatchObject({ 'Видано': '5', 'Повернуто': '2', 'Разом отримав': '3' });
    expect(byName(items, 'Трубка ' + uniq)).toMatchObject({ 'Видано': '1', 'Разом отримав': '1' });
    expect(byName(items, 'Матрас ' + uniq)).toBeUndefined();
    expect(byName(items, 'Концентратор ' + uniq)).toBeUndefined();
    const months = await table('sqs-card-months');
    expect(byName(months, 'Подгузники ' + uniq)).toMatchObject({ '07.2026': '3', '08.2026': '', 'Разом': '3' });
    expect(byName(months, 'Видач')).toMatchObject({ '07.2026': '1', '08.2026': '1', '09.2026': '1', 'Разом': '3' });

    // история: выдачи и возврат, новые сверху; другой получатель не попадает
    const history = await table('sqs-card-history');
    expect(history.rows.length).toBe(5);
    expect(history.rows[0][0]).toBe('01.09.2026');
    expect(history.rows.find(r => r[0] === '15.08.2026')[4]).toBe('Возврат: не подошел размер');
    expect(await preview.innerText()).not.toContain('— 7 ');

    // интервал выдачи: трубка 01.09.2026 + 90 дней
    const next = await table('sqs-card-intervals');
    expect(byName(next, 'Трубка ' + uniq)).toMatchObject({ 'Інтервал, днів': '90', 'Можна з': '30.11.2026' });

    // Excel — без кнопки «Продлить»
    const xls = await xlsxText(page.context().request, '/index.php?p=App/Pages/ShowReport&arg=xls/recipientcard');
    expect(xls).toContain(r1);
    expect(xls).toContain('Подгузники ' + uniq);
    expect(xls).not.toContain('Продовжити');

    // переход в документ из истории
    await clickWait(page, `#preview table.sqs-card-history a.sqs-doc:text-is("${ret}")`);
    await expect(page).toHaveURL(new RegExp('Register/DocList'));
    expect(await page.locator('body').innerText()).toContain(ret);

    // «Продлить» — и обратно в карточку с новым сроком
    await page.goto(`/index.php?p=App/Pages/Report/RecipientCard&arg=${cid}`);
    await clickWait(page, '#preview table.sqs-card-units a.sqs-extend');
    await expect(page).toHaveURL(new RegExp('EquipmentExtend'));
    await page.locator('#eform #enddate').fill('2026-12-31');
    await clickWait(page, '#eform #save');
    await expect(page).toHaveURL(new RegExp('RecipientCard'));
    expect(byName(await table('sqs-card-units'), 'Концентратор ' + uniq)['Строк']).toContain('31.12.2026');
  } finally {
    query(`delete from sqs_issue_interval where kind = 'item' and ref_id = ${itemB}`);
  }
});
