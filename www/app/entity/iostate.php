<?php

namespace App\Entity;

/**
 * Класс-сущность  оплата
 *
 * @table=iostate
 * @keyfield=id
 * @view=iostate_view
 */
class IOState extends \ZCL\DB\Entity
{
    //доход платежи
    public const TYPE_BASE_INCOME  = 1;     //доходы реализации товаров  услцн
    public const TYPE_OTHER_INCOME = 2;     //прочие доходы
    public const TYPE_FIN          = 3;     //доходы от  фин.  деятельности
 //   public const TYPE_CANCEL_CUST  = 5;     //отмена  платежа  покупки
    public const TYPE_INEQ         = 6;     //ввод в  экплуатацию ОС
    public const TYPE_INVEQ        = 7;     //ремонт и восстановдение ОС

    //внебалансовые доходы (для  статистики)
    public const TYPE_OVER      = 30;     //излишки при инвентаризации
    public const TYPE_BEGIN     = 31;     //внесение  начальных остатков
 
    //расход платежи
    public const TYPE_BASE_OUTCOME     = 50;    // закупка ТМЦ  услуг
    public const TYPE_COMMON_OUTCOME   = 51;    //общепроизводственные  расходы
    public const TYPE_ADMIN_OUTCOME    = 52;    //административные  расходы
    public const TYPE_SALE_OUTCOME     = 53;    //расходы на сбыт
    public const TYPE_SALARY_OUTCOME   = 54;    //выплата зарплат
    public const TYPE_TAX_OUTCOME      = 55;    //уплата прочих налогов  и сборов
    public const TYPE_TAX_NDS          = 70;    //уплата  НДС
    public const TYPE_TAX_CARE         = 71;    //уплата  по страхованию
    public const TYPE_BILL_RENT        = 56;    //расходы на  аренду
    public const TYPE_OTHER_OUTCOME    = 57;   //прочие расходы
    public const TYPE_DIVIDEND_OUTCOME = 58;   //распределение прибыли
    public const TYPE_INV              = 59;   //Инвестиции
    public const TYPE_BANK             = 60;   //Банковское  обслуживание
  //  public const TYPE_CANCEL           = 61;    //отмена  платежа  продажи
    public const TYPE_NAKL             = 62;    //накладные  расходы
    public const TYPE_ADS              = 63;    //   расходы на  маркетинг
    public const TYPE_BILL_OUTCOME     = 64;    //расходы на  комуналку
    public const TYPE_OUTSERVICE       = 65;    //расходы на услуги
    public const TYPE_AMOR             = 67;    // амортизация ОС
     public const TYPE_PROD             = 68;    // прямые производственные  затраты
  //   public const TYPE_ADVANCEREP       = 69;    // Подотчет сотрудниу

    //внебалансовые расходы (для  статистики)   
    public const TYPE_LOST             = 80;     //потери при инвентаризации
    public const TYPE_TRASH            = 81;     //отходы производства
    public const TYPE_OUTEQ            = 82;    // списание ОС
  


    protected function init() {
        $this->id = 0;
        $this->iotype = 0;

    }

    /**
    * Добавление  записи  о расходах-доходах
    * 
    * @param mixed $document_id
    * @param mixed $amount
    * @param mixed $type
    * @param mixed $storno   для возвратов
    * @param mixed $date  дата  если  отличается  от  даты  документа
    * @return mixed
    */
    public static function addIOState($document_id, $amount, $type,$storno=false,$date=null) {
        if (0 == doubleval($amount) || 0 == intval($document_id) || 0 == intval($type)) {
            return;
        }

        $amount = abs($amount) ;
        if(intval($type) >= 50) { //расходы
            $amount = 0 - $amount;
        }

        if($storno) {  
            $amount = 0 - $amount;
        }


        $io = new  IOState();
        $io->document_id = $document_id;
        $io->amount = $amount;
        $io->iotype = $type;
        if($date != null) {
          $io->iodate = $date;  
        }
        $io->save();
    }


    protected function afterLoad() {
        $this->document_date = strtotime($this->document_date);

        parent::afterLoad();
    }

