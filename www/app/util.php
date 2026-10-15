<?php

namespace App;

use App\Helper as H;
use Symfony\Polyfill\Mbstring\Mbstring;
use Symfony\Polyfill\Uuid\Uuid;

/**
 * Класс   со  вспомагательными   функциями
 */
class Util
{
    /**
    * генерация  комманд для  чекового  принтера
    *
    * @param mixed $template
    * @return mixed
    */
    public static function generateESCPOS($template) {


        return  [];
    }

    /**
    * генерация QR кода  *
    * @param mixed $data
    * @param mixed $size
    * @param mixed $margin
    */
    public static function generateQR($data, $size, $margin=5) {

        $writer = new \Endroid\QrCode\Writer\PngWriter();


        $qrCode = new \Endroid\QrCode\QrCode($data);
        $qrCode->setSize($size);
        $qrCode->setMargin($margin);
        // $qrCode->setWriterByName('png');

        $result = $writer->write($qrCode);

        $dataUri = $result->getDataUri();

        return $dataUri;
    }

    /**
     * генерация  GUID
     *
     */
    public static function guid() {

        $uuid = Uuid::uuid_create(Uuid::UUID_TYPE_RANDOM);

        return $uuid;
    }

    /**
     * возвращает первые  буквы
     */
    public static function getLabelName($name) {
        $name = preg_replace('|\s+|', ' ', $name);
        $name = Mbstring::mb_strtoupper($name, 'UTF-8');

        $w = explode(' ', $name);
        $lb = Mbstring::mb_substr($w[0], 0, 1, 'UTF-8');
        if (count($w) > 1) {
            $lb .= Mbstring::mb_substr($w[1], 0, 1, 'UTF-8');
        }

        return $lb;
    }

    /**
     * Вставляет пробелы  между символами строки
     *
     * @param mixed $string
     */
    public static function addSpaces($string) {
        $_data = "";
        $strlen = mb_strlen($string);
        while($strlen) {
            $_data .= (" " . mb_substr($string, 0, 1, 'UTF-8'));
            $string = mb_substr($string, 1, $strlen, 'UTF-8');
            $strlen = mb_strlen($string, 'UTF-8');
        }

        return trim($_data);
    }

    /**
     * вовращает  список  лет
     */
    public static function getYears() {
        $list = array();
        for ($i = 2020; $i <= 2030; $i++) {
            $list[$i] = $i;
        }
        return $list;
    }

    /**
     * вовращает  список  месяцев
     */
    public static function getMonth() {
        $list = array();
        $list[1] = __('app.util.sichen');
        $list[2] = __('app.util.liutyi');
        $list[3] = __('app.util.berezen');
        $list[4] = __('app.util.kviten');
        $list[5] = __('app.util.traven');
        $list[6] = __('app.util.cherven');
        $list[7] = __('app.util.lypen');
        $list[8] = __('app.util.serpen');
        $list[9] = __('app.util.veresen');
        $list[10] = __('app.util.zhovten');
        $list[11] = __('app.util.lystopad');
        $list[12] = __('app.util.hruden');
        return $list;
    }



  

    public static function money2str($number) {
        // SquirrelStock: валюта суммы прописью из настроек (ua — гривна, ru — рубль, us — доллар, eu — евро)
        $f = 'money2str_' . (\App\System::getOptions('common')['money2str'] ?? 'ua');
        if (!in_array($f, ['money2str_ua', 'money2str_ru', 'money2str_us', 'money2str_eu'], true)) {
            $f = 'money2str_ua';
        }

        $f = __NAMESPACE__ . '\\' . $f;   // функции money2str_* объявлены в этом пространстве имен

        return mb_ucfirst( $f($number, M2S_KOPS_MANDATORY + M2S_KOPS_DIGITS + M2S_KOPS_SHORT) );
    }

    /**
     *     Преобразование  первого  символа   в   верхний  регистр
     *
     * @param mixed $str
     */
    public static function ucfirst($str) {

        return mb_ucfirst($str);
    }

    //многобайтовая версия
    public static function mb_split($str, $len = 1) {

        $arr = [];
        $length = mb_strlen($str, 'UTF-8');

        for ($i = 0; $i < $length; $i += $len) {

            $arr[] = mb_substr($str, $i, $len, 'UTF-8');
        }

        return $arr;
    }

