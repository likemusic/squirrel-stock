// Импорт ведомости 1С: дата оприходования. Пусто — дата ведомости (конец периода из заголовка); дата раньше начала
// актуального периода — отказ с объяснением или, если администратор отметил, ограничение снимается на время импорта
// и возвращается (при первом переносе ОСВ 01 приходуется датой раньше первой выдачи из истории).
const path = require('path');
const { test, expect } = require('../fixtures');
const { clickWait, messages } = require('../helpers/zippy');
const { value } = require('../helpers/db');

const lastImportDate = () => value(`select concat(document_id, '|', date(document_date)) from documents where notes like 'Імпорт відомості 1С%' order by document_id desc limit 1`);

test('импорт ведомости 1С: дата оприходования и закрытый период', async ({ page }) => {
  const setActual = async date => {
    await page.goto('/index.php?p=App/Pages/Options');
    const was = await page.locator('#business #actualdate').inputValue();
    await page.locator('#business #actualdate').fill(date);
    await clickWait(page, '#business button[type=submit], #business input[type=submit]');
    return was;
  };
  const run = async (date, lift) => {
    await page.goto('/index.php?p=App/Pages/Service/Import1C');
    expect(await page.locator('#iform #date').inputValue()).toBe('');   // по умолчанию пусто — дата ведомости
    if (date) await page.locator('#iform #date').fill(date);
    if (lift) await page.locator('#iform #liftperiod').check();
    await page.locator('#iform #filename').setInputFiles(path.join(__dirname, '..', 'fixtures', 'osv-10.xls'));
    await clickWait(page, '#iform #import');
    return (await messages(page)).join(' | ');
  };
  const actual = await setActual('2026-01-01');
  try {
    // пусто: ведомость «за Сентябрь 2026 г.» — 30.09.2026
    expect(await run('')).toContain('дата оприбуткування 30.09.2026');
    expect(lastImportDate().split('|')[1]).toBe('2026-09-30');

    // раньше начала актуального периода без отметки — ничего не записано, понятная причина
    const before = lastImportDate();
    expect(await run('2017-12-31')).toMatch(/Дата оприбуткування 31\.12\.2017 раніше початку актуального періоду з налаштувань \(01\.01\.2026\)/);
    expect(lastImportDate()).toBe(before);

    // с отметкой администратора — документ датой 31.12.2017, начало периода вернулось
    const msg = await run('2017-12-31', true);
    expect(msg).toContain('дата оприбуткування 31.12.2017');
    expect(msg).toMatch(/перенесено з 01\.01\.2026 на 31\.12\.2017 і повернуто/);
    expect(lastImportDate().split('|')[1]).toBe('2017-12-31');
    await page.goto('/index.php?p=App/Pages/Options');
    expect(await page.locator('#business #actualdate').inputValue()).toBe('2026-01-01');
  } finally {
    await setActual(actual);
  }
});
