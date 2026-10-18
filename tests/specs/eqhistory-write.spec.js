// Импорт истории оборудования: то, что нашлось на репетиции переноса реальных данных (16.10.2026).
//  - единица выдана повторно раньше даты возврата по прошлой выдаче — ошибка проверки (раньше проходило молча
//    или роняло запись);
//  - договор, записанный на другое ФИО, — ошибка проверки, а не сбой при записи;
//  - возврат будущим числом — заметка: единица на руках, дата — срок договора;
//  - событие, которое не записалось, — в отчете с причиной и с тем, что из-за него пропущено; остальное записывается;
//  - запись частями: страница сама продолжает запись, пока не запишет всё (хостинг обрывает долгий запрос).
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { clickWait } = require('../helpers/zippy');
const { query, value } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const qty = sn => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${sn}'`));
// получатель последней выдачи единицы
const holder = sn => value(`select c.customer_name from entrylist e join store_stock s on s.stock_id = e.stock_id join documents d on d.document_id = e.document_id
                            join customers c on c.customer_id = d.customer_id where s.snumber = '${sn}' and d.meta_id = (select meta_id from metadata where meta_name = 'GoodsIssue')
                            order by d.document_date desc, d.document_id desc limit 1`);
const day = n => new Date(Date.now() + n * 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

test('импорт истории: пересечения, договор с двумя ФИО, будущий возврат, сбой записи, запись частями', async ({ page, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const base = 1000000 + Math.floor(Math.random() * 8000000);
  const inv = k => '0' + (base + k);
  const cn = (k, yy) => (Number(String(base).slice(-5)) + k) + '/' + yy;
  const who = k => `Подопечный ${uniq}-${String(k).padStart(3, '0')}`;
  const future = day(30);

  const dir = testInfo.outputPath('eqhw');
  const tmp = `/tmp/eqh-${uniq}`;
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', tmp]);
  execFileSync('docker', ['cp', path.join(__dirname, '..', 'fixtures', 'make-eqhistory-write.php'), `${WEB}:${tmp}/make.php`]);
  execFileSync('docker', ['exec', WEB, 'php', `${tmp}/make.php`, tmp, String(base), uniq, future]);
  require('fs').mkdirSync(dir, { recursive: true });
  for (const f of ['osv-01.xls', 'census.xlsx']) execFileSync('docker', ['cp', `${WEB}:${tmp}/${f}`, path.join(dir, f)]);

  const setActual = async date => {
    await page.goto('/index.php?p=App/Pages/Options');
    const was = await page.locator('#business #actualdate').inputValue();
    await page.locator('#business #actualdate').fill(date);
    await clickWait(page, '#business button[type=submit], #business input[type=submit]');
    return was;
  };
  const actual = await setActual('2010-01-01');
  try {
    await page.goto('/index.php?p=App/Pages/Service/Import1C');
    await page.locator('#iform #date').fill('2024-01-01');
    await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
    await clickWait(page, '#iform #import');
    expect(qty(inv(4))).toBe(1);

    const stat = async k => Number((await page.locator(`#eqh-summary tr[data-stat=${k}] td`).last().innerText()).trim());
    await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
    await clickWait(page, '#iform #check');

    // проверка: две ошибки, план без строк с ошибками
    const problems = (await page.locator('#eqh-problems tr.error').allInnerTexts()).join('\n');
    expect(problems).toContain('Одиницю видано повторно раніше повернення за попередньою видачею (повернення 01.05.2025)');
    expect(problems).toContain(`Договір ${cn(4, '25')} уже є з іншим отримувачем`);
    expect(await page.locator('#eqh-problems tr.error').count()).toBe(2);
    const fut = future.split('-').reverse().join('.');
    expect((await page.locator('#eqh-problems tr.note').allInnerTexts()).join('\n')).toContain(`Повернення майбутньою датою (${fut})`);
    expect(await stat('issue')).toBe(5);    // строки 3, 5, 6, 8, 9; строки 4 и 7 — ошибки
    expect(await stat('return')).toBe(2);   // строки 3 и 9; будущий возврат строки 8 — не возврат

    // к записи единицы одиночной модели на складе уже нет
    query(`update store_stock set qty = 0 where snumber = '${inv(4)}'`);

    // запись частями по 2 события: страница продолжает сама, ход виден
    query("delete from keyval where keyd = 'sqs_eqh_chunk'; insert into keyval (keyd, vald) values ('sqs_eqh_chunk', '2')");
    // части идут быстрее, чем видно глазу: ход считается по ответам сервера
    const parts = [];
    page.on('response', async r => {
      if (!r.request().isNavigationRequest()) return;
      const m = (await r.text().catch(() => '')).match(/id="eqh-progress"[^>]*>Запис: (\d+) з (\d+)/);
      if (m) parts.push(m[1] + '/' + m[2]);
    });
    await clickWait(page, '#iform #write');
    await expect(page.locator('#eqh-summary tr[data-stat=failed]')).toBeVisible({ timeout: 60_000 });
    expect(parts).toEqual(['2/7', '4/7', '6/7']);
    await expect(page.locator('#eqh-progress')).toHaveCount(0);
    expect(await stat('issue')).toBe(4);
    expect(await stat('return')).toBe(1);
    expect(await stat('failed')).toBe(1);
    expect(await stat('skipped')).toBe(1);
    const failed = (await page.locator('#eqh-problems tr.error', { hasText: 'Не записано' }).allInnerTexts()).join('\n');
    expect(failed).toMatch(/Не записано \(видача 01\.04\.2025\)/);
    expect(failed).toContain('Через це не записано: повернення 01.05.2025');

    expect(holder(inv(1))).toBe(who(3));          // цепочка единицы 1 не оборвалась на строке с ошибкой
    expect(qty(inv(1))).toBe(0);
    expect(qty(inv(3))).toBe(1);                  // выдача по чужому договору не записана
    expect(value(`select count(*) from customers where customer_name = '${who(5)}'`)).toBe('0');
    expect(holder(inv(5))).toBe(who(6));          // будущий возврат — единица на руках
    expect(qty(inv(5))).toBe(0);
    // срок договора (enddate в XML details, метка времени) — дата будущего возврата
    // (полночь по времени стенда — Europe/Minsk, UTC+3)
    expect(Number(value(`select extractvalue(details, '//enddate') from contracts where contract_number = '${cn(6, '25')}'`))).toBe(Date.parse(future + 'T00:00:00+03:00') / 1000);
    // временный файл разбора удален после записи (в таблицах персональные данные)
    expect(execFileSync('docker', ['exec', WEB, 'sh', '-c', `grep -l '${uniq}' /tmp/sqs-eqh-* 2>/dev/null || true`]).toString().trim()).toBe('');
    // ФИО получателя поправили в программе; повторная проверка тех же таблиц: записанное — «уже импортировано»,
    // а не «договор с другим получателем» (иначе отпал бы и возврат, вписанный в таблицу потом)
    query(`update customers set customer_name = '${who(3)} испр.' where customer_name = '${who(3)}'`);
    await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
    await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
    await clickWait(page, '#iform #check');
    expect((await page.locator('#eqh-problems tr.error').allInnerTexts()).join('\n')).not.toContain(`Договір ${cn(3, '25')}`);
    expect(await stat('done')).toBe(5);    // 4 выдачи и возврат
    expect(await stat('issue')).toBe(1);   // незаписанная выдача строки 9 — снова в плане, с возвратом
    expect(await stat('return')).toBe(1);
  } finally {
    query("delete from keyval where keyd = 'sqs_eqh_chunk'");
    await setActual(actual);
  }
});