    //очистка  номера  телефона
    public static function handlePhone($tel) {
        $tel = str_replace(' ', '', $tel);
        $tel = preg_replace("/[^0-9.]/", "", $tel);

        $phonel = System::getOption("common", 'phonel');
        if($phonel==12 && strlen($tel)==10) {
            $tel = '38'.$tel ;
        }
        if($phonel==10 && strlen($tel)==12) {
            $tel = substr($tel,2) ;
        }

        return $tel;
    }

    //генерация случайного цвета
    public static function genColor() {
        $color = dechex(rand(0x000000, 0xFFFFFF));
        return $color;
    }

    // возвращает  прошлые месяцы  с  названиями  и датами начала  и конца
    public static function genPastMonths($num) {
        $mlist = Util::getMonth();

        $list = array();

        $dt = new \App\DateTime();
        $dt->subMonth(1);
        for ($i = 1; $i <= $num; $i++) {
            //   $mon[] = $mlist[$dt->month];
            $to = $dt->endOfMonth()->getTimestamp();
            $from = $dt->startOfMonth()->getTimestamp();
            $list[] = array('number' => $dt->monthNumber(), 'name' => $mlist[$dt->monthNumber()], 'start' => $from, 'end' => $to);
            $dt = $dt->subMonth(1);
        }
        $list = array_reverse($list);

        return $list;
    }

    //массив  в  обьекты для  фронта
    public static function tokv(array $a) {
        $r = array();
        foreach($a as $k=>$v) {
            $r[]=array('key'=>$k,'value'=>$v) ;
        }
        return  $r;
    }
    //парсит  строку на  слова
    public static function strtoarray($text) {
        $r = array();

        foreach(explode(' ', trim($text)) as $l) {
            $l = trim($l) ;
            if(strlen($l) > 0) {
                $r[] = $l ;
            }

        }
        return  $r;
    }
    
    //разбивка  на строки  по пробелам
    public static function splitstr($text,$length) {
        $lines =[];
        
        $words = self::strtoarray($text) ;
        
        $line="";
        foreach($words as $w ) {
            
            if(mb_strlen($line)  + mb_strlen($w)  <=$length ) {
                $line =  $line . $w .' ';
            } else {
                 $lines[] = trim($line) ; 
                  $line="";      
            }
            
        }
        if(strlen($line)>0) {
           $lines[] = trim($line) ; 
        }
        
        return $lines;
    }
    
    /**
    * разбмвка текста на  строки  по переносу (например  из  textarea)
    * 
    * @param mixed $text
    */
    public static function splitText($text) {
       $ret=[];
       $list= explode(PHP_EOL, $text  ) ;
       foreach($list as $l) {
           $l = trim($l,"\r");
           $l = trim($l,"\n");
           $l = trim($l);
           $l = trim($l);
           if(strlen($l) >0 ) {
             $ret[]= $l; 
           }
       }
       return $ret;
    }
 
    
}



