// Разметка в данных выводится как текст: название позиции, получателя, договора, примечание документа, комментарий,
// уведомление, описание позиции с «<b>метка</b>», кавычками и угловыми скобками показываются буквально и не становятся
// элементами страницы (всплывающие сообщения toastr, подсказка при вводе, список договоров после выбора получателя,
// печатная форма и просмотр документа, список уведомлений, окно описания позиции).
// Фигурные скобки шаблона («{{#x}}») в названии не ломают страницу: Zippy прогоняет готовую страницу через Mustache.
// Проверочные значения — безобидная разметка, без скриптов.
const { test, expect } = require('../fixtures');
const { createItem, createCustomer, createContract, createDoc, pickAuto, clickWait, printForm } = require('../helpers/zippy');
const { value, query, esc } = require('../helpers/db');

const MARK = '<b>метка</b> "кв" \'кв\' <>';

// текст показан буквально, и элемента <b> с «метка» внутри нет
async function literal(loc, what) {
  await expect(loc.first(), what).toContainText('<b>метка</b>', { timeout: 10000 });
  expect(await loc.locator('b', { hasText: 'метка' }).count(), what + ': разметка из данных стала элементом').toBe(0);
}

test('подсказка при вводе и всплывающие сообщения', async ({ page, uniq }) => {
  const code = 'MK-' + uniq, who = `Получатель ${uniq} ${MARK}`;
  await createItem(page, { name: `Позиция ${uniq} ${MARK}`, code });
  expect(value(`select itemname from items where item_code = '${code}'`), 'название сохранено с разметкой (кавычки Zippy меняет на `)').toContain('<b>метка</b>');
  await createDoc(page, 'IncomeItem', { date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }] });

  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
  await page.locator('#cform #customer').fill(who);
  await page.locator('#cform #date').fill('2026-10-10');
  await page.locator('#cform #item').pressSequentially(code, { delay: 30 });
  await literal(page.locator('li a.dropdown-item:visible', { hasText: code }), 'подсказка');
  await page.locator('#cform #item').fill('');

  // нехватка остатка — сообщение с названием позиции в ответе AJAX
  await pickAuto(page, '#cform #item', code, code);
  await page.locator('#cform #qty').fill('9');
  await clickWait(page, '#cform #additem');
  await literal(page.locator('.toast-message'), 'сообщение об ошибке (AJAX)');
  await page.locator('.toast-message').evaluateAll(ts => ts.forEach(t => t.closest('.toast').remove()));

  // выдача — сообщение с именем получателя после перехода, при отрисовке страницы
  await page.locator('#cform #qty').fill('1');
  await clickWait(page, '#cform #additem');
  await clickWait(page, '#cform #issue');
  await literal(page.locator('.toast-message', { hasText: uniq }), 'сообщение после выдачи');
});

test('договор получателя в списке накладной', async ({ page, uniq }) => {
  const who = 'Получатель ' + uniq, number = 'Д-' + uniq;
  await createCustomer(page, { name: who });
  await createContract(page, { number, customer: who, from: '2026-01-01', to: '2026-12-31', note: MARK });
  await page.goto('/index.php?p=App/Pages/Doc/GoodsIssue');
  await pickAuto(page, '#docform #customer', uniq, who);
  const option = page.locator('#docform #contract option', { hasText: number });
  await expect(option).toHaveCount(1, { timeout: 10000 });
  expect(await option.textContent()).toContain('<b>метка</b>');
});

