// Запросы к базе стенда для проверок, которые неудобно делать через интерфейс (остатки, число проводок).
// База — в контейнере docker-стенда; имя контейнера: SQS_DB_CONTAINER (по умолчанию squirrelstock-db-1).
const { execFileSync } = require('child_process');

const CONTAINER = process.env.SQS_DB_CONTAINER || 'squirrelstock-db-1';

// Возвращает строки результата как массивы значений (текст)
function query(sql) {
  const out = execFileSync('docker', ['exec', '-i', CONTAINER, 'mariadb', '-uzstore', '-pzstore', 'zstore', '-N', '-B', '-e', sql], { encoding: 'utf8' });
  return out.split('\n').filter(Boolean).map(l => l.split('\t'));
}

function value(sql) {
  const rows = query(sql);
  return rows.length ? rows[0][0] : null;
}

const esc = s => String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'");

// Остаток товара по коду на всех складах
function stockQty(code) {
  return Number(value(`select coalesce(sum(s.qty), 0) from store_stock s join items i on i.item_id = s.item_id where i.item_code = '${esc(code)}'`));
}

module.exports = { query, value, stockQty, esc };
