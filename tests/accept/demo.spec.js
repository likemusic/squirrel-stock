// Приемка пакета (TODO 2.23): сценарий показа (.local/DEMO_SCRIPT.md) на копии хостинга из пакета (tools/release/accept.sh)
// под admin и под ролью «Склад» (sklad, cli/role-warehouse.php). Главная, каждый пункт меню, выдачи, возврат, продление,
// акт в Excel, PDF, резервная копия; под admin — копия в папку и восстановление этой копии страницей, под sklad —
// закрытые страницы по прямому адресу. Снимки главной и меню — test-results/accept/shots/ для просмотра глазами.
// Тесты одной роли идут по порядку в одном входе; упавший тест не останавливает остальные.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { blockExternal, clickWait, pickAuto, messages, runReport } = require('../helpers/zippy');
const { xlsxText } = require('../helpers/xlsx');

const WEB = process.env.SQS_WEB_CONTAINER || 'sqs-accept-web';
const SHOTS = path.join(__dirname, '..', 'test-results', 'accept', 'shots');
const BK = '/var/www/squirrelstock-backup';
// ошибка на странице: сообщение программы о базе, ошибки и предупреждения PHP, временная папка под open_basedir
// (путь сервера не ищется: «Резервная копия» показывает admin папку копий)
const BAD = /Ошибка базы данных|Fatal error|Uncaught|Exception|Warning:|Notice:|Deprecated:|Невірний клас|Path must not be empty|open_basedir/;
const DENIED = /Нет права доступа к странице|Немає права доступу до сторінки|имеет доступ только|має доступ тільки/;
// закрытые для «Склада» страницы: раздел «Система», импорт, обновление программы
const CLOSED = ['Options', 'Admin', 'MenuList', 'Firm', 'Users', 'Roles', 'PosList', 'Subscribes', 'CronTab', 'SystemLog', 'Update',
  'Service/Import', 'Service/Import1C', 'Service/ImportEquipmentHistory', 'Service/AppUpdate'];
