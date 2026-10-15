<?php

namespace App\Entity;

/**
 * Клас-сущность  бухгалтерский  счет
 *
 */
class Account  
{
  
    /**
    * список  счетов
    * 
    * @param mixed $namewithcode    имя с  кодом
    * @param mixed $onlyman только доступные для  ручных проводок (без аналитики)
    */
    public static function getList($namewithcode =false,$onlyman=false) {
         $ret=[];
         $ret[10]=__('app.account.osnovni_zasoby');
         $ret[104]=__('app.account.mashyny_ta_obladnannia');
         $ret[106]=__('app.account.instrumenty_prylady_ta_inventar');
       //  $ret[11]='Інші необоротні активи';
     //    $ret[112]='Малоцінні необоротні матеріальні активи';
         $ret[12]=__('app.account.nematerialni_aktyvy');
         $ret[13]=__('app.account.znos_neoborotnykh_aktyviv');
         $ret[131]=__('app.account.znos_osnovnykh_zasobiv');
         $ret[132]=__('app.account.znos_mnma');
         $ret[15]=__('app.account.kapitalni_investytsii');
         $ret[20]=__('app.account.vyrobnychi_zapasy');
         $ret[201]=__('app.account.syrovyna_i_materialy');
       //  $ret[203]='Паливо';
         $ret[207]=__('app.account.zapchastyny');
         $ret[22]=__('common.mshp');
         $ret[23]=__('common.vyrobnytstvo');
         $ret[25]=__('common.napivfabrykaty');
         $ret[26]=__('common.hotova_produktsiia');
         $ret[28]=__('common.tovary');
         $ret[30]=__('common.kasa');
         $ret[31]=__('app.account.bank');
         $ret[36]=__('app.account.rozrakhunky_z_pokuptsiamy');
         $ret[37]=__('app.account.rozrakhunky_z_riznymy_debitoramy');
    //     $ret[371]='Розрахунки за виданими авансами';
         $ret[372]=__('app.account.rozrakhunky_z_pidzvitnymy_osobamy');
         $ret[40]=__('app.account.vlasnyi_kapital');
         $ret[44]=__('app.account.nerozpodilenyi_prybutok');
         $ret[63]=__('app.account.rozrakhunky_z_postachalnykamy');
         $ret[64]=__('app.account.rozrakhunky_za_podatkamy_i_platezhamy');
         $ret[641]=__('app.account.rozrakhunky_za_podatkamy');
         $ret[642]=__('app.account.rozrakhunky_za_platezhamy');
         $ret[643]=__('app.account.podatkovi_zoboviazannia');
         $ret[644]=__('app.account.podatkovyi_kredyt');
         $ret[65]=__('app.account.rozrakhunky_za_strakhuvanniam');
         $ret[66]=__('app.account.rozrakhunky_po_zarobitnii_plati');
         $ret[68]=__('app.account.rozrakhunky_za_inshmy_operatsiiamy');
    //     $ret[681]='Розрахунки за отриманими авансами';
         $ret[70]=__('app.account.dokhody_vid_realizatsii');
 
         $ret[71]=__('app.account.dokhody_operatsiinoi_diialnosti');
         $ret[79]=__('app.account.finansovi_rezultaty');
         $ret[90]=__('app.account.sobivartist_realizatsii');
 
         $ret[91]=__('common.zahalnovyrobnychi_vytraty');
         $ret[92]=__('common.administratyvni_vytraty');
         $ret[93]=__('common.vytraty_na_zbut');
         $ret[94]=__('app.account.inshi_vytraty_operatsiinoi_diialnosti');
         $ret[941]=__('app.account.nakladni_vytraty');
         $ret[942]=__('common.otrymani_posluhy');
         $ret[943]=__('app.account.komunalka');
         $ret[944]=__('app.account.arenda');
         $ret[947]=__('app.account.nedostachi_ta_vtraty_vid_psuvannia');
         $ret[949]=__('app.account.inshi_vytraty_operatsiinoi_diialnosti');
         $ret[97]=__('common.inshi_vytraty');
         $ret[99]=__('app.account.podatok_na_prybutok');
        
         
         if($onlyman) { 
            $man=[40,44,641,642,643,644,65,704,71,79,904,94,941,943,944,97,99]; 
            $retc=[]; 
            foreach($ret as $c=>$n) {
                
               if(in_array($c,$man) ){
                   $retc[$c] = $n   ; 
               }
              
            }
            $ret =  $retc;       
         }
         if($namewithcode) { 
            $retc=[]; 
            foreach($ret as $c=>$n) {
               $retc[$c] = $c.' '. $n  ;
            }
            $ret =  $retc;
         }
         
         
         return $ret;
    }
 