test('примечание документа в печатной форме и просмотре, комментарий к документу', async ({ page, uniq }) => {
  const code = 'MN-' + uniq;
  await createItem(page, { name: 'Позиция ' + code, code });
  const { number } = await createDoc(page, 'IncomeItem', { date: '2026-10-01', notes: `Примечание ${uniq} ${MARK}`, rows: [{ code, qty: 1, price: 0 }] });
  const id = value(`select document_id from documents where document_number = '${esc(number)}' order by document_id desc limit 1`);

  await printForm(page, id);
  await literal(page.locator('body'), 'печатная форма');

  await page.goto('/index.php?p=App/Pages/Register/DocList');
  await page.locator('#filter #searchnumber').fill(number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchnumber').press('Enter')]);
  await clickWait(page, 'a[id^=show_]:visible');
  await literal(page.locator('#preview'), 'просмотр документа');

  await page.locator('a[data-bs-target="#tcomm"]').click();
  await page.locator('#msgform textarea[name=msgtext]').fill(`Комментарий ${uniq} ${MARK}\nвторая строка`);
  await page.locator('#msgform input[type=button]').click();
  await literal(page.locator('#tcomm p', { hasText: uniq }), 'комментарий');
  expect(await page.locator('#tcomm p', { hasText: uniq }).locator('br').count(), 'перенос строки комментария').toBe(1);
});

// сообщение от другого пользователя (форма «Отправить сообщение» сохраняет текст как введен) и системное с <b> и <br>
test('уведомления пользователю', async ({ page, uniq }) => {
  const admin = value(`select user_id from users where userlogin = 'admin'`);
  query(`insert into notifies (user_id, dateshow, message, sender_id, checked) values
         (${admin}, now(), '${esc(`Сообщение ${uniq} ${MARK}`)}', ${admin}, 0),
         (${admin}, now(), 'Системное ${uniq} <b>жирное</b><br>вторая строка', -4, 0)`);
  await page.goto('/index.php?p=App/Pages/NotifyList');
  await literal(page.locator('[id^=nlist_] p', { hasText: 'Сообщение ' + uniq }), 'уведомление');
  const sys = page.locator('[id^=nlist_] p', { hasText: 'Системное ' + uniq });
  await expect(sys.locator('b'), 'разметка системных сообщений сохранена').toHaveText('жирное');
  await expect(sys.locator('br')).toHaveCount(1);
});

test('описание позиции', async ({ page, uniq }) => {
  const code = 'MD-' + uniq;
  await page.goto('/index.php?p=App/Pages/Reference/ItemList');
  await clickWait(page, '#addnew');
  await page.locator('#editname').fill('Позиция ' + code);
  await page.locator('#editcode').fill(code);
  await page.locator('#editdescription').fill(`Описание ${uniq} ${MARK}\nвторая строка`);
  await clickWait(page, '#save');
  await page.locator('#filter #searchkey').fill(code);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchkey').press('Enter')]);
  await clickWait(page, 'a[id^=showdesc_]:visible');
  await literal(page.locator('#idesccontent'), 'описание позиции');
});

test('фигурные скобки шаблона в названии не ломают страницу', async ({ page, uniq }) => {
  const code = 'MB-' + uniq;
  try {
    await createItem(page, { name: `Позиция {{#x}} {{y}} ${code}`, code });
    expect(value(`select itemname from items where item_code = '${code}'`)).toBe(`Позиция {{#x}} {{y}} ${code}`);
    await page.goto('/index.php?p=App/Pages/Reference/ItemList');
    await page.locator('#filter #searchkey').fill(code);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchkey').press('Enter')]);
    await expect(page.locator('body'), 'название в списке позиций как введено').toContainText(`Позиция {{#x}} {{y}} ${code}`, { timeout: 10000 });
    await expect(page.locator('a[title="Редактировать"]:visible, a[title="Редагувати"]:visible').first(), 'подсказки шаблона страницы переведены').toBeAttached();
    await clickWait(page, 'a[id^=edit_]:visible');
    await expect(page.locator('#editname'), 'в поле ввода (атрибут value) — тоже как введено').toHaveValue(`Позиция {{#x}} {{y}} ${code}`);
  } finally {
    // позиция без документов: убрать, чтобы она не попадала в списки других тестов стенда
    query(`delete from items where item_code = '${code}'`);
  }
});
