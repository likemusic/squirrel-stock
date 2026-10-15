// Действия в интерфейсе Zippy/SquirrelStock: вход, справочники, документы, отчёты.
// Перенесено из сценариев исследования (../Zippy/tools: lib.js, docs.js, reports.js).

const LOCAL = ['127.0.0.1', 'localhost'];

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

async function clickWait(page, sel) {
  await Promise.all([page.waitForLoadState('networkidle'), page.locator(sel).first().click()]);
  await page.waitForTimeout(300);
}

// Подсказка (autocomplete): ввести запрос и выбрать вариант.
// firstMustContain — ждать, пока первым в списке станет нужный вариант: список по недопечатанному запросу
// успевает показаться раньше («1C-03» → 1C-030 вместо 1C-039)
async function pickAuto(page, sel, query, expect, firstMustContain) {
  const inp = page.locator(sel);
  await inp.fill('');
  await inp.pressSequentially(query, { delay: 40 });
  let opt = page.locator('li a.dropdown-item:visible', { hasText: expect || '' }).first();
  if (firstMustContain) {
    opt = page.locator('li a.dropdown-item:visible').first();
    for (let i = 0; i < 40 && !((await opt.innerText().catch(() => '')).includes(firstMustContain)); i++) await page.waitForTimeout(200);
  }
  await opt.waitFor({ timeout: 8000 });
  await Promise.all([page.waitForLoadState('networkidle'), opt.click()]);
  await page.waitForTimeout(300);
}

async function createItem(page, { name, code, msr = 'шт', serial = false }) {
  await page.goto('/index.php?p=App/Pages/Reference/ItemList');
  await clickWait(page, '#addnew');
  await page.locator('#editname').fill(name);
  await page.locator('#editcode').fill(code);
  await page.locator('#editmsr').fill(msr);
  if (serial) await page.locator('#edituseserial').setChecked(true);
  await clickWait(page, '#save');
}

// type: 1 — физлицо (получатель), 2 — организация (донор, поставщик)
async function createCustomer(page, { name, type = 1 }) {
  await page.goto('/index.php?p=App/Pages/Reference/CustomerList');
  await clickWait(page, '#addnew');
  await page.locator('#editcustomername').fill(name);
  await page.locator('#edittype').selectOption(String(type));
  await clickWait(page, '#customerdetail button[type=submit], #customerdetail input[type=submit], #save');
}

// Документ через форму и проведение.
// type: IncomeItem | GoodsReceipt | GoodsIssue | OutcomeItem
// d: { date, customer, payment, notes, rows: [{ code, qty, price, sn, sdate }] }
// Возвращает номер, сообщения и то, что форма сама подставила в строки (цена, партия).
async function createDoc(page, type, d) {
  await page.goto('/index.php?p=App/Pages/Doc/' + type);
  const number = await page.locator('#document_number').inputValue();
  await page.locator('#document_date').fill(d.date);
  await page.locator('#docform #store').selectOption('1');
  if (d.customer) await pickAuto(page, '#docform #customer', d.customer, d.customer);
  if (d.payment !== undefined && await page.locator('#docform #payment').count()) await page.locator('#docform #payment').selectOption(String(d.payment));
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
  await clickWait(page, '#execdoc');
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

async function bodyText(page) {
  return (await page.locator('body').innerText()).replace(/\s+/g, ' ');
}

module.exports = { blockExternal, login, messages, clickWait, pickAuto, createItem, createCustomer, createDoc, runReport, bodyText };