    /**
     * типы  доходов  и расходов
     *
     * @param mixed $type 1=    доход (для списков),2-   расход (для списков),   0 - все 
     */
    public static function getTypeList($type = 0) {
        $list = array();
        if ($type == 1 ||   $type == 0  ) {
            $list[self::TYPE_BASE_INCOME] = __('app.iostate.dokhody_realizatsii');

            $list[self::TYPE_FIN] = __('app.iostate.dokhody_vid_finansovykh_operatsii');
          //  $list[self::TYPE_CANCEL_CUST] = "Скасування платежу закупівлі";
            $list[self::TYPE_OTHER_INCOME] = __('app.iostate.inshi_dokhody');
         //  $list[self::TYPE_INEQ] = "Інші доходи";
        }

        if ($type == 2 ||   $type == 0  ) {
            $list[self::TYPE_BASE_OUTCOME] = __('app.iostate.vytraty_na_zakupku');
            $list[self::TYPE_COMMON_OUTCOME] = __('common.zahalnovyrobnychi_vytraty');
            $list[self::TYPE_PROD] = __('app.iostate.vytraty_na_vyrobnytstvo');
            $list[self::TYPE_ADMIN_OUTCOME] = __('common.administratyvni_vytraty');
            $list[self::TYPE_SALE_OUTCOME] = __('common.vytraty_na_zbut');
            $list[self::TYPE_SALARY_OUTCOME] = __('app.iostate.vytraty_na_zarplatu');
            $list[self::TYPE_TAX_NDS] =  __('app.iostate.rozrakhunky_z_pdv');
            $list[self::TYPE_TAX_CARE] =  __('app.iostate.rozrakhunky_z_strakhuvannia');
            $list[self::TYPE_TAX_OUTCOME] =  __('app.iostate.inshi_podatky_ta_zbory');
            $list[self::TYPE_BILL_OUTCOME] = __('app.iostate.vytraty_na_komunalku');
            $list[self::TYPE_BILL_RENT] = __('app.iostate.vytraty_na_orendu');
            $list[self::TYPE_DIVIDEND_OUTCOME] = __('app.iostate.rozpodil_prybutku');
            $list[self::TYPE_INV] = __('app.iostate.investytsii');
            $list[self::TYPE_BANK] = __('app.iostate.posluhy_banku');
         //   $list[self::TYPE_CANCEL] =  "Скасування платежу продажу";
            $list[self::TYPE_NAKL] =  __('app.account.nakladni_vytraty');
            $list[self::TYPE_ADS] =  __('app.iostate.vytraty_na_marketynh_ta_reklamu');
            $list[self::TYPE_OTHER_OUTCOME] = __('common.inshi_vytraty');
            $list[self::TYPE_OUTSERVICE] = __('app.iostate.vytraty_na_posluhy');
     //       $list[self::TYPE_ADVANCEREP] = "Підзвіт співробітнику";
        }

  
 
 
        if (  $type == 0) {
            $list[self::TYPE_INEQ] = __('app.iostate.vvod_oz_v_ekspluatatsiiu');
            $list[self::TYPE_INVEQ] = __('app.iostate.remont_ta_vidnovlennia_oz');
            $list[self::TYPE_OUTEQ] = __('app.iostate.spysannia_oz');
            $list[self::TYPE_AMOR] = __('app.iostate.amortyzatsiia_oz');
            $list[self::TYPE_LOST] = __('app.iostate.vtraty_pry_inventaryzatsii');
            $list[self::TYPE_TRASH] = __('app.iostate.vidkhody_vyrobnytstva');
            $list[self::TYPE_OVER] = __('app.iostate.nadlyshky_pry_inventaryzatsii');
            $list[self::TYPE_BEGIN] = __('app.iostate.vnesennia_pochatkovykh_zalyshkiv');
           
        }

        return $list;
    }
    
    /**
    * Для  начисления  зарплаты
    * 
    */
    public static function getTypeListSal( ) {
       $list = array();
       $list[self::TYPE_PROD] = __('app.iostate.vytraty_na_vyrobnytstvo');
       $list[self::TYPE_COMMON_OUTCOME] = __('common.zahalnovyrobnychi_vytraty');
       $list[self::TYPE_ADMIN_OUTCOME] = __('common.administratyvni_vytraty');
       $list[self::TYPE_SALE_OUTCOME] = __('common.vytraty_na_zbut');
       $list[self::TYPE_OTHER_OUTCOME] = __('common.inshi_vytraty');
  
       return $list;
    }

 
    /**
    * Для  авансовго отчета
    * 
    */
    public static function getTypeListAdv( ) {
       $list = array();
       $list[self::TYPE_COMMON_OUTCOME] = __('common.zahalnovyrobnychi_vytraty');
       $list[self::TYPE_ADMIN_OUTCOME] = __('common.administratyvni_vytraty');
       $list[self::TYPE_SALE_OUTCOME] = __('common.vytraty_na_zbut');
       $list[self::TYPE_OTHER_OUTCOME] = __('common.inshi_vytraty');
  
       return $list;
    }

