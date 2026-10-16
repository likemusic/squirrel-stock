// Действия в интерфейсе Zippy/SquirrelStock: вход, справочники, документы, отчёты.
// Перенесено из сценариев исследования (../Zippy/tools: lib.js, docs.js, reports.js).

const LOCAL = ['127.0.0.1', 'localhost'];

// Режим учёта серий (настройка usesnumber): 2 — серии со сроком годности, 3 — серийные номера изделий.
// Хоспису сроки годности не нужны (15.10.2026), основной режим тестов — 3.
const USESNUMBER_HOSPICE = '3';

// Все запросы не к стенду обрываются: так ловятся зависимости от интернета (CDN, шрифты, счётчики)
async function blockExternal(context) {
  await context.route(u => !LOCAL.includes(new URL(u).hostname), r => r.abort());
}

async function login(page, user = 'admin', pass = process.env.SQS_PASS || 'admin') {
  await page.goto('/');
  if (await page.locator('input[type=password]').count()) {
    await page.locator('#userlog').first().fill(user);
    await page.locator('input[type=password]').first().fill(pass);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('button[type=submit], input[type=submit]').first().click()]);
  }
  if (await page.locator('input[type=password]').count()) throw new Error('вход не удался: ' + (await messages(page)).join(' | '));
}

async function messages(page) {
  return (await page.locator('.toast-message, .alert-danger, .alert-warning, .alert-success').allInnerTexts()).map(s => s.replace(/\s+/g, ' ').trim()).filter(Boolean);
}

// Щелчок и ожидание результата. Если щелчок начал переход страницы (отправка формы, кнопка-ссылка Zippy), ждать загрузки
// новой страницы. waitForLoadState('networkidle') — это состояние: пока переход не начался, оно уже «выполнено», и на
// медленной машине код дальше работал со старой страницей (флак «строк 0, ожидалось 1»: строки считались на странице,
// где таблица строк скрыта формой строки, хотя на сервере строка была).
async function clickWait(page, sel) {
  const nav = page.waitForEvent('request', { predicate: r => r.isNavigationRequest() && r.frame() === page.mainFrame(), timeout: 1500 }).catch(() => null);
  const loaded = page.waitForEvent('load', { timeout: 60000 }).catch(() => null);
  await page.locator(sel).first().click();
  if (await nav) {
    await loaded;   // новая страница загружена; ждать простоя сети после нее нельзя: на некоторых страницах он не наступает
  } else {
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});   // без перехода — ответ AJAX
  }
  await page.waitForTimeout(300);
}

// Подсказка (autocomplete): ввести запрос и выбрать вариант.
// firstMustContain — ждать, пока первым в списке станет нужный вариант: список по недопечатанному запросу
// успевает показаться раньше («1C-03» → 1C-030 вместо 1C-039)
// Набор текста по символам. Скобки — через Shift+9/0, как на настоящей клавиатуре: подсказка (bootstrap-typeahead)
// принимает «(» (код 40, как у стрелки вниз) только с Shift, а Playwright печатает ее без Shift.
async function typeText(page, inp, text) {
  for (const part of text.split(/([()])/)) {
    if (part === '(' || part === ')') {
      await page.keyboard.down('Shift');
      await page.keyboard.press(part === '(' ? 'Digit9' : 'Digit0');
      await page.keyboard.up('Shift');
    } else if (part) {
      await inp.pressSequentially(part, { delay: 40 });
    }
  }
}

async function pickAuto(page, sel, query, expect, firstMustContain) {
  const inp = page.locator(sel);
  await inp.fill('');
  await typeText(page, inp, query);
  let opt = page.locator('li a.dropdown-item:visible', { hasText: expect || '' }).first();
  if (firstMustContain) {
    opt = page.locator('li a.dropdown-item:visible').first();
    // вариант выглядит как «название, код»: код «X-03» входит и в «X-030», поэтому сверяется окончание строки
    const fits = t => t.endsWith(', ' + firstMustContain) || (!t.includes(', ') && t.includes(firstMustContain));
    for (let i = 0; i < 40 && !fits((await opt.innerText().catch(() => '')).trim()); i++) await page.waitForTimeout(200);
  }
  await opt.waitFor({ timeout: 8000 });
  await Promise.all([page.waitForLoadState('networkidle'), opt.click()]);
  await page.waitForTimeout(300);
}

