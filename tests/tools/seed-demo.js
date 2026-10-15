// Демо-база SquirrelStock для показа: вымышленная благотворительная организация, которая бесплатно выдает
// расходники и оборудование. Все данные выдуманы.
// Запускать на ЧИСТОЙ базе стенда:
//   cd docker && docker compose down -v && SQS_LANG=ru docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
//   cd ../tests && node tools/seed-demo.js
// Затем «Сервис → Резервная копия» дает zip для демо-сервера.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('@playwright/test');
const { blockExternal, login, clickWait, createCustomer, createDoc, createContract, messages } = require('../helpers/zippy');
const { query, value, esc } = require('../helpers/db');
const { webPhp } = require('../helpers/stand');

const BASE = process.env.SQS_BASE || 'http://127.0.0.1:8083';
const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

// детерминированный генератор: одинаковая демо-база при каждом запуске
let seed = 20261015;
const rnd = n => { seed = (seed * 1103515245 + 12345) % 2147483648; return seed % n; };
const pick = a => a[rnd(a.length)];

const WARDS = Array.from({ length: 16 }, (_, i) => `Подопечный ${String(i + 1).padStart(3, '0')}`);
const ON_REQUEST = ['Семья по запросу 01', 'Семья по запросу 02'];
const ORG = 'Пансионат (демо)';
const GASTRO = new Set(['Подопечный 003', 'Подопечный 007', 'Подопечный 011', 'Подопечный 014']);
const LEFT_CARE = new Set(['Подопечный 015', 'Подопечный 016']);
const SENT_BY = ['Выездная служба', 'Врач-педиатр поликлиники', 'Социальный работник', 'Отделение паллиативной помощи'];

const CONSUMABLES = ['Подгузники для взрослых размер L, 30 шт', 'Подгузники детские размер 4, 50 шт', 'Подгузники детские размер 5, 44 шт',
  'Пеленки впитывающие 60х60, 30 шт', 'Пеленки впитывающие 60х90, 30 шт', 'Перчатки нитриловые нестерильные M, 100 шт',
  'Шприц 5 мл одноразовый', 'Шприц 10 мл одноразовый', 'Шприц 20 мл одноразовый', 'Салфетки влажные для ухода, 80 шт',
  'Катетер аспирационный CH 10', 'Катетер аспирационный CH 12', 'Крем защитный под подгузник 200 мл', 'Пена очищающая для кожи 400 мл',
  'Канюля носовая кислородная', 'Фильтр для ИВЛ тепловлагообменный'];
const GASTRO_ITEMS = ['Зонд гастростомический 14 Fr', 'Удлинитель для гастростомы', 'Питание энтеральное 500 мл', 'Шприц Жане 150 мл'];

const codeOf = name => value(`select item_code from items where itemname = '${esc(name)}'`);

