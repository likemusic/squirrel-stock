<?php

namespace App;

use App\Entity\User;

/**
 * Класс  содержащи  методы  работы   с  наиболее  важными
 * системными  данными
 */
class System
{
    public const CURR_VERSION = "8.2.9"; // текущая  версия
    public const PREV_VERSION = "8.2.8"; // предыдущая версия (для отката кода в случае  проблем)
    // SquirrelStock: не проверять и не ставить обновления Zippy с zippy.com.ua (они затёрли бы изменения форка)
    public const FORK_NO_UPSTREAM_UPDATES = true;
    public const REQUIRED_DB  = "8.2.0"; // требуемая  версия  структуры БД
     

    /**
     * Возвращает  текущего  юзера
     * @return  User
     */
    public static function getUser() {
        $user = Session::getSession()->user;
        if ($user == null) {
            $user = new User();
            self::setUser($user);
        }
        return $user;
    }

    /**
     * Устанавливавет  текущего  юзера  в  системме
     *
     * @param User $user
     */
    public static function setUser(User $user) {
        Session::getSession()->user = $user;
    }

    public static function getBranch() {

        return intval(Session::getSession()->branch_id);
    }

    public static function setBranch(int $branch_id) {
        Session::getSession()->branch_id = $branch_id;
    }

    public static function getCustomer() {

        return intval(Session::getSession()->customer_id);
    }

    public static function setCustomer(int $customer_id) {
        Session::getSession()->customer_id = $customer_id;
    }

    /**
     * Возвращает  сессию
     * @return  Session
     */
    public static function getSession() {

        return Session::getSession();
    }

    /**
     * Возвращает набор  параметром  по  имени набора
     *
     * @param mixed $group
     * @param mixed $reload
 
     */
    public static function getOptions($group,$reload= false ) {

        $opp  = Session::getSession()->options ??[] ;
        if(!is_array($opp))  {
            $opp=[];
        }       
        if(isset($opp[$group]) && $reload==false  ) {
            return $opp[$group];
        }
       
       
        $conn = \ZDB\DB::getConnect();

        $rs = $conn->GetOne("select optvalue from options where optname='{$group}' ");
        
        if($group=='version')  {
           return $rs; 
        }
        if (strlen($rs) > 0) {
            // SquirrelStock: в настройках — массивы, строки, числа и App\DataItem (списки валют, доп. полей, источников).
            // Другие классы не восстанавливаются: значение могло прийти из файла импорта настроек или из копии
            $safe = ['allowed_classes' => [\App\DataItem::class]];
            if(strpos($rs,':')>0) {
                $d =  @unserialize($rs, $safe);
                $opp[$group]  = $d;
                Session::getSession()->options = $opp;
                return $d;
            }   

            $d =    @unserialize(@base64_decode($rs), $safe);
            $opp[$group]  = $d;
            Session::getSession()->options = $opp;
            return $d;
            
        }

        return [];
        
    }

    /**
     * возвращает настройку
     *
     * @param mixed $group
     * @param mixed $option
     */
    public static function getOption($group, $option) {

        $options = self::getOptions($group);

        return $options[$option] ??'';
    }

    /**
     * Записывает набор  параметров  по имени набора
     *
     * @param mixed $group
     * @param mixed $options
     */
    public static function setOptions($group, $options) {
     
        $options = serialize($options);
        $options = base64_encode($options) ;
        $conn = \ZDB\DB::getConnect();
        $conn->Execute(" delete from options where  optname='{$group}' ");
        $conn->Execute(" insert into options (optname,optvalue) values ('{$group}'," . $conn->qstr($options) . " ) ");
        Session::getSession()->options = [];
    }
    /**
    * установить отьедный параметр
    *
    * @param mixed $group
    * @param mixed $option
    * @param mixed $value
    */
    public static function setOption($group, $option, $value) {

        $options = self::getOptions($group);
        $options[$option]  = $value;

        self::setOptions($group, $options) ;
    }

   
    public static function setSuccessMsg($msg) {
        Session::getSession()->smsg = $msg;
    }

    public static function getSuccesMsg() {
        return Session::getSession()->smsg;
    }

    public static function setErrorMsg($msg, $toppage=false) {
  
        Session::getSession()->emsg = $msg;
     
    }

    public static function getErrorMsg() {
        return Session::getSession()->emsg;
    }

  

    public static function setWarnMsg($msg) {
        Session::getSession()->wmsg = $msg;
    }