async function createItem(page, { name, code, msr = 'шт', serial = false, minqty }) {
  await page.goto('/index.php?p=App/Pages/Reference/ItemList');
  await clickWait(page, '#addnew');
  await page.locator('#editname').fill(name);
  await page.locator('#editcode').fill(code);
  await page.locator('#editmsr').fill(msr);
  if (serial) await page.locator('#edituseserial').setChecked(true);
  if (minqty !== undefined) await page.locator('#editminqty').fill(String(minqty));
  await clickWait(page, '#save');
}

// type: 1 — покупатель (получатель), 2 — поставщик (донор)
async function createCustomer(page, { name, type = 1, phone, address }) {
  await page.goto('/index.php?p=App/Pages/Reference/CustomerList');
  await clickWait(page, '#addnew');
  await page.locator('#editcustomername').fill(name);
  await page.locator('#edittype').selectOption(String(type));
  if (phone) await page.locator('#editphone').fill(phone);
  if (address) await page.locator('#editaddress').fill(address);
  await clickWait(page, '#customerdetail button[type=submit], #customerdetail input[type=submit], #save');
}

// Документ через форму и проведение.
// type: IncomeItem | GoodsReceipt | GoodsIssue | OutcomeItem
// d: { date, customer, payment, contract, notes, rows: [{ code, qty, price, sn, sdate }], dblclick, draft }
// dblclick — нажать «Провести» двойным щелчком (как делают торопливые пользователи)
// Возвращает номер, сообщения и то, что форма сама подставила в строки (цена, партия).
async function createDoc(page, type, d) {
  await page.goto('/index.php?p=App/Pages/Doc/' + type);
  const number = await page.locator('#document_number').inputValue();
  await page.locator('#document_date').fill(d.date);
  await page.locator('#docform #store').selectOption('1');
  if (d.customer) await pickAuto(page, '#docform #customer', d.customer, d.customer);
  if (d.payment !== undefined && await page.locator('#docform #payment').count()) await page.locator('#docform #payment').selectOption(String(d.payment));
  if (d.contract) {   // договор выбирается по номеру; список появляется после выбора контрагента
    const find = () => page.locator('#docform #contract option').evaluateAll((os, n) => (os.find(o => o.textContent.trim().startsWith(n + ' ')) || {}).value, d.contract);
    let value = await find();
    for (let i = 0; i < 50 && !value; i++) { await page.waitForTimeout(200); value = await find(); }   // список приходит AJAX-ом после выбора контрагента
    if (!value) throw new Error(`договор ${d.contract} не найден в накладной`);
    await page.locator('#docform #contract').selectOption(value);
  }
  if (d.notes) await page.locator('#docform #notes').fill(d.notes);
  const suggested = [];
  for (const r of d.rows) {
    // после сохранения строки форма может сама открыть следующую — смотрим, что именно показано
    await page.locator('#editdetail:visible, #addrow:visible').first().waitFor({ timeout: 15000 });
    if (!(await page.locator('#editdetail:visible').count())) await clickWait(page, '#addrow');
    await pickAuto(page, type === 'GoodsIssue' ? '#edittovar' : '#edititem', r.code, '', r.code);
    const s = { code: r.code };
    if (await page.locator('#editprice').count()) s.price = await page.locator('#editprice').inputValue();
    if (type === 'GoodsIssue' && await page.locator('#editserial').count()) s.serial = await page.locator('#editserial').inputValue();
    suggested.push(s);
    if (r.qty !== undefined) await page.locator('#editquantity').fill(String(r.qty));
    if (r.price !== undefined && await page.locator('#editprice').count()) await page.locator('#editprice').fill(String(r.price));
    if (r.sn) await page.locator(type === 'GoodsIssue' ? '#editserial' : '#editsnumber').fill(r.sn);
    if (r.sdate) await page.locator('#editsdate').fill(r.sdate);
    await clickWait(page, type === 'GoodsIssue' ? '#submitrow' : '#saverow');
  }
  await page.locator('#cancelrow:visible, #addrow:visible').first().waitFor({ timeout: 15000 });
  if (await page.locator('#editdetail:visible').count()) await clickWait(page, '#cancelrow');
  const nrows = await page.locator('#docform a[id^=delete_]:visible').count();
  const uniq = new Set(d.rows.map(r => r.code + '|' + (r.sn || ''))).size;
  if (nrows !== uniq) throw new Error(`${type} ${number}: строк ${nrows}, ожидалось ${uniq}: ${(await messages(page)).join(' | ')}`);
  if (d.draft) {   // сохранить без проведения
    await clickWait(page, '#savedoc');
    return { number, msgs: await messages(page), suggested };
  }
  if (d.dblclick) {
    await page.locator('#execdoc').dblclick();
    await page.waitForTimeout(2000); // оба запроса и переход после проведения
    await page.waitForLoadState('networkidle');
  } else await clickWait(page, '#execdoc');
  const msgs = await messages(page);
  if (await page.locator('#docform #execdoc:visible').count()) throw new Error(`${type} ${number}: не проведён: ${msgs.join(' | ')}`);
  return { number, msgs, suggested };
}