(async () => {
  const browser = await chromium.launch();
  let context = await browser.newContext({ baseURL: BASE });
  await blockExternal(context);
  let page = await context.newPage();
  page.on('dialog', d => d.accept());
  await login(page);

  // 1. Профиль, русские названия в базе; новая сессия, чтобы перечитать настройки
  log(webPhp('cli/charity-profile.php').trim());
  log(webPhp('cli/localize-db.php', 'ru').trim());
  await context.close();
  context = await browser.newContext({ baseURL: BASE });
  await blockExternal(context);
  page = await context.newPage();
  page.on('dialog', d => d.accept());
  await login(page);
  await page.goto('/index.php?p=App/Pages/Options');
  await page.locator('#common #money2str').selectOption('ru');
  await clickWait(page, '#common button[type=submit]');

  // 2. Склады-состояния оборудования и категории
  for (const name of ['Ремонт', 'К списанию']) {
    await page.goto('/index.php?p=App/Pages/Reference/StoreList');
    await clickWait(page, '#storeadd');
    await page.locator('#storeeditname').fill(name);
    await clickWait(page, '#storesave');
  }
  for (const name of ['Расходные материалы', 'Гастростомы', 'Оборудование']) {
    await page.goto('/index.php?p=App/Pages/Reference/CategoryList');
    await clickWait(page, '#addnew');
    await page.locator('#editcat_name').fill(name);
    await clickWait(page, '#categorydetail #save');
  }
  const catId = name => value(`select cat_id from item_cat where cat_name = '${esc(name)}'`);
  log('склады и категории');

  // 3. Номенклатура и остатки из ведомостей 1С (материалы 30.06.2026, оборудование)
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'sqs-demo-'));
  execFileSync('docker', ['cp', path.join(__dirname, 'demo-ledgers.php'), `${WEB}:/tmp/demo-ledgers.php`]);
  webPhp('/tmp/demo-ledgers.php', '/tmp');
  for (const [file, cat] of [['demo-osv-10.xls', 'Расходные материалы'], ['demo-osv-01.xls', 'Оборудование']]) {
    execFileSync('docker', ['cp', `${WEB}:/tmp/${file}`, path.join(tmp, file)]);
    await page.goto('/index.php?p=App/Pages/Service/Import1C');
    await page.locator('#iform #date').fill('2026-06-30');
    await page.locator('#iform #cat').selectOption(String(catId(cat)));
    await page.locator('#iform #filename').setInputFiles(path.join(tmp, file));
    await clickWait(page, '#iform #import');
    log((await messages(page)).join(' | '));
  }
  query(`update items set cat_id = ${catId('Гастростомы')} where itemname in (${GASTRO_ITEMS.map(n => `'${esc(n)}'`).join(',')})`);
  // артикулы для подсказки при вводе: у импортированных позиций их нет
  query("update items set item_code = concat('П-', lpad(item_id, 4, '0')) where item_code is null or item_code = ''");

  // 4. Получатели с признаками-тегами
  const tag = (name, t) => query(`insert into taglist (tag_name, tag_type, item_id) select '${esc(t)}', 1, customer_id from customers where customer_name = '${esc(name)}'`);
  for (const [i, name] of [...WARDS, ...ON_REQUEST, ORG].entries()) {
    await createCustomer(page, { name, type: 1, phone: `029${String(1000000 + i * 7919).slice(-7)}`, address: `г. Минск, ул. Демонстрационная, ${i + 1}` });
    tag(name, WARDS.includes(name) ? 'подопечный' : name == ORG ? 'организация' : 'по запросу');
    if (GASTRO.has(name)) tag(name, 'гастростома');
    if (LEFT_CARE.has(name)) tag(name, 'снят с опеки');
  }
  log('получатели', WARDS.length + ON_REQUEST.length + 1);

  // 5. Выдачи расходников июль — октябрь 2026
  const issues = [];
  for (const month of ['07', '08', '09', '10']) {
    const days = month == '10' ? 14 : 28;
    for (let k = 0; k < 9; k++) {
      const who = k == 8 && month != '10' ? ORG : pick(rnd(5) == 0 ? ON_REQUEST : WARDS.filter(w => !LEFT_CARE.has(w) || month == '07'));
      const items = new Set();
      while (items.size < 2 + rnd(3)) items.add(pick(CONSUMABLES));
      if (GASTRO.has(who)) { items.add(GASTRO_ITEMS[2]); if (rnd(2)) items.add(pick(GASTRO_ITEMS)); }
      const date = `2026-${month}-${String(1 + Math.floor(k * days / 9) + rnd(2)).padStart(2, '0')}`;
      const rows = [...items].map(n => ({ code: codeOf(n), qty: 1 + rnd(who == ORG ? 10 : 4), price: 0 }));
      const r = await createDoc(page, 'GoodsIssue', { date, customer: who, payment: 0, notes: 'Выдача', rows });
      issues.push({ ...r, date, who });
    }
    log('выдачи', month);
  }

  // 6. Возвраты и списания с причинами
  for (const issue of [issues[3], issues[20]]) {
    const id = value(`select document_id from documents where document_number = '${esc(issue.number)}' order by document_id desc limit 1`);
    await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + id);
    const d = new Date(issue.date); d.setDate(d.getDate() + 5);
    await page.locator('#document_date').fill(d.toISOString().slice(0, 10));
    await page.locator('#notes').fill('Возврат: не подошел размер');
    await clickWait(page, '#execdoc');
  }
  for (const [date, reason, name, qty] of [['2026-07-31', 'Причина: порча — намокла упаковка', 'Пеленки впитывающие 60х90, 30 шт', 3],
    ['2026-08-29', 'Причина: истек срок годности', 'Питание энтеральное высококалорийное 200 мл', 12],
    ['2026-09-30', 'Причина: брак — повреждена упаковка', 'Шприц 10 мл одноразовый', 20]]) {
    await createDoc(page, 'OutcomeItem', { date, notes: reason, rows: [{ code: codeOf(name), qty }] });
  }
  log('возвраты и списания');

  // 7. Оборудование: договоры, выдачи, возврат, ремонт, к списанию
  const unit = name => query(`select s.snumber from store_stock s join items i on i.item_id = s.item_id where i.itemname = '${esc(name)}' and s.qty > 0 and s.store_id = 1 order by s.snumber limit 1`)[0][0];
  const eq = [['Концентратор кислорода 5 л', 'Подопечный 002', '2026-07-03', '2099-12-31'], ['Отсасыватель медицинский В-40', 'Подопечный 003', '2026-07-10', '2099-12-31'],
    ['Кровать функциональная медицинская', 'Подопечный 005', '2026-07-21', '2026-12-31'], ['Концентратор кислорода 5 л', 'Подопечный 007', '2026-08-04', '2099-12-31'],
    ['Пульсоксиметр', 'Семья по запросу 01', '2026-08-12', '2026-09-30'], ['Кресло-коляска детская', 'Подопечный 009', '2026-08-25', '2099-12-31'],
    ['Аппарат НИВЛ', 'Подопечный 011', '2026-09-08', '2099-12-31'], ['Отсасыватель медицинский В-40', 'Подопечный 015', '2026-07-15', '2099-12-31']];
  const given = [];
  for (const [n, [name, who, date, to]] of eq.entries()) {
    const number = `${101 + n}/26`;
    await createContract(page, { number, customer: who, from: date, to, note: pick(SENT_BY) });
    const sn = unit(name);
    const r = await createDoc(page, 'GoodsIssue', { date, customer: who, payment: 0, contract: number, notes: 'Выдача оборудования по договору', rows: [{ code: codeOf(name), qty: 1, price: 0, sn }] });
    given.push({ ...r, sn, who });
  }
  // снят с опеки — вернули оборудование
  const back = given[7];
  const backId = value(`select document_id from documents where document_number = '${esc(back.number)}' order by document_id desc limit 1`);
  await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + backId);
  await page.locator('#document_date').fill('2026-10-01');
  await page.locator('#notes').fill('Возврат оборудования: снят с опеки');
  await clickWait(page, '#execdoc');
  // перемещение на склады «Ремонт» и «К списанию»
  for (const [name, store, note] of [['Отсасыватель медицинский В-40', 'Ремонт', 'Не работает мотор'], ['Матрас противопролежневый', 'К списанию', 'Порван чехол, ремонт нецелесообразен']]) {
    const sn = unit(name);
    const to = value(`select store_id from stores where storename = '${esc(store)}'`);
    await page.goto('/index.php?p=App/Pages/Doc/MoveItem');
    await page.locator('#document_date').fill('2026-10-05');
    await page.locator('#docform #store').selectOption('1');
    await page.locator('#docform #tostore').selectOption(String(to));
    await page.locator('#docform #notes').fill(note);
    await clickWait(page, '#addrow');
    const inp = page.locator('#edititem');
    await inp.pressSequentially(codeOf(name), { delay: 40 });
    await page.locator('li a.dropdown-item:visible').first().click();
    await page.waitForLoadState('networkidle');
    await page.locator('#editsnumber').fill(sn);
    await page.locator('#editquantity').fill('1');
    await clickWait(page, '#saverow');
    await clickWait(page, '#execdoc');
    log('перемещение', name, sn, '→', store, (await messages(page)).join(' | '));
  }

  const stat = query("select (select count(*) from documents), (select count(*) from items), (select count(*) from customers), (select count(*) from contracts)")[0];
  log(`готово: документов ${stat[0]}, позиций ${stat[1]}, контрагентов ${stat[2]}, договоров ${stat[3]}`);
  await browser.close();
})().catch(e => { console.error('ОШИБКА', e.message); process.exit(1); });