const day = n => new Date(Date.now() + n * 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const text = async page => (await page.locator('body').innerText()).replace(/\s+/g, ' ');

fs.mkdirSync(SHOTS, { recursive: true });

for (const [login, pass] of [['admin', process.env.SQS_PASS], ['sklad', process.env.SQS_SKLAD_PASS]]) {
  test.describe(login, () => {
    let context, page;
    const errors = [];   // ошибки JavaScript на страницах

    test.beforeAll(async ({ browser }) => {
      context = await browser.newContext();
      await blockExternal(context);
      page = await context.newPage();
      page.on('dialog', d => d.accept());
      page.on('pageerror', e => errors.push(page.url() + ': ' + e.message));
      await page.goto('/');
      await page.locator('#userlog').fill(login);
      await page.locator('input[type=password]').first().fill(pass);
      await page.locator('button[type=submit], input[type=submit]').first().click();
      await page.locator('#logout').first().waitFor({ state: 'attached', timeout: 15000 });
    });
    test.afterAll(async () => {
      await context?.close();
    });
    test.beforeEach(() => {
      errors.length = 0;
    });
    test.afterEach(() => {
      expect.soft(errors, 'ошибки JavaScript').toEqual([]);
    });

    test('главная: напоминания о сроках, «Ниже минимального остатка», заголовок вкладки', async () => {
      await page.goto('/');
      await page.screenshot({ path: path.join(SHOTS, `${login}-main.png`), fullPage: true });
      expect.soft(await text(page)).not.toMatch(BAD);
      expect.soft(await page.locator('#sqs-due a.sqs-extend').count(), 'напоминания о сроках оборудования с кнопкой «Продлить»').toBeGreaterThan(0);
      await expect.soft(page.locator('#sqs-belowmin'), 'блок «Ниже минимального остатка»').toBeVisible();
      expect.soft(await page.title(), 'заголовок вкладки').not.toMatch(/Zippy/);
    });

    test('меню: каждый пункт открывается без ошибки', async () => {
      await page.goto('/');
      // меню выдвинуто и все разделы раскрыты — для снимка
      await page.evaluate(() => {
        document.querySelector('#sidebar').classList.remove('collapsed');
        document.querySelectorAll('#sidebar .collapse').forEach(e => e.classList.add('show'));
      });
      await page.waitForTimeout(500);   // выезд меню (transition .35s)
      await page.locator('#sidebar').screenshot({ path: path.join(SHOTS, `${login}-menu.png`) });
      const hrefs = [...new Set(await page.locator('#sidebar a[href*="index.php?p="]').evaluateAll(a => a.map(e => e.getAttribute('href'))))];
      expect(hrefs.length, 'пунктов меню').toBeGreaterThan(20);
      const bad = [];
      for (const href of hrefs) {
        const resp = await page.goto(href);
        await page.waitForLoadState('networkidle').catch(() => {});
        const t = await text(page);
        const why = resp.status() >= 400 ? 'HTTP ' + resp.status()
          : /Pages\/Error/.test(page.url()) || DENIED.test(t) ? 'нет доступа или ошибка: ' + t.slice(0, 120)
          : BAD.test(t) ? 'ошибка: ' + t.match(BAD)[0] : '';
        if (why) bad.push(`${href} — ${why}`);
      }
      console.log(`${login}: пунктов меню ${hrefs.length}, с ошибкой ${bad.length}`);
      expect(bad).toEqual([]);
    });

    test('выдача расходников: предупреждение об интервале, проведение', async () => {
      await page.goto('/index.php?p=App/Pages/Service/ConsumablesIssue');
      await pickAuto(page, '#cform #customer', 'Подопечный 003', 'Подопечный 003');
      await pickAuto(page, '#cform #item', 'Зонд гастростомический 14', 'Зонд гастростомический 14');
      await page.locator('#cform #qty').fill('1');
      await clickWait(page, '#cform #additem');
      const warn = (await page.locator('#cissue-rows [id^=rowwarn]:visible').allInnerTexts()).join(' ');
      expect.soft(warn, 'предупреждение об интервале').toMatch(/последняя выдача этому получателю .* выдают не чаще раза в \d+ дн\./);
      await pickAuto(page, '#cform #item', 'Подгузники детские размер 5', 'Подгузники детские размер 5');
      await page.locator('#cform #qty').fill('2');
      await clickWait(page, '#cform #additem');
      await expect(page.locator('#cissue-rows tr')).toHaveCount(2);
      await clickWait(page, '#cform #issue');
      expect((await messages(page)).join(' ')).toMatch(/Выдано: накладная \S+, получатель Подопечный 003, позиций: 2/);
    });

    test('выдача оборудования по договору и возврат из журнала выдач', async () => {
      await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
      await pickAuto(page, '#iform #customer', 'Подопечный 004', 'Подопечный 004');
      await page.locator('#iform #referrer').fill('Выездная служба');
      await pickAuto(page, '#iform #unit', 'Насос', 'Насос');
      await clickWait(page, '#iform #addunit');
      const unit = (await page.locator('#iform [id^=unitlabel]').first().innerText()).trim();
      const inv = unit.match(/\d{6,}/)?.[0];
      expect(inv, 'инвентарный номер в строке: ' + unit).toBeTruthy();
      await clickWait(page, '#iform #issue');
      const msg = (await messages(page)).join(' ');
      const number = msg.match(/накладная (\S+?),/)?.[1];
      expect(msg).toMatch(/Выдано: договор \S+, накладная \S+, получатель Подопечный 004/);

      await page.goto('/index.php?p=App/Pages/Register/GIList');
      await page.locator('#filter #searchnumber').fill(number);
      await clickWait(page, '#filter input[type=submit]');
      await clickWait(page, `a:text-is("${number}")`);
      await clickWait(page, '#statusform #bret');
      expect(page.url()).toContain('ReturnIssue');
      await page.locator('#notes').fill('Возврат оборудования: больше не нужно');
      await clickWait(page, '#execdoc');
      expect.soft(await text(page)).not.toMatch(BAD);
      expect.soft(page.url(), (await messages(page)).join(' ')).not.toContain('ReturnIssue');

      await runReport(page, 'Report/EquipmentWhere', { texts: { searchtext: inv } });
      await expect(page.locator('#preview'), 'единица снова на складе').toContainText('Основной склад');
    });

    test('продление срока с главной', async () => {
      await page.goto('/');
      const extend = page.locator('#sqs-due a.sqs-extend');
      expect(await extend.count(), 'на главной есть «Продлить»').toBeGreaterThan(0);
      await clickWait(page, '#sqs-due a.sqs-extend');
      await page.locator('#eform #ondemand').setChecked(false);
      await page.locator('#eform #enddate').fill(day(60));
      await clickWait(page, '#eform #save');
      expect((await messages(page)).join(' ')).toContain(day(60).split('-').reverse().join('.'));
    });

    test('акт списания за месяц в Excel', async () => {
      await runReport(page, 'Report/IssueAct', { selects: { year: '2026', month: '9' } });
      await expect(page.locator('#preview')).toContainText('Списание расходников');
      const xls = await xlsxText(page.context().request, '/index.php?p=App/Pages/ShowReport&arg=xls/issueact');
      expect(xls).toContain('Списание расходников');
    });

    test('PDF «Состояния складов»', async () => {
      await page.goto('/index.php?p=App/Pages/Report/StoreItems');
      await clickWait(page, '#filter #onreport');
      const resp = await page.context().request.get('/index.php?p=App/Pages/ShowReport&arg=pdf/storeitems');
      expect(resp.status()).toBe(200);
      const body = await resp.body();
      expect(body.subarray(0, 4).toString(), 'PDF, а не текст ошибки: ' + body.subarray(0, 200).toString()).toBe('%PDF');
    });

    test('резервная копия' + (login == 'admin' ? ': «Создать копию», «Сохранить и сделать копию сейчас», восстановление этой копии' : ': «Создать копию»'), async ({}, testInfo) => {
      await page.goto('/index.php?p=App/Pages/Service/Backup');
      const [download] = await Promise.all([page.waitForEvent('download', { timeout: 120_000 }), page.locator('#bform #create').click()]);
      const zip = testInfo.outputPath('backup.zip');
      await download.saveAs(zip);
      expect(fs.readFileSync(zip).subarray(0, 2).toString(), 'zip, а не текст ошибки').toBe('PK');
      if (login != 'admin') {
        await expect(page.locator('#rform, #sform'), 'восстановления и настроек автокопии нет').toHaveCount(0);
        return;
      }
      const files = () => execFileSync('docker', ['exec', WEB, 'ls', BK], { encoding: 'utf8' }).split('\n').filter(f => /^squirrelstock-.*\.zip$/.test(f));
      const was = files().length;
      await page.goto('/index.php?p=App/Pages/Service/Backup');
      await clickWait(page, '#sform #runnow');
      expect.soft((await messages(page)).join(' ')).not.toMatch(/ошибк|Path|open_basedir/i);
      expect(files().length, 'копия в папке ' + BK).toBe(was + 1);

      await page.goto('/index.php?p=App/Pages/Service/Backup');
      await page.locator('#rform #rfile').setInputFiles(zip);
      await page.locator('#rform #confirm').setChecked(true);
      await clickWait(page, '#rform #restore');
      await expect(page.locator('.toast-message').first(), (await messages(page)).join(' ')).toContainText(/восстановлено/);
      await page.goto('/');
      expect(await text(page), 'главная после восстановления').not.toMatch(BAD);
    });

    if (login != 'admin') {
      test('закрытые страницы по прямому адресу отказывают', async () => {
        const open = [];
        for (const p of CLOSED) {
          await page.goto('/index.php?p=App/Pages/' + p);
          // отказ — сообщением или переходом на страницу ошибки (там сообщение показано не всегда)
          if (!DENIED.test(await text(page)) && !/Pages\/Error/.test(page.url())) open.push(p);
        }
        expect(open).toEqual([]);
      });
    }
  });
}
