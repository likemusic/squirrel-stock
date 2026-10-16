// Импорт ведомости 1С: дата оприходования. Пусто — дата ведомости (конец периода из заголовка); дата раньше начала
// актуального периода — отказ с объяснением или, если администратор отметил, ограничение снимается на время импорта
// и возвращается (при первом переносе ОСВ 01 приходуется датой раньше первой выдачи из истории).
const path = require('path');
const { test, expect } = require('../fixtures');
const { clickWait, messages } = require('../helpers/zippy');
const { value } = require('../helpers/db');
const { execFileSync } = require('child_process');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';

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

test('импорт ведомости 1С: дата ведомости из заголовка', async () => {
  // период — в строке с названием или отдельной строкой («за Сентябрь 2026 г.» в своей ячейке, как в выгрузке хосписа)
  // от www-data: init.php от root создал бы журнал дня, который сайт потом не может писать
  const out = execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'php', '-r', `$_SERVER['HTTP_HOST'] = 'localhost'; $_SERVER['SERVER_PORT'] = 80; require '/var/www/html/init.php';
    foreach ([[['Ведомость'], ['за Сентябрь 2026 г.']], [['Оборотно-сальдовая ведомость по счету 01.1 за 26.05.26']], [['x за 1 квартал 2026 г.']],
              [['x за 01.05.26 - 31.05.26']], [['x за Май 2026 г.']], [['x за 2025 г.']], [['нет периода']]] as $r) {
      $d = \\App\\Pages\\Service\\Import1C::osvDate($r); echo ($d ? date('Y-m-d', $d) : 'null'), ' '; }`], { encoding: 'utf8' });
  expect(out.trim()).toBe('2026-09-30 2026-05-26 2026-03-31 2026-05-31 2026-05-31 2025-12-31 null');
});