    public static function getWarnMsg() {
        return Session::getSession()->wmsg;
    }

    public static function setInfoMsg($msg, $toppage=false) {
       Session::getSession()->imsg = $msg;
    }

 
   
    public static function getInfoMsg() {
        return Session::getSession()->imsg;
    }

    public static function clean() {
        

    }

    public static function useCron() {
        return  \App\Helper::getKeyVal('cron') ?? false;
    }
  
  /**
  * проверка  на  входязий IP
  * 
  */
    public static function checkIP() {
        $options=self::getOptions('common') ;
        if($options['checkip']  != 1) return;
        if($_SERVER['REMOTE_ADDR']=='127.0.0.1')  return;
        
        $list = explode("\n",$options['iplist'] ) ;
        foreach($list as $ip) {
            if(trim($ip)=== $_SERVER['REMOTE_ADDR']) {
                return;
            }
        }

        http_response_code(403) ;
        die;
    }

    
    /**
    * проверка  обновлений  и ряда  параметров  настроек
    * вызывается  раз в  неделю
    */
    public static function checkUpdate() {
       
       
        $options = System::getOptions("common");       
      
        $lastcheck=intval( \App\Helper::getKeyVal('lastchecksystem')) ;
        if(strtotime('-7 day') < $lastcheck ) {
           return;
        }
     
        \App\Helper::setKeyVal('lastchecksystem',time()) ;
       
        $user = System::getUser() ;
        if ($user->userlogin == "admin") {
                if ($user->userpass === "admin" || password_verify("admin", (string)$user->userpass)) {   // SquirrelStock: любой хеш пароля admin
                    $n = new \App\Entity\Notify();
                    $n->user_id = $user->user_id;
                    $n->message = __('app.system.zminit_u_profili_parol_za') ;
                    $n->sender_id = \App\Entity\Notify::SYSTEM;

                    $n->save();                 
                          
                }
                if(System::useCron() == false){
                    $n = new \App\Entity\Notify();
                    $n->user_id = $user->user_id;
                    $n->message = __('app.system.planuvalnyk_vymkneno_deiaki_fonovi_zavda') ;
                    $n->sender_id = \App\Entity\Notify::SYSTEM;

                    $n->save();                    
                }
        }
         
        if($user->rolename=='admins'   ){
          
            if (\App\Entity\Notify::isNotify(\App\Entity\Notify::SYSTEM)) {
                $n = new \App\Entity\Notify();
                $n->user_id = $user->user_id;
                $n->message = __('app.system.ie_neprochytani_systemni_povidomlennia') ;
                $n->sender_id = \App\Entity\Notify::SYSTEM;

                $n->save();                 
            }          
          
            if(($options['noupdate'] ??0)==1) {
              return;  
            }    
                 
            $b=0;
            $data = System::checkVersion() ;

            if(is_array($data)){
               $b= version_compare($data['version'] , System::CURR_VERSION);
            }               
                 
            if( $b==1 ){
                $n = new \App\Entity\Notify();
                $n->user_id = $user->user_id;
                $n->message = __('app.system.dostupna_nova_versiia_detalnishe', ['version' => $data['version']]) ;
                $n->sender_id = \App\Entity\Notify::SYSTEM;

                $n->save();              
            }                                                     
        }        
    }
    /**
    * проверка   версии
    * 
    */
    public static function checkVersion() {
        // SquirrelStock: обновления Zippy с zippy.com.ua затёрли бы изменения форка, обновления приходят через git
        if (self::FORK_NO_UPSTREAM_UPDATES) {
            return false;
        }

        $url = "https://zippy.com.ua/updates/version.json";
        
        $response = Helper::getContent($url) ;
        if($response === false) {
            return false;
        }
        
        $data = @json_decode($response, true);
        if(!is_array($data)) {
           \App\Helper::logerror(__('app.system.nevirnyi_format_failu', ['url' => $url])) ;
           \App\Helper::logerror($response) ;
           return false;
        }
  
        return $data;     
    }
    
    public static function isVendorActual(){
         
          $json = @file_get_contents(_ROOT. "/vendor/leon-mbs/zippy/composer.json")   ;
          if(strlen($json)>0) {
              $json = json_decode($json,true) ;
              $fv  = $json['ver'] ?? '0.0.0';
            
              $b= version_compare($fv ,'2.8.2');
              if($b == -1) {
                 return false ;  
              }  else {
                 return true;  
              }    
             
          }
       
        return true;  
          
    }
}
