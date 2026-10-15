<?php

namespace App\Entity;

use App\Helper as H;

/**
 * Класс-сущность  запись  о  рабочем  времени
 *
 * @table=timesheet
 * @view=timesheet_view
 * @keyfield=time_id
 */
class TimeItem extends \ZCL\DB\Entity
{
    public const TIME_WORK = 1;   //Рабочий день
    public const TINE_OVER = 2;   //переработка
    public const TINE_WN   = 3;   //работа в  выходные  и ночь
    // const TINE_FREE   = 4;   //отгул
    public const TINE_HL    = 5;   //отпуск
    public const TINE_ILL   = 6;   //больничный
    public const TINE_BT    = 7;   //командировка
    public const TINE_OTHER = 10;   //другое

    protected function init() {
        $this->time_id = 0;
        $this->emp_id = 0;
        $this->t_type = 0;
    }

    protected function afterLoad() {
        $this->t_start = strtotime($this->t_start);
        $this->t_end = strtotime($this->t_end);
    }

    public static function getTypeTime() {
        $list = array();
        $list[self::TIME_WORK] = __('app.timeitem.robochyi_chas');
        $list[self::TINE_OVER] = __('app.timeitem.pererobka');
        $list[self::TINE_WN] = __('app.timeitem.vykhidni_nichna_zmina');
        // $list[self::TINE_FREE] = 'Отгул';
        $list[self::TINE_HL] = __('app.timeitem.vidpustka');
        $list[self::TINE_ILL] = __('app.timeitem.likarnianyi');
        $list[self::TINE_BT] = __('app.timeitem.vidriadzhennia');
        $list[self::TINE_OTHER] = __('app.timeitem.inshe');

        return $list;
    }

    public function isValid() {

        if (($this->t_end - $this->t_start) < 300) {
            return __('app.timeitem.nevirnyi_interval');
        }
        if (($this->t_end - $this->t_start) > (24 * 3600 - 300)) {
            return __('app.timeitem.nevirnyi_interval');
        }
        if (($this->t_end - $this->t_start) <= $this->t_break) {
            return __('app.timeitem.nevirnyi_interval');
        }
        $conn = \ZDB\DB::getConnect();
        $t_start = $conn->DBTimeStamp($this->t_start);
        $t_end = $conn->DBTimeStamp($this->t_end);
        $sql = " select  *  from timesheet where time_id <> {$this->time_id}  and  emp_id={$this->emp_id}  and       (( t_start >  {$t_start}    and  t_start < {$t_end}  ) or ( t_end >  {$t_start}    and  t_end < {$t_end}   ) or (   {$t_start} <= t_start   and  t_end <= {$t_end}   )   )";
        
       
        $sql = " select  count(*)  from timesheet where time_id <> {$this->time_id}  and  emp_id={$this->emp_id}  and       (( t_start >  {$t_start}    and  t_start < {$t_end}  ) or ( t_end >  {$t_start}    and  t_end < {$t_end}   ) or (   {$t_start} <= t_start   and  t_end <= {$t_end}   )   )";
       
        $cnt = $conn->GetOne($sql);

        if ($cnt > 0) {
            return __('app.timeitem.interval_peretynaietsia_z_isnuiuchym');
        }

        return "";
    }

}