define('M2S_KOPS_DIGITS', 0x01);    // digital copecks
define('M2S_KOPS_MANDATORY', 0x02);    // mandatory copecks
define('M2S_KOPS_SHORT', 0x04);    // shorten copecks

 
function money2str_ru($money, $options = 0) {

    $money = preg_replace('/[\,\-\=]/', '.', $money);

    $numbers_m = array('', __('app.util.odyn'), __('app.util.dva'), __('app.util.try'), __('app.util.chetyre'), __('app.util.piat'), __('app.util.shest'), __('app.util.sem'),
        __('app.util.vosem'), __('app.util.deviat'), __('app.util.desiat'), __('app.util.odynnadtsat'), __('app.util.dvenadtsat'), __('app.util.trynadtsat'),
        __('app.util.chetyrnadtsat'), __('app.util.piatnadtsat'), __('app.util.shestnadtsat'), __('app.util.semnadtsat'), __('app.util.vosemnadtsat'),
        __('app.util.deviatnadtsat'), __('app.util.dvadtsat'), 30  => __('app.util.trydtsat'), 40 => __('app.util.sorok'), 50 => __('app.util.piatdesiat'),
                                    60  => __('app.util.shestdesiat'), 70 => __('app.util.semdesiat'), 80 => __('app.util.vosemdesiat'), 90 => __('app.util.devianosto'),
                                    100 => __('app.util.sto'), 200 => __('app.util.dvesty'), 300 => __('app.util.trysta'), 400 => __('app.util.chetyresta'),
                                    500 => __('app.util.piatsot'), 600 => __('app.util.shestsot'), 700 => __('app.util.semsot'), 800 => __('app.util.vosemsot'),
                                    900 => __('app.util.deviatsot'));

    $numbers_f = array('', __('app.util.odna'), __('app.util.dve'));

    $units_ru = array(
        (($options & M2S_KOPS_SHORT) ? array(__('app.util.kop'), __('app.util.kop'), __('app.util.kop')) : array(__('app.util.kopeika'), __('app.util.kopeiky'), __('app.util.kopeek'))),
        array(__('app.util.rubl'), __('app.util.rublia'), __('app.util.rublei')),
        array(__('app.util.tysiacha'), __('app.util.tysiachy'), __('app.util.tysiach')),
        array(__('app.util.myllyon'), __('app.util.myllyona'), __('app.util.myllyonov'))
    );

    $dig = '';
    $ret = '';

    // enumerating digit groups from left to right, from trillions to copecks
    // $i == 0 means we deal with copecks, $i == 1 for roubles,
    // $i == 2 for thousands etc.
    for ($i = sizeof($units_ru) - 1; $i >= 0; $i--) {

        // each group contais 3 digits, except copecks, containing of 2 digits
        $grp = ($i != 0) ? dec_digits_group($money, $i - 1, 3) :
            dec_digits_group($money, -1, 2);

        // process the group if not empty
        if ($grp != 0) {

            // digital copecks
            if ($i == 0 && ($options & M2S_KOPS_DIGITS)) {
                $ret .= sprintf('%02d', $grp) . ' ';
                $dig = $grp;

                // the main case
            } else {
                for ($j = 2; $j >= 0; $j--) {
                    $dig = dec_digits_group($grp, $j);
                    if ($dig != 0) {

                        // 10 to 19 is a special case
                        if ($j == 1 && $dig == 1) {
                            $dig = dec_digits_group($grp, 0, 2);
                            $ret .= $numbers_m[$dig] . ' ';
                            break;
                        } // thousands and copecks are Feminine gender in Russian
                        elseif (($i == 2 || $i == 0) && $j == 0 && ($dig == 1 || $dig == 2)) {
                            $ret .= $numbers_f[$dig] . ' ';
                        } // the main case
                        else {
                            $ret .= $numbers_m[(int)($dig * pow(10, $j))] . ' ';
                        }
                    }
                }
            }
            $ret .= $units_ru[$i][sk_plural_form($dig)] . ' ';
        } // roubles should be named in case of empty roubles group too
        elseif ($i == 1 && $ret != '') {
            $ret .= $units_ru[1][2] . ' ';
        } // mandatory copecks
        elseif ($i == 0 && ($options & M2S_KOPS_MANDATORY)) {
            $ret .= (($options & M2S_KOPS_DIGITS) ? '00' : __('app.util.nol')) .
                ' ' . $units_ru[0][2];
        }
    }

    return trim($ret);
}

