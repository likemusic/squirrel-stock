// Напоминание на главной: выданное оборудование с истекшим или истекающим в ближайшие 14 дней сроком (ответ хосписа 1.4).
// «До востребования» в напоминание не попадает. ZIPPY_GAPS п. 10.
const { test, expect } = require('../fixtures');
const { createItem, createDoc, pickAuto, clickWait } = require('../helpers/zippy');

// дата по местному времени (стенд — Europe/Minsk): после полуночи по UTC еще вчерашний день
const day = n => new Date(Date.now() + n * 86400000 - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

test('напоминание о сроке пользования на главной', async ({ page, uniq }) => {
  const code = 'ED-' + uniq, name = 'Кровать ' + uniq;
  const inv = n => `INV${n}-${uniq}`;
  await createItem(page, { name, code, serial: true });
  await createDoc(page, 'IncomeItem', { date: day(-60), rows: [1, 2, 3].map(n => ({ code, qty: 1, price: 0, sn: inv(n) })) });

  const issue = async (n, end) => {
    await page.goto('/index.php?p=App/Pages/Service/EquipmentIssue');
    await page.locator('#iform #customer').fill(`Получатель ${n} ${uniq}`);
    await page.locator('#iform #date').fill(day(-30));
    if (end) {
      await page.locator('#iform #ondemand').setChecked(false);
      await page.locator('#iform #enddate').fill(end);
    }
    await pickAuto(page, '#iform #unit', inv(n), inv(n));
    await clickWait(page, '#iform #issue');
  };
  await issue(1, day(-3));   // просрочено
  await issue(2, day(5));    // истекает
  await issue(3);            // до востребования

  await page.goto('/');
  const box = page.locator('#sqs-due');
  await expect(box).toBeVisible();
  await expect(box.locator('tr', { hasText: inv(1) }).locator('td').last()).toHaveClass(/text-danger/);
  await expect(box.locator('tr', { hasText: inv(1) })).toContainText('3');
  await expect(box.locator('tr', { hasText: inv(2) })).toHaveCount(1);
  await expect(box.locator('tr', { hasText: inv(3) })).toHaveCount(0);
});