    /**
    * Для  приходного кассового ордера
    * 
    */
    public static function getTypeListInM( ) {
       $list = array();

       $list[self::TYPE_FIN] = __('app.iostate.dokhody_vid_finansovykh_operatsii');
       $list[self::TYPE_OTHER_INCOME] = __('app.iostate.inshi_dokhody');
       $list[self::TYPE_BEGIN] = __('app.iostate.vnesennia_pochatkovykh_zalyshkiv');
       
       return $list;
    }
    /**
    * Для  расходного  кассового ордера
    * 
    */
    public static function getTypeListOutM( ) {
        $list = array();

        $list[self::TYPE_COMMON_OUTCOME] = __('common.zahalnovyrobnychi_vytraty');
        $list[self::TYPE_ADMIN_OUTCOME] = __('common.administratyvni_vytraty');
        $list[self::TYPE_SALE_OUTCOME] = __('common.vytraty_na_zbut');
        $list[self::TYPE_TAX_NDS] =  __('app.iostate.rozrakhunky_z_pdv');
        $list[self::TYPE_TAX_CARE] =  __('app.iostate.rozrakhunky_z_strakhuvannia');
        $list[self::TYPE_TAX_OUTCOME] =  __('app.iostate.inshi_podatky_ta_zbory');
        $list[self::TYPE_BILL_OUTCOME] = __('app.iostate.vytraty_na_komunalku');
        $list[self::TYPE_BILL_RENT] = __('app.iostate.vytraty_na_orendu');
        $list[self::TYPE_DIVIDEND_OUTCOME] = __('app.iostate.rozpodil_prybutku');
        $list[self::TYPE_INV] = __('app.iostate.investytsii');
        $list[self::TYPE_NAKL] =  __('app.account.nakladni_vytraty');
        $list[self::TYPE_ADS] =  __('app.iostate.vytraty_na_marketynh_ta_reklamu');
        $list[self::TYPE_BANK] = __('app.iostate.posluhy_banku');
        $list[self::TYPE_SALARY_OUTCOME] = __('common.vyplata_zarplaty');
        $list[self::TYPE_OTHER_OUTCOME] = __('common.inshi_vytraty');
   
       return $list;
    }
 
  /**
  * Для  книги доходов и расходов
    * 
    */
    public static function getTypeListBook( ) {
       $list = array();

       $list[self::TYPE_BASE_INCOME] = __('app.iostate.dokhody_realizatsii');
       $list[self::TYPE_FIN] = __('app.iostate.dokhody_vid_finansovykh_operatsii');
       $list[self::TYPE_OTHER_INCOME] = __('app.iostate.inshi_dokhody');
          
        
        $list[self::TYPE_BASE_OUTCOME] = __('app.iostate.vytraty_na_zakupku');
        $list[self::TYPE_OTHER_OUTCOME] = __('common.inshi_vytraty');
            

        $list[self::TYPE_TAX_NDS] =  __('app.iostate.rozrakhunky_z_pdv');
        $list[self::TYPE_TAX_CARE] =  __('app.iostate.rozrakhunky_z_strakhuvannia');
        $list[self::TYPE_TAX_OUTCOME] =  __('app.iostate.inshi_podatky_ta_zbory');
        $list[self::TYPE_AMOR] = __('app.iostate.amortyzatsiia_oz');
              
        $list[self::TYPE_ADS] =  __('app.iostate.vytraty_na_marketynh_ta_reklamu');
        $list[self::TYPE_BANK] = __('app.iostate.posluhy_banku');
        $list[self::TYPE_SALE_OUTCOME] = __('common.vytraty_na_zbut');
        
        $list[self::TYPE_SALARY_OUTCOME] = __('common.vyplata_zarplaty');
  
   
       return $list;
    }
    
    
}
