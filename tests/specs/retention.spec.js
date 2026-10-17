// Сроки хранения служебных данных: неудачные попытки входа (sqs_loginfail) старше суток и просроченные токены «Запомнить»
// (sqs_remember) удаляются при любом входе, а не только при следующей неудаче; в резервную копию обе таблицы не попадают.
const { test, expect } = require('../fixtures');
const { login } = require('../helpers/zippy');
const { webPhp } = require('../helpers/stand');
const { query, value } = require('../helpers/db');

test('удачный вход удаляет старые попытки входа и просроченные токены', async ({ browser, uniq }) => {
  const old = 'old' + uniq, fresh = 'fresh' + uniq, sel = (uniq.toLowerCase() + '0'.repeat(24)).replace(/[^0-9a-f]/g, 'a').slice(0, 24);
  query(`insert into sqs_loginfail (login, ip, createdon) values ('${old}', '10.0.0.1', now() - interval 2 day), ('${fresh}', '10.0.0.1', now() - interval 1 hour);
         insert into sqs_remember (selector, user_id, token_hash, pass_fp, expires) values ('${sel}', 0, repeat('0', 64), repeat('0', 64), now() - interval 1 day)`);
  try {
    const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    await login(await ctx.newPage());
    await ctx.close();
    expect(Number(value(`select count(*) from sqs_loginfail where login = '${old}'`)), 'попытка старше суток удалена').toBe(0);
    expect(Number(value(`select count(*) from sqs_loginfail where login = '${fresh}'`)), 'свежая попытка осталась (блокировка подбора)').toBe(1);
    expect(Number(value(`select count(*) from sqs_remember where selector = '${sel}'`)), 'просроченный токен удален').toBe(0);
  } finally {
    query(`delete from sqs_loginfail where login in ('${old}', '${fresh}'); delete from sqs_remember where selector = '${sel}'`);
  }
});

test('резервная копия без попыток входа и токенов «Запомнить»', async ({ uniq }) => {
  const mark = 'bk' + uniq;
  query(`insert into sqs_loginfail (login, ip, createdon) values ('${mark}', '10.0.0.2', now())`);
  try {
    const out = webPhp('-r', `
      require "init.php";
      $f = tempnam(sys_get_temp_dir(), "sqs");
      (new \\App\\SQS\\Backup())->dump($f);
      $s = file_get_contents($f); unlink($f);
      echo json_encode(["mark" => str_contains($s, "${mark}"), "fail" => (bool)preg_match("/INSERT INTO .sqs_loginfail./", $s),
        "remember" => (bool)preg_match("/INSERT INTO .sqs_remember./", $s), "create" => str_contains($s, "CREATE TABLE IF NOT EXISTS \`sqs_loginfail\`")]);
    `);
    const r = JSON.parse(out.replace(/^[^{]*/, ''));
    expect(r.mark, 'логина из попытки в копии нет').toBe(false);
    expect(r.fail).toBe(false);
    expect(r.remember).toBe(false);
    expect(r.create, 'таблица в копии есть (для новой базы), без строк').toBe(true);
  } finally {
    query(`delete from sqs_loginfail where login = '${mark}'`);
  }
});

// журнал правок (sqs_doclog: примечания, прежние значения полей) удаляется вместе с документом — хук afterDelete
test('удаление документа удаляет его журнал правок', async ({ uniq }) => {
  const meta = Number(value(`select meta_id from metadata where meta_type = 1 and meta_name = 'IncomeItem'`));
  const id = Number(value(`insert into documents (document_number, document_date, meta_id, state, notes) values ('RT-${uniq}', curdate(), ${meta}, 1, ''); select last_insert_id()`));
  query(`insert into sqs_doclog (document_id, user_id, createdon, changes) values (${id}, 0, now(), '[]'), (${id}, 0, now(), '[]')`);
  try {
    webPhp('-r', `require "init.php"; \\App\\Entity\\Doc\\Document::delete(${id});`);
    expect(Number(value(`select count(*) from documents where document_id = ${id}`)), 'документ удален').toBe(0);
    expect(Number(value(`select count(*) from sqs_doclog where document_id = ${id}`)), 'журнал правок удален').toBe(0);
  } finally {
    query(`delete from documents where document_id = ${id}; delete from sqs_doclog where document_id = ${id}`);
  }
});
