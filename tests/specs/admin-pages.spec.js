// «Системный журнал» (уведомления системы, история статусов всех документов, просмотр и скачивание logs/*.txt) и
// «Планировщик» (включить, запустить, удалить задачу) в меню есть только у роли admins, а по прямому адресу открывались
// у любого вошедшего. Теперь пользователь другой роли получает «доступ только администратору».
const { test, expect } = require('../fixtures');
const { bodyText } = require('../helpers/zippy');
const { createUser, dropUser, loginAs } = require('../helpers/users');
const { query, esc } = require('../helpers/db');

const DENIED = /К странице имеет доступ только администратор|До сторінки має доступ тільки адміністратор/;
const PAGES = ['/index.php?p=App/Pages/SystemLog', '/index.php?p=App/Pages/CronTab'];

test('системный журнал и планировщик: другая роль — нет доступа, admins — открываются', async ({ page, browser, uniq }) => {
  const user = 'ap' + uniq.toLowerCase(), pass = 'Пароль-' + uniq, mark = `Уведомление ${uniq}`;
  const role = createUser(user, pass, 'ap-' + uniq);
  query(`insert into notifies (user_id, dateshow, checked, message, sender_id) values (-4, now(), 0, '${esc(mark)}', 0)`);
  let context;
  try {
    let other;
    ({ context, page: other } = await loginAs(browser, user, pass));
    for (const url of PAGES) {
      await other.goto(url);
      const t = await bodyText(other);
      expect(t, url).toMatch(DENIED);
      expect(t, url).not.toContain(mark);
      expect(t, url + ': файлов журнала не видно').not.toMatch(/log-\d{4}-\d{2}-\d{2}\.txt/);
    }

    for (const url of PAGES) {
      await page.goto(url);
      expect(await bodyText(page), url).not.toMatch(DENIED);
    }
    expect(await bodyText(page), 'admin видит системные уведомления').toContain(mark);
  } finally {
    await context?.close();
    query(`delete from notifies where message = '${esc(mark)}'`);
    dropUser(user, role);
  }
});
