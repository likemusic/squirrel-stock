// Главная: администратор видит предупреждение, если в каком-то месте нет удачной резервной копии дольше 48 часов.
// Нужна веб-проверка: если планировщик не запущен вовсе, уведомления о сбое не будет. ZIPPY_GAPS п. 33.
const { test, expect } = require('../fixtures');
const { value } = require('../helpers/db');

test('предупреждение о давней резервной копии на главной', async ({ page }) => {
  const local = JSON.parse(require('fs').readFileSync(__dirname + '/../../www/lang/uk.json', 'utf8'))['service.backup.place_local'];
  // время журнала копий пишет PHP по Минску, а now() базы стенда — UTC
  const id = value(`insert into sqs_backuplog (place, createdon, ok, file, message) values ('local', now() + interval 3 hour, 1, 'test.zip', 'тест предупреждения'); select last_insert_id()`);
  try {
    await page.goto('/');
    const banner = page.locator('#sqs-backup-stale');
    if (await banner.count()) await expect(banner, 'свежая копия в папке компьютера').not.toContainText(local);

    value(`update sqs_backuplog set createdon = now() - interval 3 day where ok = 1 and place = 'local'`);
    await page.goto('/');
    await expect(banner).toContainText(local);
  } finally {
    value(`delete from sqs_backuplog where id = ${id}`);
  }
});
