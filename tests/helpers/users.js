// Пользователь с отдельной ролью (не admins) и вход им в новом контексте браузера.
// Роль без права на документы и журналы: страницы для всех вошедших (профиль) открываются, остальные — нет.
const { execFileSync } = require('child_process');
const { blockExternal } = require('./zippy');
const { query, value, esc } = require('./db');

const WEB = process.env.SQS_WEB_CONTAINER || 'squirrelstock-web-1';
const hashOf = pass => execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);'], { input: pass, encoding: 'utf8' });

function createUser(login, pass, rolename) {
  const acl = execFileSync('docker', ['exec', WEB, 'php', '-r', `echo serialize(['noshowpartion' => 0, 'showotherstores' => 1, 'aclview' => '', 'acledit' => '', 'aclexe' => '', 'aclcancel' => '', 'aclstate' => '', 'acldelete' => '', 'widgets' => '', 'modules' => '', 'smartmenu' => '']);`], { encoding: 'utf8' });
  query(`insert into roles (rolename, acl, disabled) values ('${esc(rolename)}', '${esc(acl)}', 0)`);
  const role = Number(value(`select max(role_id) from roles where rolename = '${esc(rolename)}'`));
  query(`insert into users (userlogin, userpass, createdon, email, acl, disabled, options, role_id)
         select '${esc(login)}', '${esc(hashOf(pass))}', now(), '', acl, 0, options, ${role} from users where userlogin = 'admin'`);
  return role;
}

function dropUser(login, role) {
  query(`delete from users where userlogin = '${esc(login)}'; delete from roles where role_id = ${Number(role)}`);
}

async function loginAs(browser, user, pass) {
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  await blockExternal(context);
  const page = await context.newPage();
  page.on('dialog', d => d.accept());
  await page.goto('/');
  await page.locator('#userlog').fill(user);
  await page.locator('input[type=password]').first().fill(pass);
  await page.locator('button[type=submit], input[type=submit]').first().click();
  await page.locator('#logout').first().waitFor({ state: 'attached', timeout: 15000 });
  return { context, page };
}

module.exports = { createUser, dropUser, loginAs };