function money2str_us($money, $options = 0) {

    $money = preg_replace('/[\,\-\=]/', '.', $money);

    $numbers_m = array('', __('app.util.odyn'), __('app.util.dva'), __('app.util.try'), __('app.util.chetyre'), __('app.util.piat'), __('app.util.shest'), __('app.util.sem'),
        __('app.util.vosem'), __('app.util.deviat'), __('app.util.desiat'), __('app.util.odynnadtsat'), __('app.util.dvenadtsat'), __('app.util.trynadtsat'),
        __('app.util.chetyrnadtsat'), __('app.util.piatnadtsat'), __('app.util.shestnadtsat'), __('app.util.semnadtsat'), __('app.util.vosemnadtsat'),
        __('app.util.deviatnadtsat'), __('app.util.dvadtsat'), 30  => __('app.util.trydtsat'), 40 => __('app.util.sorok'), 50 => __('app.util.piatdesiat'),
                                    60  => __('app.util.shestdesiat'), 70 => __('app.util.semdesiat'), 80 => __('app.util.vosemdesiat'), 90 => __('app.util.devianosto'),
                                    100 => __('app.util.sto'), 200 => __('app.util.dvesty'), 300 => __('app.util.trysta'), 400 => __('app.util.chetyresta'),
                                    500 => __('app.util.piatsot'), 600 => __('app.util.shestsot'), 700 => __('app.util.semsot'), 800 => __('app.util.vosemsot'),
                                    900 => __('app.util.deviatsot'));

    $numbers_f = array('', __('app.util.odyn'), __('app.util.dva'));

    $units_ru = array(
        (($options & M2S_KOPS_SHORT) ? array('c.', 'c.', 'c.') : array(__('app.util.tsent'), __('app.util.tsenta'), __('app.util.tsentov'))),
        array(__('app.util.dollar'), __('app.util.dollara'), __('app.util.dollarov')),
        array(__('app.util.tysiacha'), __('app.util.tysiachy'), __('app.util.tysiach')),
        array(__('app.util.myllyon'), __('app.util.myllyona'), __('app.util.myllyonov'))
    );

    $ret = '';

    // enumerating digit groups from left to right, from trillions to copecks
    // $i == 0 means we deal with copecks, $i == 1 for roubles,
    // $i == 2 for thousands etc.
    for ($i = sizeof($units_ru) - 1; $i >= 0; $i--) {
        $dig='';
        // each group contais 3 digits, except copecks, containing of 2 digits
        $grp = ($i != 0) ? dec_digits_group($money, $i - 1, 3) :
            dec_digits_group($money, -1, 2);

        // process the group if not empty
        if ($grp != 0) {

            // digital copecks
            if ($i == 0 && ($options & M2S_KOPS_DIGITS)) {
                $ret .= sprintf('%02d', $grp) . ' ';
                $dig = $grp;

                // the main case
            } else {
                for ($j = 2; $j >= 0; $j--) {
                    $dig = dec_digits_group($grp, $j);
                    if ($dig != 0) {

                        // 10 to 19 is a special case
                        if ($j == 1 && $dig == 1) {
                            $dig = dec_digits_group($grp, 0, 2);
                            $ret .= $numbers_m[$dig] . ' ';
                            break;
                        } // thousands and copecks are Feminine gender in Russian
                        elseif (($i == 2 || $i == 0) && $j == 0 && ($dig == 1 || $dig == 2)) {
                            $ret .= $numbers_f[$dig] . ' ';
                        } // the main case
                        else {
                            $ret .= $numbers_m[(int)($dig * pow(10, $j))] . ' ';
                        }
                    }
                }
            }
            $ret .= $units_ru[$i][sk_plural_form($dig)] . ' ';
        } // roubles should be named in case of empty roubles group too
        elseif ($i == 1 && $ret != '') {
            $ret .= $units_ru[1][2] . ' ';
        } // mandatory copecks
        elseif ($i == 0 && ($options & M2S_KOPS_MANDATORY)) {
            $ret .= (($options & M2S_KOPS_DIGITS) ? '00' : __('app.util.nol')) .
                ' ' . $units_ru[0][2];
        }
    }

    return trim($ret);
}