// Отчёт: заполнить фильтр и нажать «ОК».
// f: { from, to, selects: {id: value}, autos: {id: text}, checks: {id: bool}, texts: {id: text}, form, submit }
async function runReport(page, path, f = {}) {
  await page.goto('/index.php?p=App/Pages/' + path);
  const formSel = f.form ? '#' + f.form : '#filter';
  const form = page.locator(formSel);
  for (const [k, v] of Object.entries(f.selects || {})) {
    await Promise.all([page.waitForLoadState('networkidle'), form.locator('#' + k).selectOption(String(v))]);
    await page.waitForTimeout(400);
  }
  if (f.from) await form.locator('#' + (f.fromId || 'from')).fill(f.from);
  if (f.to) await form.locator('#' + (f.toId || 'to')).fill(f.to);
  for (const [k, v] of Object.entries(f.autos || {})) await pickAuto(page, formSel + ' #' + k, v, v);
  for (const [k, v] of Object.entries(f.checks || {})) await form.locator('#' + k).setChecked(v);
  for (const [k, v] of Object.entries(f.texts || {})) await form.locator('#' + k).fill(v);
  const btn = form.locator(f.submit || 'input[type=submit][value="ОК"], input[type=button][value="ОК"], input[type=submit][value="Далее >"]').first();
  await Promise.all([page.waitForLoadState('networkidle'), btn.click()]);
  await page.waitForTimeout(600);
}

// Договор (Довідники → Договори) в состоянии «В работе» — только такие выбираются в документах.
// d: { number, customer, from, to, note } — to: дата окончания (срок), note: краткое описание
async function createContract(page, d) {
  await page.goto('/index.php?p=App/Pages/Reference/ContractList');
  await clickWait(page, '#addnew');
  await page.locator('#editcontract_number').fill(d.number);
  if (d.from) await page.locator('#editcreatedon').fill(d.from);
  if (d.to) await page.locator('#editenddate').fill(d.to);
  await pickAuto(page, '#editcust', d.customer, d.customer);
  if (d.note) await page.locator('#editshortdesc').fill(d.note);
  await clickWait(page, '#contractdetail #save');
  await page.locator('#filter #searchkey').fill(d.number);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#filter #searchkey').press('Enter')]);
  await clickWait(page, 'a[id^=show_]:visible');
  await page.locator('#mstates').selectOption('6');
  await clickWait(page, '#bstatus');
}

async function setUsesnumber(page, value) {
  await page.goto('/index.php?p=App/Pages/Options');
  await page.locator('#business #usesnumber').selectOption(String(value));
  await clickWait(page, '#business button[type=submit]');
}

// Печатная форма документа (то, что уходит на принтер): текст страницы ShowDoc&arg=print/<id>
async function printForm(page, documentId) {
  await page.goto('/index.php?p=App/Pages/ShowDoc&arg=print/' + documentId);
  return (await page.locator('body').innerText()).replace(/\s+/g, ' ');
}

async function bodyText(page) {
  return (await page.locator('body').innerText()).replace(/\s+/g, ' ');
}

module.exports = { createContract, printForm, USESNUMBER_HOSPICE, setUsesnumber, blockExternal, login, messages, clickWait, pickAuto, createItem, createCustomer, createDoc, runReport, bodyText };
