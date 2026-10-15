<?php
// config.php для docker-стенда (монтируется поверх www/config/config.php)

$_config = array('common'=>[],'db'=>[],'smtp'=>[]) ;
$_config['common']['loglevel'] = 200  ;//DEBUG = 100,INFO = 200,WARNING = 300,ERROR = 400;
$_config['common']['timezone'] = 'Europe/Minsk';
$_config['common']['lang'] = getenv('SQS_LANG') ?: 'uk';   // язык интерфейса по умолчанию: uk, ru (файлы www/lang/*.json)

$_config['db']['host'] = getenv('DB_HOST') ?: 'db';
$_config['db']['name'] = getenv('DB_NAME') ?: 'zstore';
$_config['db']['user'] = getenv('DB_USER') ?: 'zstore';
$_config['db']['pass'] = getenv('DB_PASS') ?: 'zstore';

$_config['smtp']['usesmtp'] = false ;
$_config['smtp']['host'] = '' ;
$_config['smtp']['port'] = 587 ;
$_config['smtp']['user'] = '' ;
$_config['smtp']['emailfrom'] = '' ;
$_config['smtp']['pass'] = '' ;
$_config['smtp']['tls'] = true ;
