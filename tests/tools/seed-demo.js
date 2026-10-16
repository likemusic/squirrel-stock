// Демо-база SquirrelStock для показа: вымышленная благотворительная организация, которая бесплатно выдает
// расходники и оборудование. Все данные выдуманы.
// Запускать на ЧИСТОЙ базе стенда:
//   cd docker && docker compose down -v && SQS_LANG=ru docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
//   cd ../tests && node tools/seed-demo.js
// Затем «Сервисные страницы → Резервная копия» дает zip для демо-сервера.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('@playwright/test');
const { blockExternal, login, clickWait, createCustomer, createDoc, messages, pickAuto } = require('../helpers/zippy');
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
const FIRM = 'Благотворительная организация (демо)';   // своя организация (выдает и принимает)
const ORG = 'Пансионат (демо)';   // получает «по запросу»
const GASTRO = new Set(['Подопечный 003', 'Подопечный 007', 'Подопечный 011', 'Подопечный 014']);   // получают питание для гастростомы
const LEFT_CARE = new Set(['Подопечный 015', 'Подопечный 016']);
const SENT_BY = ['Выездная служба', 'Врач-педиатр поликлиники', 'Социальный работник', 'Отделение паллиативной помощи'];

const CONSUMABLES = ['Подгузники для взрослых размер L, 30 шт', 'Подгузники детские размер 4, 50 шт', 'Подгузники детские размер 5, 44 шт',
  'Пеленки впитывающие 60х60, 30 шт', 'Пеленки впитывающие 60х90, 30 шт', 'Перчатки нитриловые нестерильные M, 100 шт',
  'Шприц 5 мл одноразовый', 'Шприц 10 мл одноразовый', 'Шприц 20 мл одноразовый', 'Салфетки влажные для ухода, 80 шт',
  'Катетер аспирационный CH 10', 'Катетер аспирационный CH 12', 'Крем защитный под подгузник 200 мл', 'Пена очищающая для кожи 400 мл',
  'Канюля носовая кислородная', 'Фильтр для ИВЛ тепловлагообменный'];
const GASTRO_ITEMS = ['Зонд гастростомический 14 Fr', 'Удлинитель для гастростомы', 'Питание энтеральное 500 мл', 'Шприц Жане 150 мл'];
const SMALL_EQ = ['Ходунки детские', 'Ванна для купания детская', 'Стул для купания'];   // мелкое оборудование без номеров, количеством
const TUBES = ['Зонд гастростомический 14 Fr', 'Зонд гастростомический 16 Fr'];   // интервал выдачи — раз в 90 дней

const codeOf = name => value(`select item_code from items where itemname = '${esc(name)}'`);

