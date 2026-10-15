<?php

namespace App\Entity;

/**
 * сущность для хранения движения по  ОС
 *
 * @view=eqentry_view
 * @table=eqentry
 * @keyfield=id
 */
class EqEntry extends \ZCL\DB\Entity
{  
    public const OP_INCOME = 1;
    public const OP_OUTCOME = 2;
    public const OP_AMOR = 3;
    public const OP_REPAIR = 4;
    public const OP_MOVE = 5;
    public const OP_BUY = 6;
    public const OP_PROD = 7;
    public const OP_STORE = 8;
    public const OP_SELL = 9;
    public const OP_TOSTORE = 10;
    public const OP_LOST = 11;

    public function __construct(   ) {
        parent::__construct();
        
    }

    protected function init() {
        $this->amount=0;
    }

    protected function afterLoad() {
        $this->document_date = strtotime($this->document_date);
    }

    public static function getOpList(){
        
        $list=[];
        $list[self::OP_INCOME] = __('app.eqentry.vvedennia_v_ekspluatatsiiu') ;
        $list[self::OP_OUTCOME] = __('app.eqentry.vyvedennia_z_ekspluatatsii') ;
        $list[self::OP_AMOR] = __('app.eqentry.narakhuvannia_amortyzatsii') ;
        $list[self::OP_REPAIR] = __('app.eqentry.remont_ta_vidnovlennia') ;
        $list[self::OP_MOVE] = __('app.eqentry.peremishchennia_mizh_vyr_diliankamy') ;
        $list[self::OP_BUY] = __('app.eqentry.zakupka') ;
        $list[self::OP_PROD] = __('app.eqentry.oprykhoduvannia_z_vyrobnytstva') ;
        $list[self::OP_STORE] = __('app.eqentry.oprykhoduvannia_zi_skladu') ;
        $list[self::OP_SELL] = __('app.eqentry.prodazh') ;
        $list[self::OP_TOSTORE] = __('app.eqentry.oprykhoduvannia_na_sklad') ;
        $list[self::OP_LOST] = __('app.eqentry.spysannia') ;
        
        return $list ;
   }   
}
