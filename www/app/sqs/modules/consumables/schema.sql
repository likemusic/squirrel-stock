-- Интервал выдачи (App\SQS\IssueInterval): «не чаще раза в N дней» для категории (kind = 'cat') или позиции ('item')
CREATE TABLE IF NOT EXISTS sqs_issue_interval (
  kind varchar(4) NOT NULL,
  ref_id int NOT NULL,
  days int NOT NULL,
  PRIMARY KEY (kind, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
