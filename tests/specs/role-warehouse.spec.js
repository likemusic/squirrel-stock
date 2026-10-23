// Роль «Склад» для работника склада (TODO 2.17, cli/role-warehouse.php): все, кроме раздела «Система», импорта,
// «Обновления программы», восстановления из копии и настроек автокопии. Проверяется: меню без запрещенного, прямой адрес
// запрещенной страницы не открывается, рабочие сценарии проходят — выдача расходников, выдача и возврат оборудования,
// отчеты, копия (скачать и в папку); у admins страницы по-прежнему открываются.
const fs = require('fs');
const { execFileSync } = require('child_process');
const { test, expect } = require('../fixtures');
const { bodyText, clickWait, pickAuto, messages, runReport } = require('../helpers/zippy');
const { loginAs } = require('../helpers/users');
const { seed } = require('../helpers/seed');
const { query, value, esc } = require('../helpers/db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const DENIED = /Нет права доступа к странице|Немає права доступу до сторінки|имеет доступ только|має доступ тільки/;
// раздел «Система» и закрытые сервисы: [адрес, есть ли пункт в меню]
const SYSTEM = ['Options', 'Admin', 'MenuList', 'Firm', 'Users', 'Roles', 'PosList', 'Subscribes', 'CronTab', 'SystemLog', 'Update'];
const SERVICES = ['Import', 'Import1C', 'ImportEquipmentHistory', 'AppUpdate'];
const url = p => '/index.php?p=App/Pages/' + p;

test('роль «Склад»: меню и страницы без «Системы», импорта и обновления; выдача, возврат, отчеты, копия', async ({ browser, page: adminPage, uniq }, testInfo) => {
  test.setTimeout(5 * 60_000);
  const role = 'Склад-' + uniq, login = 'sk' + uniq.toLowerCase(), pass = 'Пароль-' + uniq;
  const out = execFileSync('docker', ['exec', '-u', 'www-data', '-e', 'SQS_USER_PASS=' + pass, WEB, 'php', 'cli/role-warehouse.php', '--name=' + role, '--user=' + login], { encoding: 'utf8' });
  expect(out).toMatch(/Создана роль/);
  expect(out, 'пароль задан — не печатается').not.toContain(pass);
  const dir = `/tmp/sqs-role-${uniq}`;
  let context;
  try {
    // повторный запуск — обновление, не вторая роль
    execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'php', 'cli/role-warehouse.php', '--name=' + role, '--user=' + login]);
    expect(value(`select count(*) from roles where rolename = '${esc(role)}'`)).toBe('1');
    // оперативное меню — выдачи и формы выдачи (как в cli/charity-profile.php)
    const smart = execFileSync('docker', ['exec', WEB, 'php', '-r', `require "init.php"; $r = \\App\\Entity\\UserRole::getFirst("rolename = " . \\ZDB\\DB::getConnect()->qstr($argv[1]));
      echo implode(",", \\ZDB\\DB::getConnect()->GetCol("SELECT meta_name FROM metadata WHERE meta_id IN (" . ($r->smartmenu ?: "0") . ") ORDER BY meta_name"));`, "--", role], { encoding: 'utf8' }).trim();
    expect(smart).toBe('ConsumablesIssue,EquipmentIssue,GIList');

    let page;
    ({ context, page } = await loginAs(browser, login, pass));

    // меню
    await page.goto('/');
    for (const p of SYSTEM) await expect(page.locator(`a[href="/index.php?p=App/Pages/${p}"]`), 'в меню нет ' + p).toHaveCount(0);
    for (const p of SERVICES) await expect(page.locator(`a[href$="App/Pages/Service/${p}"]`), 'в меню нет ' + p).toHaveCount(0);
    for (const p of ['Service/Backup', 'Service/ConsumablesIssue', 'Service/EquipmentIssue', 'Report/StoreItems']) {
      await expect(page.locator(`a[href$="App/Pages/${p}"]`).first(), 'в меню есть ' + p).toBeAttached();
    }

    // прямые адреса
    for (const p of [...SYSTEM, ...SERVICES.map(s => 'Service/' + s)]) {
      await page.goto(url(p));
      expect(await bodyText(page), p).toMatch(DENIED);
    }

    // копия: скачать и в папку — можно; восстановления и настроек автокопии на странице нет
    await page.goto(url('Service/Backup'));
    expect(await bodyText(page)).not.toMatch(DENIED);
    await expect(page.locator('#rform, #sform, #backup-status')).toHaveCount(0);
    const [download] = await Promise.all([page.waitForEvent('download', { timeout: 60_000 }), page.locator('#bform #create').click()]);
    const zip = testInfo.outputPath('role.zip');
    await download.saveAs(zip);
    expect(fs.readFileSync(zip).subarray(0, 2).toString()).toBe('PK');
    execFileSync('docker', ['exec', '-u', 'www-data', WEB, 'mkdir', '-p', dir]);
    await page.goto(url('Service/Backup'));
    await page.locator('#uform #usbpath').fill(dir);
    await clickWait(page, '#uform #copyto');
    expect(execFileSync('docker', ['exec', WEB, 'ls', dir], { encoding: 'utf8' })).toMatch(/squirrelstock-.*\.zip/);

    // выдача расходников
    const code = 'RW-' + uniq, eq = 'RWE-' + uniq, inv = 'INV' + uniq, who = 'Получатель ' + uniq;
    seed({ items: [{ name: 'Перевязка ' + code, code }, { name: 'Кровать ' + eq, code: eq, serial: true }],
           docs: [{ type: 'IncomeItem', date: '2026-10-01', rows: [{ code, qty: 5, price: 0 }, { code: eq, qty: 1, price: 0, sn: inv }] }] });
    await page.goto(url('Service/ConsumablesIssue'));
    await page.locator('#cform #customer').fill(who);
    await page.locator('#cform #date').fill('2026-10-10');
    await pickAuto(page, '#cform #item', code, code);
    await page.locator('#cform #qty').fill('2');
    await clickWait(page, '#cform #additem');
    await clickWait(page, '#cform #issue');
    expect(Number(value(`select coalesce(sum(qty), 0) from store_stock s join items i on i.item_id = s.item_id where i.item_code = '${code}'`)), (await messages(page)).join(' ')).toBe(3);

    // выдача оборудования и возврат
    await page.goto(url('Service/EquipmentIssue'));
    await page.locator('#iform #customer').fill(who);
    await page.locator('#iform #date').fill('2026-10-11');
    await pickAuto(page, '#iform #unit', inv, inv);
    await clickWait(page, '#iform #addunit');
    await clickWait(page, '#iform #issue');
    const sn = () => Number(value(`select coalesce(sum(qty), 0) from store_stock where snumber = '${inv}'`));
    expect(sn(), (await messages(page)).join(' ')).toBe(0);
    const issue = value(`select max(d.document_id) from documents d join metadata m on m.meta_id = d.meta_id join customers c on c.customer_id = d.customer_id
                         where m.meta_name = 'GoodsIssue' and c.customer_name = '${esc(who)}' and d.document_date = '2026-10-11'`);
    await page.goto(url('Doc/ReturnIssue&arg=0/' + issue));
    await page.locator('#document_date').fill('2026-10-12');
    await clickWait(page, '#execdoc');
    expect(sn(), (await messages(page)).join(' ')).toBe(1);

    // отчеты
    await runReport(page, 'Report/EquipmentWhere', { texts: { searchtext: inv } });
    await expect(page.locator('#preview')).toContainText(inv);
    await page.goto(url('Report/StoreItems'));
    expect(await bodyText(page)).not.toMatch(DENIED);
  } finally {
    await context?.close();
    execFileSync('docker', ['exec', WEB, 'rm', '-rf', dir]);
    query(`delete from users where userlogin = '${esc(login)}'; delete from roles where rolename = '${esc(role)}'`);
  }

  // admins — как раньше
  for (const p of ['Firm', 'Service/Backup']) {
    await adminPage.goto(url(p));
    expect(await bodyText(adminPage), 'admin: ' + p).not.toMatch(DENIED);
  }
  await expect(adminPage.locator('#rform #restore')).toBeVisible();
});