    /**
     * список использованых счетов
     *
     */
    public static function getUsedList($namewithcode =false ) {
           $all=self::getList($namewithcode) ;
           $ret=[];
           $conn = \ZDB\DB::getConnect();
           $col=$conn->GetCol("select distinct  accdt from acc_entry");
           foreach($col  as $code){
              if(!isset($ret[$code]) && isset($all[$code])) {
                 $ret[$code]  = $all[$code]  ;
              }
           }
           $col=$conn->GetCol("select distinct  accct from acc_entry");
           foreach($col as $code){
              if(!isset($ret[$code]) && isset($all[$code])) {
                 $ret[$code]  = $all[$code]  ;
              }
           }
           
           return $ret;         
    }
 

   
    /**
    * обороты за период
    * 
    * @param mixed $from
    * @param mixed $to
    */
    public static function getOb($acc,$from, $to) {
        $conn = \ZDB\DB::getConnect();
 
        $acc = $conn->qstr($acc.'%');
        
        $from = $conn->DBDate($from)  ;
        $to = $conn->DBDate($to)  ;
            
        $w = "";
        $c = \App\ACL::getBranchConstraint();
        if (strlen($c) > 0) {
            $w .= " and " . $c;
        }        
        $sql = "select coalesce(sum(amount),0) from  acc_entry_view where accdt like {$acc} and createdon >= {$from} and createdon <= {$to}   {$w}";
        $obdt = $conn->GetOne($sql);
        $sql = "select coalesce(sum(amount),0) from  acc_entry_view where accct like {$acc} and createdon >= {$from} and createdon <= {$to}  {$w}" ;
        $obct = $conn->GetOne($sql);
        
        return ['dt'=>$obdt,'ct'=>$obct];
    }

    /**
    * сальдо на  дату
    * 
    * @param mixed $date
    * @param mixed $ext   развернутое
    */
    public static function getSaldo($acc,$date, $ext=false) {
        $conn = \ZDB\DB::getConnect();
 
        $acc = $conn->qstr($acc.'%');
        
        $date = $conn->DBDate($date)  ;
            
        $w = "";
        $c = \App\ACL::getBranchConstraint();
        if (strlen($c) > 0) {
            $w .= " and " . $c;
        }        
        $sql = "select coalesce(sum(amount),0) from  acc_entry_view where accdt like {$acc} and createdon <= {$date} {$w}";
        $startdt = $conn->GetOne($sql);
        $sql = "select coalesce(sum(amount),0) from  acc_entry_view where accct like {$acc} and createdon <= {$date} {$w}" ;
        $startct = $conn->GetOne($sql);
        
        
        if($ext)  {
            return ['dt'=>$startdt,'ct'=>$startct];
        }
        
        //сворачиваем
        if($startdt > $startct) {
           $startdt = $startdt- $startct; 
           return ['dt'=>$startdt,'ct'=>0];
         
        }   else {
           $startct = $startct- $startdt; 
         
           return ['dt'=>0,'ct'=>$startct];
            
        }
        
    
    }

 
 
  

    /**
    * оборот между счетами
    * 
    * @param mixed $acc_dt
    * @param mixed $acc_ct
    * @param mixed $from
    * @param mixed $to
    * @param mixed $bc
    */
    public static function getObBetweenAccount($acc_dt, $acc_ct, $from, $to ) {
        $w = "";
        $c = \App\ACL::getBranchConstraint();
        if (strlen($c) > 0) {
            $w .= " and " . $c;
        }  

        $conn = \ZDB\DB::getConnect();
        $acc_dt = $conn->qstr($acc_dt . '%');
        $acc_ct = $conn->qstr($acc_ct . '%');

        $sql = "select coalesce(sum(amount),0) from   acc_entry_view where {$w}  accdt like {$acc_dt} and  accct like {$acc_ct}   and createdon  >= " . $conn->DBDate($from) . " and createdon <= " . $conn->DBDate($to);

        return $conn->GetOne($sql) ;
    }

    /**
    * обороты  по  счету по документу
    * 
    * @param mixed $acc_code
    * @param mixed $doc_id
    */
    public static function getObDoc($acc_code,$doc_id) {
        $conn = \ZDB\DB::getConnect();
 
        $acc = $conn->qstr($acc_code.'%');
  
        
        $sql = "select coalesce(sum(amount),0) from  acc_entry_view where accdt like {$acc} and document_id=".$doc_id;
        $obdt = $conn->GetOne($sql);
        $sql = "select coalesce(sum(amount),0) from  acc_entry_view where accct like {$acc} and document_id=".$doc_id;
        $obct = $conn->GetOne($sql);
        
        return ['dt'=>$obdt,'ct'=>$obct];
    }
    
    //счета  с  проводками
    public  static  function getWithEntry() {
        $list=[];
        $conn = \ZDB\DB::getConnect();
        $sql = "  select distinct accct from  acc_entry_view where  document_id >0";
        foreach($conn->getCol($sql) as $acc ){
           $list[$acc]=$acc; 
        } 
   
        $sql = "  select distinct accdt from  acc_entry_view where  document_id >0";
        foreach($conn->getCol($sql) as $acc ){
           $list[$acc]=$acc; 
        } 
        return array_keys($list);
    }    
 
 
     /**
    * счет  учета  ТМЦ по типу
    *  
    */
    public static  function getAccCode(){
            $list=[];
            $list[0]='28'; 
            $list[\App\Entity\Item::TYPE_TOVAR]='28'; 
            $list[\App\Entity\Item::TYPE_MAT]='201'; 
            $list[\App\Entity\Item::TYPE_MBP]='22'; 
            $list[\App\Entity\Item::TYPE_PROD]='26'; 
            $list[\App\Entity\Item::TYPE_HALFPROD]='25'; 
            return $list;
    
    }
   
   
}