function money2str_eu($money, $options = 0) {
    $dig='';
    $money = preg_replace('/[\,\-\=]/', '.', $money);

    $numbers_m = array('', __('app.util.odyn'), __('app.util.dva'), __('app.util.try'), __('app.util.chetyre'), __('app.util.piat'), __('app.util.shest'), __('app.util.sem'),
        __('app.util.vosem'), __('app.util.deviat'), __('app.util.desiat'), __('app.util.odynnadtsat'), __('app.util.dvenadtsat'), __('app.util.trynadtsat'),
        __('app.util.chetyrnadtsat'), __('app.util.piatnadtsat'), __('app.util.shestnadtsat'), __('app.util.semnadtsat'), __('app.util.vosemnadtsat'),
        __('app.util.deviatnadtsat'), __('app.util.dvadtsat'), 30  => __('app.util.trydtsat'), 40 => __('app.util.sorok'), 50 => __('app.util.piatdesiat'),
                                    60  => __('app.util.shestdesiat'), 70 => __('app.util.semdesiat'), 80 => __('app.util.vosemdesiat'), 90 => __('app.util.devianosto'),
                                    100 => __('app.util.sto'), 200 => __('app.util.dvesty'), 300 => __('app.util.trysta'), 400 => __('app.util.chetyresta'),
                                    500 => __('app.util.piatsot'), 600 => __('app.util.shestsot'), 700 => __('app.util.semsot'), 800 => __('app.util.vosemsot'),
                                    900 => __('app.util.deviatsot'));

    $numbers_f = array('', __('app.util.odyn'), __('app.util.dva'));

    $units_ru = array(
        (($options & M2S_KOPS_SHORT) ? array('c.', 'c.', 'c.') : array(__('app.util.tsent'), __('app.util.tsenta'), __('app.util.tsentov'))),
        array(__('app.util.evro'), __('app.util.evro'), __('app.util.evro')),
        array(__('app.util.tysiacha'), __('app.util.tysiachy'), __('app.util.tysiach')),
        array(__('app.util.myllyon'), __('app.util.myllyona'), __('app.util.myllyonov'))
    );

    $ret = '';

    // enumerating digit groups from left to right, from trillions to copecks
    // $i == 0 means we deal with copecks, $i == 1 for roubles,
    // $i == 2 for thousands etc.
    for ($i = sizeof($units_ru) - 1; $i >= 0; $i--) {

        // each group contais 3 digits, except copecks, containing of 2 digits
        $grp = ($i != 0) ? dec_digits_group($money, $i - 1, 3) :
            dec_digits_group($money, -1, 2);

        // process the group if not empty
        if ($grp != 0) {

            // digital copecks
            if ($i == 0 && ($options & M2S_KOPS_DIGITS)) {
                $ret .= sprintf('%02d', $grp) . ' ';
                $dig = $grp;

                // the main case
            } else {
                for ($j = 2; $j >= 0; $j--) {
                    $dig = dec_digits_group($grp, $j);
                    if ($dig != 0) {

                        // 10 to 19 is a special case
                        if ($j == 1 && $dig == 1) {
                            $dig = dec_digits_group($grp, 0, 2);
                            $ret .= $numbers_m[$dig] . ' ';
                            break;
                        } // thousands and copecks are Feminine gender in Russian
                        elseif (($i == 2 || $i == 0) && $j == 0 && ($dig == 1 || $dig == 2)) {
                            $ret .= $numbers_f[$dig] . ' ';
                        } // the main case
                        else {
                            $ret .= $numbers_m[(int)($dig * pow(10, $j))] . ' ';
                        }
                    }
                }
            }
            $ret .= $units_ru[$i][sk_plural_form($dig)] . ' ';
        } // roubles should be named in case of empty roubles group too
        elseif ($i == 1 && $ret != '') {
            $ret .= $units_ru[1][2] . ' ';
        } // mandatory copecks
        elseif ($i == 0 && ($options & M2S_KOPS_MANDATORY)) {
            $ret .= (($options & M2S_KOPS_DIGITS) ? '00' : __('app.util.nol')) .
                ' ' . $units_ru[0][2];
        }
    }

    return trim($ret);
}

