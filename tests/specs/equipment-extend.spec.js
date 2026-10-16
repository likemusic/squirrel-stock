// Продлить срок пользования оборудованием: кнопка в напоминании на главной и в «Оборудование: где сейчас» меняет дату
// окончания договора без смены статуса (договор «В работе» штатно не редактируется) и оставляет комментарий в договоре:
// было → стало, кто, когда.
const { test, expect } = require('../fixtures');
const { createItem, createDoc, pickAuto, clickWait, messages } = require('../helpers/zippy');
const { value } = require('../helpers/db');
const { xlsxText } = require('../helpers/xlsx');

// дата по местному времени (стенд — Europe/Minsk)
const day = n => new Date(Date.now() + n * 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const ru = iso => iso.split('-').reverse().join('.');

test('продлить срок: с главной и из «где сейчас»', async ({ page, uniq }) => {
  const code = 'EX-' + uniq, inv = 'INVX-' + uniq, who = 'Получатель ' + uniq;
  await createItem(page, { name: 'Кровать ' + uniq, code, serial: true });
  await createDoc(page, 'IncomeItem', { date: day(-60), rows: [{ code, qty: 1, price: 0, sn: inv }] });
  await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
  const number = await page.locator('#iform #contract_number').inputValue();
  await page.locator('#iform #customer').fill(who);
  await page.locator('#iform #date').fill(day(-30));
  await page.locator('#iform #ondemand').setChecked(false);
  await page.locator('#iform #enddate').fill(day(-3));
  await pickAuto(page, '#iform #unit', inv, inv);
  await clickWait(page, '#iform #issue');
  const cid = value(`select contract_id from contracts where contract_number = '${number}'`);
  expect(cid, 'договор создан').toMatch(/^\d+$/);

  // с главной: просроченный договор → новая дата через 60 дней → напоминание исчезает
  await page.goto('/');
  await clickWait(page, `#sqs-due tr:has-text("${inv}") a.sqs-extend`);
  await expect(page.locator('#extend-info')).toContainText(number);
  await expect(page.locator('#extend-info')).toContainText(who);
  await expect(page.locator('#extend-info')).toContainText(inv);
  await page.locator('#eform #ondemand').setChecked(false);
  await page.locator('#eform #enddate').fill(day(60));
  await clickWait(page, '#eform #save');
  expect((await messages(page)).join(' ')).toContain(ru(day(60)));
  await expect(page.locator('#sqs-due tr', { hasText: inv })).toHaveCount(0);
  expect(value(`select state from contracts where contract_id = ${cid}`), 'договор остался «В работе»').toBe('6');
  const note = value(`select concat(user_id, '|', message) from messages where item_type = 7 and item_id = ${cid} order by message_id desc limit 1`);
  expect(note, 'комментарий в договоре: было → стало, кто').toMatch(new RegExp(`^[1-9]\\d*\\|.*${ru(day(-3)).replace(/\./g, '\\.')}.*${ru(day(60)).replace(/\./g, '\\.')}`));

  // в «где сейчас» новая дата; оттуда же — «до востребования»
  const where = async () => {
    await page.goto('/index.php?p=App/Pages/Report/EquipmentWhere');
    await page.locator('#filter #searchtext').fill(inv);
    await clickWait(page, '#filter #onreport');
    return page.locator('#detail tr', { hasText: inv });
  };
  await expect(await where()).toContainText(ru(day(60)));
  await clickWait(page, `#detail tr:has-text("${inv}") a.sqs-extend`);
  await page.locator('#eform #ondemand').setChecked(true);
  await clickWait(page, '#eform #save');
  await expect(page.locator('h3, h4').first()).toContainText(/Оборудование: где сейчас|Обладнання: де зараз/);
  await expect(await where()).toContainText(/до востребования|до запитання/);
  expect(value(`select state from contracts where contract_id = ${cid}`)).toBe('6');

  // в выгрузке отчета (Excel, печать) кнопки нет
  const xls = await xlsxText(page.context().request, '/index.php?p=App/Pages/ShowReport&arg=xls/equipmentwhere');
  expect(xls).toContain(inv);
  expect(xls).not.toMatch(/продлить|подовжити/i);
});