// выдача расходников короткой формой «Сервисные страницы → Выдача расходников»; возвращает номер созданной накладной
async function consumablesIssue(page, { date, customer, notes, rows }) {
  await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
  await pickAuto(page, '#cform #customer', customer, customer);   // существующий получатель — из подсказки
  await page.locator('#cform #date').fill(date);
  for (const { code, qty } of rows) {
    await pickAuto(page, '#cform #item', code, code);
    await page.locator('#cform #qty').fill(String(qty));
    await clickWait(page, '#cform #additem');
  }
  if (notes) await page.locator('#cform #notes').fill(notes);
  const before = value("select coalesce(max(document_id), 0) from documents");
  await clickWait(page, '#cform #issue');
  const number = value(`select d.document_number from documents d join metadata m on m.meta_id = d.meta_id
                        where m.meta_name = 'GoodsIssue' and d.document_id > ${before} and d.state = 5 order by d.document_id desc limit 1`);
  if (!number) throw new Error(`выдача расходников ${customer} ${date}: ${(await messages(page)).join(' | ')}`);
  return { number };
}

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
  // организация: в печатной форме выдачи строка «Выдал», возврата — «Принял» (название пишется в документ при создании)
  await page.goto('/index.php?p=App/Pages/Firm');
  await page.locator('#firmform #firm_name').fill(FIRM);
  await clickWait(page, '#firmform #save');
  if (value(`select from_base64(optvalue) like '%${esc(FIRM)}%' from options where optname = 'firm'`) !== '1') throw new Error('название организации не сохранено');

  // 2. Склады-состояния оборудования и категории
  for (const name of ['Ремонт', 'К списанию']) {   // «К списанию» — пустой, для показа
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
  const inList = names => names.map(n => `'${esc(n)}'`).join(',');
  query(`update items set cat_id = ${catId('Гастростомы')} where itemname in (${inList(GASTRO_ITEMS)})`);
  query(`update items set cat_id = ${catId('Оборудование')} where itemname in (${inList(SMALL_EQ)})`);
  // интервал выдачи (справочник «Интервалы выдачи»): гастростомическая трубка одному получателю — не чаще раза в 90 дней
  query(`insert into sqs_issue_interval (kind, ref_id, days) select 'item', item_id, 90 from items where itemname in (${inList(TUBES)})`);
  // минимальный остаток (карточка позиции «Мин. количество»): трех позиций к концу выдач меньше минимума — «Ниже минимального остатка»
  for (const [name, min] of [['Подгузники детские размер 5, 44 шт', 50], ['Зонд гастростомический 14 Fr', 12], ['Перчатки нитриловые нестерильные L, 100 шт', 40],
    ['Шприц 5 мл одноразовый', 300], ['Пеленки впитывающие 60х90, 30 шт', 100]]) {
    query(`update items set minqty = ${min} where itemname = '${esc(name)}'`);
  }
  // артикулы для подсказки при вводе: у импортированных позиций их нет
  query("update items set item_code = concat('П-', lpad(item_id, 4, '0')) where item_code is null or item_code = ''");

  // 4. Получатели с признаками-тегами: только «подопечный», «по запросу», «снят с опеки» (ответ хосписа)
  const tag = (name, t) => query(`insert into taglist (tag_name, tag_type, item_id) select '${esc(t)}', 1, customer_id from customers where customer_name = '${esc(name)}'`);
  for (const [i, name] of [...WARDS, ...ON_REQUEST, ORG].entries()) {
    await createCustomer(page, { name, type: 1, phone: `029${String(1000000 + i * 7919).slice(-7)}`, address: `г. Минск, ул. Демонстрационная, ${i + 1}` });
    tag(name, WARDS.includes(name) ? 'подопечный' : 'по запросу');
    if (LEFT_CARE.has(name)) tag(name, 'снят с опеки');
  }
  query("delete from taglist where tag_name = ''");   // форма контрагента сохраняет пустую метку
  log('получатели', WARDS.length + ON_REQUEST.length + 1);

  // 5. Выдачи расходников июль — октябрь 2026 — через «Выдачу расходников»
  const issues = [];
  for (const month of ['07', '08', '09', '10']) {
    const days = month == '10' ? 14 : 28;
    for (let k = 0; k < 9; k++) {
      const who = k == 8 && month != '10' ? ORG : pick(rnd(5) == 0 ? ON_REQUEST : WARDS.filter(w => !LEFT_CARE.has(w) || month == '07'));
      const items = new Set();
      while (items.size < 2 + rnd(3)) items.add(pick(CONSUMABLES));
      if (GASTRO.has(who)) { items.add(GASTRO_ITEMS[2]); if (rnd(2)) items.add(pick(GASTRO_ITEMS)); }
      const date = `2026-${month}-${String(1 + Math.floor(k * days / 9) + rnd(2)).padStart(2, '0')}`;
      const rows = [...items].map(n => ({ code: codeOf(n), qty: 1 + rnd(who == ORG ? 10 : 4) }));
      const r = await consumablesIssue(page, { date, customer: who, rows });
      issues.push({ ...r, date, who });
    }
    log('выдачи', month);
  }

  // три получателя с полной историей за июль — октябрь: ежемесячные расходники, у Подопечного 003 еще питание и трубки.
  // Вторая трубка выдана через 49 дней — раньше 90-дневного интервала (повредилась): форма предупреждает, выдать можно;
  // на показе новая выдача трубки Подопечному 003 тоже даст предупреждение.
  const regular = { 'Подопечный 002': ['Подгузники для взрослых размер L, 30 шт', 'Пеленки впитывающие 60х90, 30 шт'],
    'Подопечный 003': ['Подгузники детские размер 5, 44 шт', 'Питание энтеральное 500 мл', 'Шприц Жане 150 мл'],
    'Подопечный 009': ['Подгузники детские размер 4, 50 шт', 'Салфетки влажные для ухода, 80 шт'] };
  for (const month of ['07', '08', '09', '10']) {
    for (const [k, [who, names]] of Object.entries(regular).entries()) {
      await consumablesIssue(page, { date: `2026-${month}-${String(2 + k * 3).padStart(2, '0')}`, customer: who,
        rows: names.map(n => ({ code: codeOf(n), qty: n.startsWith('Питание') ? 30 : 2 })) });
    }
  }
  for (const [date, notes] of [['2026-07-06', ''], ['2026-08-24', 'Трубка повредилась — выдана раньше срока']]) {
    await consumablesIssue(page, { date, customer: 'Подопечный 003', notes, rows: [{ code: codeOf(TUBES[0]), qty: 1 }] });
  }
  log('история трех получателей');

  // две позиции 1С с одним названием и разными ценами: в акте за сентябрь — одна строка с названием из 1С
  const gloves = query(`select item_code from items where itemname = 'Перчатки нитриловые нестерильные M, 100 шт' order by item_id`).map(r => r[0]);
  await consumablesIssue(page, { date: '2026-09-18', customer: ORG, rows: gloves.map((code, k) => ({ code, qty: 2 + k })) });

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

  // 7. Оборудование через «Выдачу оборудования» (договор + выдача одной формой); у части единиц — заводской номер.
  // Сроки трех договоров — от даты сборки: истек и истекают в ближайшие 14 дней, чтобы на главной было напоминание.
  const units = name => query(`select s.item_id, s.snumber from store_stock s join items i on i.item_id = s.item_id
                                where i.itemname = '${esc(name)}' and s.qty > 0 and s.store_id = 1 order by s.snumber`);
  for (const name of ['Концентратор кислорода 5 л', 'Отсасыватель медицинский В-40', 'Аппарат НИВЛ', 'Кровать функциональная медицинская']) {
    for (const [k, [item, sn]] of units(name).entries()) {
      if (k % 2 == 0) query(`insert into sqs_unit (item_id, snumber, factory_no) values (${item}, '${esc(sn)}', 'ZV-${sn.slice(-4)}-${2020 + k}')`);
    }
  }
  const day = n => { const d = new Date(); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };
  const eq = [['Концентратор кислорода 5 л', 'Подопечный 002', '2026-07-03', null], ['Отсасыватель медицинский В-40', 'Подопечный 003', '2026-07-10', null],
    ['Кровать функциональная медицинская', 'Подопечный 005', day(-75), day(-3)], ['Концентратор кислорода 5 л', 'Подопечный 007', '2026-08-04', null],
    ['Пульсоксиметр', 'Семья по запросу 01', day(-40), day(5)], ['Кресло-коляска детская', 'Подопечный 009', '2026-08-25', null],
    ['Аппарат НИВЛ', 'Подопечный 011', day(-50), day(11)], ['Отсасыватель медицинский В-40', 'Подопечный 015', '2026-07-15', null]];
  const given = [];
  for (const [n, [name, who, date, to]] of eq.entries()) {
    const number = `${101 + n}/26`;
    const [, sn] = units(name)[0];
    await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
    await pickAuto(page, '#iform #customer', who, who);   // существующий получатель — из подсказки, иначе форма создаст нового
    await page.waitForLoadState('networkidle');
    await page.locator('#iform #contract_number').fill(number);
    await page.locator('#iform #date').fill(date);
    if (to) {
      await page.locator('#iform #ondemand').setChecked(false);
      await page.locator('#iform #enddate').fill(to);
    }
    await page.locator('#iform #referrer').fill(pick(SENT_BY));
    await pickAuto(page, '#iform #unit', sn, sn);
    await clickWait(page, '#iform #addunit');
    await page.locator('#iform #notes').fill('Выдача оборудования по договору');
    await clickWait(page, '#iform #issue');
    // всплывающее сообщение бывает уже скрыто — проверка по базе
    if (value(`select count(*) from contracts where contract_number = '${number}'`) != '1') throw new Error(`выдача ${number}: ${(await messages(page)).join(' | ')}`);
    const doc = value(`select d.document_number from documents d join metadata m on m.meta_id = d.meta_id join customers c on c.customer_id = d.customer_id
                       where m.meta_name = 'GoodsIssue' and c.customer_name = '${esc(who)}' order by d.document_id desc limit 1`);
    given.push({ number: doc, sn, who });
  }
  log('оборудование выдано', given.length);
  // снят с опеки — вернули оборудование
  const back = given[7];
  const backId = value(`select document_id from documents where document_number = '${esc(back.number)}' order by document_id desc limit 1`);
  await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + backId);
  await page.locator('#document_date').fill('2026-10-01');
  await page.locator('#notes').fill('Возврат оборудования: снят с опеки');
  await clickWait(page, '#execdoc');
  // мелкое оборудование без номеров — количеством, расходной накладной по договору получателя (как его заводит импорт истории);
  // в «Оборудование: где сейчас» — блок «Мелкое оборудование на руках». Ванну Подопечный 005 вернул — на руках ее нет.
  const small = [['Подопечный 002', '101/26', '2026-07-20', [[SMALL_EQ[1], 1], [SMALL_EQ[2], 1]]], ['Подопечный 009', '106/26', '2026-09-02', [[SMALL_EQ[0], 1]]],
    ['Подопечный 005', '103/26', day(-60), [[SMALL_EQ[1], 1]]], ['Подопечный 005', '103/26', day(-60), [[SMALL_EQ[0], 1]]]];
  const smallDocs = [];
  for (const [who, contract, date, rows] of small) {
    const r = await createDoc(page, 'GoodsIssue', { date, customer: who, payment: 0, contract, notes: 'Выдача мелкого оборудования по договору',
      rows: rows.map(([n, qty]) => ({ code: codeOf(n), qty, price: 0 })) });
    smallDocs.push(r.number);
  }
  const bathId = value(`select document_id from documents where document_number = '${esc(smallDocs[2])}' order by document_id desc limit 1`);
  await page.goto('/index.php?p=App/Pages/Doc/ReturnIssue&arg=0/' + bathId);
  await page.locator('#document_date').fill(day(-12));
  await page.locator('#notes').fill('Возврат: ванна больше не нужна');
  await clickWait(page, '#execdoc');
  log('мелкое оборудование', smallDocs.length);

  // единица в ремонт
  for (const [name, store, note] of [['Отсасыватель медицинский В-40', 'Ремонт', 'Не работает мотор']]) {
    const [, sn] = units(name)[0];
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