function money2str_ua($money, $options = 0) {
    $dig ='';
    $money = preg_replace('/[\,\-\=]/', '.', $money);

    $numbers_m = array('', __('app.util.odna'), __('app.util.dvi'), __('app.util.try'), __('app.util.chotyry'), __('app.util.piat_2'), __('app.util.shist'), __('app.util.sim'),
        __('app.util.visim'), __('app.util.deviat_2'), __('app.util.desiat'), __('app.util.odynadtsiat'), __('app.util.dvanadtsiat'), __('app.util.trynadtsiat'),
        __('app.util.chotyrnadtsiat'), __('app.util.piatnadtsiat'), __('app.util.shistnadtsiat'), __('app.util.simnadtsiat'), __('app.util.visimnadtsiat'),
        __('app.util.deviatnadtsiat'), __('app.util.dvadtsiat'), 30  => __('app.util.trydtsiat'), 40 => __('app.util.sorok'), 50 => __('app.util.piatdesiat_2'),
                                     60  => __('app.util.shistdesiat'), 70 => __('app.util.simdesiat'), 80 => __('app.util.visimdesiat'), 90 => __('app.util.devianosto_2'),
                                     100 => __('app.util.sto'), 200 => __('app.util.dvisti'), 300 => __('app.util.trysta'), 400 => __('app.util.chotyrysta'),
                                     500 => __('app.util.piatsot_2'), 600 => __('app.util.shistsot'), 700 => __('app.util.simsot'), 800 => __('app.util.visimsot'),
                                     900 => __('app.util.deviatsot_2'));

    $numbers_f = array('', __('app.util.odna'), __('app.util.dvi'));

    $units_ru = array(
        (($options & M2S_KOPS_SHORT) ? array(__('app.util.kop'), __('app.util.kop'), __('app.util.kop')) : array(__('app.util.kopiika'), __('app.util.kopiiky'), __('app.util.kopiiok'))),
        array(__('app.util.hryvnia'), __('app.util.hryvni'), __('app.util.hryven')),
        array(__('app.util.tysiacha_2'), __('app.util.tysiachi'), __('app.util.tysiach_2')),
        array(__('app.util.milion'), __('app.util.miliona'), __('app.util.milioniv')),
        array(__('app.util.miliard'), __('app.util.miliardy'), __('app.util.miliardiv'))
    );

    $ret = '';

    // enumerating digit groups from left to right, from trillions to copecks
    // $i == 0 means we deal with copecks, $i == 1 for roubles,
    // $i == 2 for thousands etc.
    for ($i = sizeof($units_ru) - 1; $i >= 0; $i--) {

        // each group contais 3 digits, except copecks, containing of 2 digits
        $grp = ($i != 0) ? dec_digits_group($money, $i - 1, 3) :
            dec_digits_group($money, -1, 2);

        // process the group if not empty
        if ($grp != 0) {

            // digital copecks
            if ($i == 0 && ($options & M2S_KOPS_DIGITS)) {
                $ret .= sprintf('%02d', $grp) . ' ';
                $dig = $grp;

                // the main case
            } else {
                for ($j = 2; $j >= 0; $j--) {
                    $dig = dec_digits_group($grp, $j);
                    if ($dig != 0) {

                        // 10 to 19 is a special case
                        if ($j == 1 && $dig == 1) {
                            $dig = dec_digits_group($grp, 0, 2);
                            $ret .= $numbers_m[$dig] . ' ';
                            break;
                        } // thousands and copecks are Feminine gender in Russian
                        elseif (($i == 2 || $i == 0) && $j == 0 && ($dig == 1 || $dig == 2)) {
                            $ret .= $numbers_f[$dig] . ' ';
                        } // the main case
                        else {
                            $ret .= $numbers_m[(int)($dig * pow(10, $j))] . ' ';
                        }
                    }
                }
            }
            $ret .= $units_ru[$i][sk_plural_form($dig)] . ' ';
        } // roubles should be named in case of empty roubles group too
        elseif ($i == 1 && $ret != '') {
            $ret .= $units_ru[1][2] . ' ';
        } // mandatory copecks
        elseif ($i == 0 && ($options & M2S_KOPS_MANDATORY)) {
            $ret .= (($options & M2S_KOPS_DIGITS) ? '00' : __('app.util.nol')) .
                ' ' . $units_ru[0][2];
        }
    }

    return trim($ret);
}

// service function to select the group of digits
function dec_digits_group($number, $power, $digits = 1) {

    if (function_exists('gmp_init') && $power >0) {
        return   gmp_intval(gmp_mod(gmp_div(intval($number), gmp_pow(10, intval($power) * intval($digits))), gmp_pow(10, (int)$digits)));
    }
 
    
    return  intval(($number/pow(10, intval($power) * intval($digits))) % pow(10, $digits)) ;
  
   //  return (int)bcmod(bcdiv($number, bcpow(10, $power * $digits, 8)), bcpow(10, $digits, 8));
}

// service function to get plural form for the number
function sk_plural_form($d) {
    $d = $d % 100;
    if ($d > 20) {
        $d = $d % 10;
    }
    if ($d == 1) {
        return 0;
    } elseif ($d > 0 && $d < 5) {
        return 1;
    } else {
        return 2;
    }
}
