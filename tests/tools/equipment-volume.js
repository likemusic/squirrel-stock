// Замер отчетов оборудования на объеме хосписа (~700 единиц, вымышленные данные tools/equipment-volume.php).
// Запускать на ЧИСТОЙ базе отдельного стенда (данные не удаляются):
//   cd tests && SQS_BASE=http://127.0.0.1:8088 SQS_WEB_CONTAINER=... node tools/equipment-volume.js load
//   node tools/equipment-volume.js measure <папка>   — время главной, «Где сейчас» и журнала выдачи; тексты отчетов — в папку
// Тексты до и после правки сравниваются diff: отчет не должен меняться.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('@playwright/test');
const { blockExternal, login, clickWait, messages } = require('../helpers/zippy');

const BASE = process.env.SQS_BASE || 'http://127.0.0.1:8083';
const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const [cmd, out] = process.argv.slice(2);

async function load(page) {
  await require('../global-setup')({ projects: [{ use: { baseURL: BASE } }] });   // настройки хосписа
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'eqvol-'));
  execFileSync('docker', ['exec', WEB, 'mkdir', '-p', '/tmp/eqvol']);
  execFileSync('docker', ['cp', path.join(__dirname, 'equipment-volume.php'), `${WEB}:/tmp/eqvol/make.php`]);
  console.log(execFileSync('docker', ['exec', WEB, 'php', '/tmp/eqvol/make.php', '/tmp/eqvol'], { encoding: 'utf8' }).trim());
  for (const f of ['osv-01.xls', 'census.xlsx']) execFileSync('docker', ['cp', `${WEB}:/tmp/eqvol/${f}`, path.join(dir, f)]);
  execFileSync('docker', ['exec', WEB, 'rm', '-rf', '/tmp/eqvol']);

  await page.goto('/index.php?p=App/Pages/Service/Import1C');
  await page.locator('#iform #date').fill('2019-01-01');
  await page.locator('#iform #filename').setInputFiles(path.join(dir, 'osv-01.xls'));
  await page.locator('#iform #liftperiod').setChecked(true);   // история с 2019 года — раньше закрытого периода
  await clickWait(page, '#iform #import');
  console.log('ведомость:', (await messages(page)).join(' | '));

  await page.goto('/index.php?p=App/Pages/Service/ImportEquipmentHistory');
  await page.locator('#iform #census').setInputFiles(path.join(dir, 'census.xlsx'));
  let t = Date.now();
  await clickWait(page, '#iform #check');
  console.log('проверка, с:', ((Date.now() - t) / 1000).toFixed(1), (await messages(page)).join(' | '));
  if (await page.locator('#iform #liftperiod').count()) await page.locator('#iform #liftperiod').setChecked(true);
  t = Date.now();
  await clickWait(page, '#iform #write');
  await page.locator('#eqh-summary tr[data-stat=failed]').waitFor({ timeout: 30 * 60_000 });
  console.log('запись, с:', ((Date.now() - t) / 1000).toFixed(1));
  console.log((await page.locator('#eqh-summary').innerText()).replace(/\s*\n\s*/g, '; '));
  fs.rmSync(dir, { recursive: true });
}

// время ответа сервера на действие (переход или отправка формы), с
async function timed(page, act) {
  const t = Date.now();
  await act();
  return ((Date.now() - t) / 1000).toFixed(2);
}

async function measure(page) {
  fs.mkdirSync(out, { recursive: true });
  const save = async (name, sel) => fs.writeFileSync(path.join(out, name + '.txt'), (await page.locator(sel).innerText()).replace(/[ \t]+/g, ' '));
  const res = {};
  for (let i = 0; i < 2; i++) {   // первый проход прогревает кэш
    res['главная'] = await timed(page, () => page.goto('/index.php'));
    await save('main', 'body');
    for (const state of ['active', 'all']) {
      await page.goto('/index.php?p=App/Pages/Report/EquipmentWhere');
      await page.locator('#filter #state').selectOption(state);
      res['где сейчас ' + state] = await timed(page, () => clickWait(page, '#filter #onreport'));
      await save('where-' + state, '#preview');
    }
    await page.goto('/index.php?p=App/Pages/Report/EquipmentJournal');
    await page.locator('#filter #from').fill('2019-01-01');
    res['журнал выдачи'] = await timed(page, () => clickWait(page, '#filter #onreport'));
    await save('journal', '#preview');
  }
  console.log(res);
}

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL: BASE });
  await blockExternal(context);
  const page = await context.newPage();
  page.setDefaultTimeout(5 * 60_000);
  await login(page);
  if (cmd === 'load') await load(page);
  else if (cmd === 'measure' && out) await measure(page);
  else console.log('node tools/equipment-volume.js load | measure <папка>');
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
