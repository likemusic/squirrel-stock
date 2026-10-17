// Смена пароля в профиле: Zippy писал в системный журнал (notifies, user_id = -4) «сменил пароль на <b>новый пароль</b>» —
// открытым текстом, видно на странице «Системный журнал» и в резервных копиях. Теперь пишется только «сменил пароль»;
// прежние такие записи удаляет схема платформы (www/app/sqs/schema.sql), другие уведомления остаются.
const { test, expect } = require('../fixtures');
const { createUser, dropUser, loginAs } = require('../helpers/users');
const { webPhp } = require('../helpers/stand');
const { query, value, esc } = require('../helpers/db');

const sysCount = like => Number(value(`select count(*) from notifies where user_id = -4 and message like '${esc(like)}'`));

test('смена пароля: в системном журнале нет нового пароля', async ({ browser, uniq }) => {
  const user = 'pn' + uniq.toLowerCase(), pass = 'Старый-' + uniq, newPass = 'Новый-' + uniq;
  const role = createUser(user, pass, 'pn-' + uniq);
  let context;
  try {
    let page;
    ({ context, page } = await loginAs(browser, user, pass));
    await page.goto('/index.php?p=App/Pages/UserProfile');
    await page.locator('[zippy=userpassword], #userpassword').first().fill(newPass);
    await page.locator('[zippy=confirmpassword], #confirmpassword').first().fill(newPass);
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('#passwordform button[type=submit]').click()]);
    await expect.poll(() => sysCount(`%${user}%`), 'запись о смене пароля есть').toBe(1);
    expect(sysCount(`%${newPass}%`), 'нового пароля в журнале нет').toBe(0);
    expect(value(`select message from notifies where user_id = -4 and message like '%${esc(user)}%'`)).toMatch(/сменил пароль$|змінив пароль$/);
  } finally {
    await context?.close();
    query(`delete from notifies where message like '%${esc(user)}%'`);
    dropUser(user, role);
  }
});

test('прежние записи с паролем удаляются схемой платформы, другие уведомления остаются', async ({ uniq }) => {
  const old = [
    `Пользователь <b>u${uniq}</b> сменил пароль на <b>p${uniq}</b>`,
    `Користувач <b>u${uniq}</b> змінив пароль на <b>p${uniq}</b>`,
  ];
  const keep = [
    `Пользователь <b>u${uniq}</b> сменил пароль`,                      // новое сообщение
    `Пользователь <b>u${uniq}</b> удалил платеж из документа ${uniq}`,  // другое системное уведомление
  ];
  const personal = `Пользователь <b>u${uniq}</b> сменил пароль на <b>p${uniq}</b> — личное`;   // не системное: user_id admin
  const admin = Number(value(`select user_id from users where userlogin = 'admin'`));
  try {
    query([...old, ...keep].map(m => `insert into notifies (user_id, dateshow, checked, message, sender_id) values (-4, now(), 0, '${esc(m)}', 0)`).join('; ')
      + `; insert into notifies (user_id, dateshow, checked, message, sender_id) values (${admin}, now(), 0, '${esc(personal)}', -4)`);
    query(`delete from keyval where keyd = 'sqs_schema'`);
    webPhp('cli/modules.php');   // применяет схему платформы, как первый запрос после обновления или восстановления копии
    expect(sysCount(`%p${uniq}</b>`), 'записи с паролем удалены').toBe(0);
    for (const m of keep) expect(sysCount(m), m).toBe(1);
    expect(Number(value(`select count(*) from notifies where user_id = ${admin} and message = '${esc(personal)}'`)), 'уведомление пользователю не тронуто').toBe(1);
  } finally {
    query(`delete from notifies where message like '%u${uniq}%'`);
  }
});
