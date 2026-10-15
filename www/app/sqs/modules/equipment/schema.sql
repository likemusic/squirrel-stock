-- Единицы оборудования (App\SQS\Units): заводской номер к инвентарному (серийному номеру Zippy, store_stock.snumber)
CREATE TABLE IF NOT EXISTS sqs_unit (
  item_id int NOT NULL,
  snumber varchar(255) NOT NULL,
  factory_no varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (item_id, snumber),
  KEY factory_no (factory_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Импорт истории выдачи оборудования из Google-таблиц (App\SQS\EqHistory); что уже импортировано — по ключу события
-- «вид|договор|единица|дата», чтобы повторный импорт не дублировал
CREATE TABLE IF NOT EXISTS sqs_eqhistory (
  eventkey varchar(255) NOT NULL,
  document_id int NOT NULL,
  createdon datetime NOT NULL,
  PRIMARY KEY (eventkey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
